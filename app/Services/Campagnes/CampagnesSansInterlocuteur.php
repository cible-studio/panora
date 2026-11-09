<?php

namespace App\Services\Campagnes;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\DiffusionDesinscription;

/**
 * Campagnes dont l'interlocuteur reste à renseigner (2026-11-09), par
 * commercial. Sans interlocuteur, les mails de suivi partent à tous les
 * contacts cochés du client : chaque commercial complète sa liste.
 *
 * « Sans interlocuteur » suit Client::interlocuteurRetenu(), la règle des
 * envois : aucun, supprimé, d'un autre client ou sans adresse valide.
 *
 * Par défaut, seules les campagnes planifiées, en cours ou en pause : une
 * campagne terminée ou annulée ne reçoit plus de mails de suivi.
 */
class CampagnesSansInterlocuteur
{
    public const SANS_COMMERCIAL = 'Sans commercial';

    private const STATUTS_EN_COURS = [CampaignStatus::PLANIFIE, CampaignStatus::ACTIF, CampaignStatus::PAUSE];

    /** Contacts cités par campagne : une grande entreprise peut en compter des dizaines. */
    private const MAX_CONTACTS = 8;

    /**
     * @return Collection<string, Collection<int, array{commercial: string, echeance: string, client: string, campagne: string, statut: string, reconduction: string, contacts: string}>>
     *         lignes par nom de commercial, les échéances les plus proches d'abord
     */
    public function parCommercial(bool $toutes = false): Collection
    {
        $exclues = DiffusionDesinscription::pluck('email')
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->flip();

        return Campaign::query()
            ->with(['client.contacts', 'interlocuteur', 'commercial', 'user'])
            ->when(!$toutes, fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::STATUTS_EN_COURS)))
            ->orderByRaw('end_date IS NULL')
            ->orderBy('end_date')
            ->get()
            ->filter(fn (Campaign $c) => $c->client
                && !$c->client->trashed()
                && !$c->client->interlocuteurRetenu($c->interlocuteur))
            ->map(fn (Campaign $c) => [
                'commercial'   => $c->resolveCommercialContact()?->name ?: self::SANS_COMMERCIAL,
                'echeance'     => $c->end_date?->format('d/m/Y') ?? '',
                'client'       => $c->client->name,
                'campagne'     => $c->name,
                'statut'       => $c->status?->label() ?? '',
                'reconduction' => $c->estAnnuelle() ? 'Oui' : 'Non',
                'contacts'     => $this->contactsConnus($c->client, $exclues),
            ])
            ->groupBy('commercial')
            ->sortKeys();
    }

    /** Contacts déjà connus, pour que le commercial n'ait qu'à choisir. Sans les adresses mortes ou désinscrites. */
    private function contactsConnus(Client $client, Collection $exclues): string
    {
        $contacts = $client->contacts
            ->filter(fn ($k) => filter_var(trim((string) $k->email), FILTER_VALIDATE_EMAIL)
                && !$exclues->has(mb_strtolower(trim($k->email))))
            ->sortByDesc(fn ($k) => ($k->is_primary ? '1' : '0') . ($k->dernier_echange?->format('Y-m-d') ?? ''))
            ->values();

        $cites = $contacts->take(self::MAX_CONTACTS)
            ->map(fn ($k) => trim($k->name . ' <' . mb_strtolower(trim($k->email)) . '>'))
            ->implode("\n");

        $reste = $contacts->count() - self::MAX_CONTACTS;

        return $reste > 0 ? $cites . "\n+ {$reste} autre(s)" : $cites;
    }
}
