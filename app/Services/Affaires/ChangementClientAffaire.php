<?php

namespace App\Services\Affaires;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ExternalPanel;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RelanceFinCampagne;
use App\Models\Renouvellement;
use App\Models\Reservation;
use App\Models\SatisfactionSurvey;
use Illuminate\Support\Facades\Log;

/**
 * ═══ SOURCE UNIQUE — changer le client d'une affaire (2026-11-16) ═══
 *
 * Une affaire = une réservation et sa campagne. Depuis le 29/10, elles
 * doivent porter le même client ; mais la campagne refusait un client
 * différent de la réservation, et la réservation refusait de changer de
 * client dès qu'elle avait sa campagne : un mauvais client ne pouvait plus
 * être corrigé. Désormais, changer le client d'un côté (fiche campagne OU
 * fiche réservation) le change des deux côtés, et déplace ce qui suit.
 *
 * RÈGLES VALIDÉES PAR ÉCRIT LE 2026-11-16 :
 *   1. Une facture validée ou envoyée (tout statut hors brouillon et
 *      annulée) empêche le changement : elle a été émise à l'ancien client,
 *      il faut passer par un avoir. Les factures en brouillon suivent.
 *   2. Relances de fin de campagne, renouvellement et enquête de
 *      satisfaction pas encore partis suivent ; ceux déjà envoyés restent.
 *   3. Le devis converti en cette réservation et la proposition (portée par
 *      la réservation) suivent aussi le nouveau client.
 *   4. Admin et Media Planner (rôles des deux écrans de modification).
 * Trouvé en codant : les panneaux de régies externes de la campagne portent
 * aussi le client — ils suivent.
 *
 * L'écran enregistre son propre dossier (validation, interlocuteur,
 * observateurs) ; ce service déplace l'autre moitié et le reste, dans la
 * même transaction, par des mises à jour directes (aucun mail ne part).
 */
class ChangementClientAffaire
{
    /** Statuts de facture qui n'empêchent pas le changement. */
    public const FACTURES_LIBRES = ['brouillon', 'annulee'];

    /** Renouvellement sans réponse du client : il suit le nouveau client. */
    public const RENOUVELLEMENTS_EN_COURS = [Renouvellement::EN_ATTENTE, Renouvellement::RAPPEL];

    /**
     * Pourquoi le client de l'affaire ne peut pas changer, ou null.
     */
    public function empechement(?Campaign $campaign, ?Reservation $reservation = null): ?string
    {
        $campaign ??= $reservation?->campaign;
        if (!$campaign) {
            return null;
        }

        $emises = Invoice::where('campaign_id', $campaign->id)
            ->whereNotIn('status', self::FACTURES_LIBRES)
            ->pluck('reference');

        if ($emises->isEmpty()) {
            return null;
        }

        return 'Facture déjà émise au client actuel (' . $emises->implode(', ') . ') : '
            . 'le client de cette affaire ne peut plus changer. Repassez la facture en brouillon '
            . 'si elle n\'a pas été envoyée, sinon faites un avoir.';
    }

    /**
     * Nom de campagne à reprendre quand il porte le nom de l'ancien client
     * (« Campagne 2A CONSULTING » → « Campagne 2A ÉDITIONS ») ; sinon inchangé.
     */
    public function nomParDefaut(string $nom, ?string $ancienClient, ?string $nouveauClient): string
    {
        if ($ancienClient && $nouveauClient && trim($nom) === 'Campagne ' . trim($ancienClient)) {
            return 'Campagne ' . trim($nouveauClient);
        }
        return $nom;
    }

    /**
     * Déplace tout ce qui suit le client de l'affaire. À appeler dans la
     * transaction de l'écran, APRÈS l'enregistrement de son propre dossier.
     *
     * @return array<string, int> nombre de lignes déplacées, par nature
     */
    public function propager(?Campaign $campaign, ?Reservation $reservation, int $nouveauClientId, ?int $contactId): array
    {
        $campaign    ??= $reservation?->campaign;
        $reservation ??= $campaign?->reservation;
        $nouveau = Client::withTrashed()->find($nouveauClientId);
        $faits = [];

        if ($reservation && (int) $reservation->client_id !== $nouveauClientId) {
            $faits['reservation'] = Reservation::whereKey($reservation->id)
                ->update(['client_id' => $nouveauClientId, 'client_contact_id' => $contactId]);
        }

        if ($campaign && (int) $campaign->client_id !== $nouveauClientId) {
            $ancienNom = Client::withTrashed()->whereKey($campaign->client_id)->value('name');
            $faits['campagne'] = Campaign::whereKey($campaign->id)->update([
                'client_id'         => $nouveauClientId,
                'client_contact_id' => $contactId,
                'name'              => $this->nomLibre($this->nomParDefaut($campaign->name, $ancienNom, $nouveau?->name), $campaign, $nouveauClientId),
            ]);
        }

        if ($campaign) {
            $faits['factures_brouillon'] = Invoice::where('campaign_id', $campaign->id)
                ->where('status', 'brouillon')
                ->where('client_id', '!=', $nouveauClientId)
                ->update(['client_id' => $nouveauClientId]);
            $faits['relances'] = RelanceFinCampagne::where('campaign_id', $campaign->id)
                ->where('statut', '!=', RelanceFinCampagne::STATUT_ENVOYE)
                ->update(['client_id' => $nouveauClientId]);
            $faits['renouvellements'] = Renouvellement::where('campaign_id', $campaign->id)
                ->whereIn('statut', self::RENOUVELLEMENTS_EN_COURS)
                ->update(['client_id' => $nouveauClientId]);
            $faits['enquetes'] = SatisfactionSurvey::where('campaign_id', $campaign->id)
                ->whereNull('sent_at')
                ->update(['client_id' => $nouveauClientId]);
            $faits['panneaux_externes'] = ExternalPanel::where('campaign_id', $campaign->id)
                ->update(['client_id' => $nouveauClientId]);
        }

        if ($reservation) {
            $faits['devis'] = Quote::where('converted_reservation_id', $reservation->id)
                ->update(['client_id' => $nouveauClientId, 'client_contact_id' => $contactId]);
        }

        $faits = array_filter($faits);
        Log::info('affaire.changement_client', [
            'campaign_id'    => $campaign?->id,
            'reservation_id' => $reservation?->id,
            'vers'           => $nouveauClientId,
            'deplaces'       => $faits,
            'by'             => auth()->id(),
        ]);

        return $faits;
    }

    /**
     * Un nom libre chez le nouveau client (un nom de campagne est unique par
     * client) : le nom proposé, sinon l'ancien, sinon l'ancien suivi du n°.
     */
    public function nomLibre(string $nom, Campaign $campaign, int $clientId): string
    {
        $pris = fn (string $n) => Campaign::where('client_id', $clientId)->where('name', $n)
            ->where('id', '!=', $campaign->id)->whereNull('deleted_at')->exists();

        foreach ([$nom, $campaign->name] as $candidat) {
            if (!$pris($candidat)) {
                return $candidat;
            }
        }
        return mb_substr($campaign->name, 0, 140) . ' #' . $campaign->id;
    }
}
