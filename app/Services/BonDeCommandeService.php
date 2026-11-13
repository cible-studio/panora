<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BonDeCommandeService — source unique de la règle « sans BC, pas de campagne ».
 *
 * ═══ RÈGLE MÉTIER VALIDÉE PAR ÉCRIT — 2026-11-13 ═══
 *
 * Avant : dès que le client cliquait « J'accepte » sur sa proposition,
 * PropositionService::confirmer() faisait TROIS choses d'un coup —
 * bloquer fermement les panneaux, annuler les options concurrentes, et
 * CRÉER LA CAMPAGNE. Or le client n'avait pas forcément envoyé son bon de
 * commande, et sans BC il ne doit pas y avoir de campagne.
 *
 * Maintenant, quatre temps :
 *
 *   1. Le client s'engage (clic proposition, devis accepté, résa ferme
 *      saisie, renouvellement accepté) → la réservation reste EN OPTION
 *      et une fenêtre BC s'ouvre (30 jours, config/bon_de_commande.php).
 *      Les panneaux ne sont PAS bloqués fermement et les options
 *      concurrentes ne sont PAS annulées : plusieurs dossiers peuvent
 *      attendre un BC sur le même panneau, le premier BC gagne.
 *      Les écrans doivent le dire (cf. dossiersConcurrents()).
 *
 *   2. Relances automatiques à J-15, J-7 et J-2 : commercial du dossier,
 *      MP en copie.
 *
 *   3. Le commercial enregistre la réception du BC — VERBALEMENT suffit,
 *      aucune pièce jointe obligatoire. Un champ libre facultatif
 *      recueille le numéro de BC ou toute autre information.
 *
 *   4. Le MP crée la campagne dédiée. C'est seulement là que les panneaux
 *      se bloquent fermement et que les dossiers concurrents tombent.
 *
 *   Passé le délai sans BC, l'option expire et les panneaux repartent au
 *   catalogue (reservations:expire-options).
 *
 * Aucune des dates ni des durées n'est écrite ailleurs : tout vient d'ici
 * ou de la config.
 */
class BonDeCommandeService
{
    /** Durée de la fenêtre BC, en jours. */
    public static function delaiJours(): int
    {
        return max(1, (int) config('bon_de_commande.delai_jours', 30));
    }

    /** Paliers de relance, en jours RESTANTS avant l'échéance. */
    public static function paliersRelance(): array
    {
        return (array) config('bon_de_commande.relances_jours_restants', [15, 7, 2]);
    }

    /** Colonne mémorisant la relance d'un palier (null si palier inconnu). */
    public static function colonneRelance(int $joursRestants): ?string
    {
        return config('bon_de_commande.colonnes_relance')[$joursRestants] ?? null;
    }

    // ══════════════════════════════════════════════════════════════
    // 1 — OUVRIR LA FENÊTRE
    // ══════════════════════════════════════════════════════════════

    /**
     * Le client s'est engagé : on ouvre l'attente de BC. La réservation
     * reste en option, aucune campagne n'est créée.
     *
     * Idempotent : si une fenêtre est déjà ouverte, on n'y touche pas
     * (sinon un second clic du client repousserait l'échéance).
     */
    public function ouvrirAttente(Reservation $reservation): Reservation
    {
        if ($reservation->bc_attendu_jusqu_au) {
            return $reservation;
        }

        $reservation->forceFill([
            'bc_attendu_jusqu_au' => Carbon::today()->addDays(self::delaiJours())->toDateString(),
            // Une nouvelle attente redémarre le cycle de relances.
            'bc_relance_j15_at'   => null,
            'bc_relance_j7_at'    => null,
            'bc_relance_j2_at'    => null,
        ])->save();

        Log::info('bc.attente.ouverte', [
            'reservation_id' => $reservation->id,
            'reference'      => $reservation->reference,
            'jusqu_au'       => $reservation->bc_attendu_jusqu_au,
        ]);

        return $reservation;
    }

    // ══════════════════════════════════════════════════════════════
    // 2 — LIRE L'ÉTAT
    // ══════════════════════════════════════════════════════════════

    /** Le dossier attend-il encore son BC ? */
    public function estEnAttente(Reservation $reservation): bool
    {
        return $reservation->bc_attendu_jusqu_au !== null
            && $reservation->bc_recu_at === null;
    }

    /** Le BC est-il arrivé ? */
    public function estRecu(Reservation $reservation): bool
    {
        return $reservation->bc_recu_at !== null;
    }

    /**
     * Jours restants avant expiration. Négatif = déjà dépassé.
     * null = aucune fenêtre ouverte.
     */
    public function joursRestants(Reservation $reservation): ?int
    {
        if (!$reservation->bc_attendu_jusqu_au) return null;

        return (int) Carbon::today()->diffInDays(
            Carbon::parse($reservation->bc_attendu_jusqu_au)->startOfDay(),
            false
        );
    }

    /**
     * La campagne peut-elle être créée ? Uniquement si le BC est reçu.
     * C'est le verrou central de toute la règle.
     */
    public function peutCreerCampagne(Reservation $reservation): bool
    {
        // Jamais passé par le nouveau flux (dossier ancien) : on ne bloque
        // pas, sinon l'historique deviendrait ingérable.
        if (!$reservation->bc_attendu_jusqu_au) return true;

        return $this->estRecu($reservation);
    }

    /** Message d'explication quand la création est refusée. */
    public function motifBlocage(Reservation $reservation): ?string
    {
        if ($this->peutCreerCampagne($reservation)) return null;

        $jours = $this->joursRestants($reservation);
        $limite = Carbon::parse($reservation->bc_attendu_jusqu_au)->format('d/m/Y');

        return $jours !== null && $jours < 0
            ? "Le délai de réception du bon de commande est dépassé depuis le {$limite}."
            : "Bon de commande non reçu — la campagne ne peut pas être créée. "
              . "Échéance : {$limite}" . ($jours !== null ? " ({$jours} j restants)." : '.');
    }

    // ══════════════════════════════════════════════════════════════
    // 3 — ENREGISTRER LA RÉCEPTION
    // ══════════════════════════════════════════════════════════════

    /**
     * Le commercial confirme avoir reçu le BC. Verbalement suffit : la
     * référence est un champ LIBRE et FACULTATIF (numéro de BC, « reçu par
     * mail le 12/11 », etc.).
     *
     * Ne crée pas la campagne : c'est le MP qui s'en charge ensuite, pour
     * la nommer et la configurer.
     */
    public function enregistrerReception(
        Reservation $reservation,
        ?string $reference = null,
        ?User $par = null
    ): Reservation {
        if ($this->estRecu($reservation)) {
            return $reservation;
        }

        $reservation->forceFill([
            'bc_recu_at'   => now(),
            'bc_recu_par'  => $par?->id ?? auth()->id(),
            'bc_reference' => $reference !== null && trim($reference) !== ''
                ? mb_substr(trim($reference), 0, 190)
                : null,
        ])->save();

        Log::info('bc.recu', [
            'reservation_id' => $reservation->id,
            'reference'      => $reservation->reference,
            'bc_reference'   => $reservation->bc_reference,
            'par'            => $reservation->bc_recu_par,
        ]);

        return $reservation;
    }

    /** Annuler une réception saisie par erreur (admin). */
    public function annulerReception(Reservation $reservation): Reservation
    {
        $reservation->forceFill([
            'bc_recu_at'   => null,
            'bc_recu_par'  => null,
            'bc_reference' => null,
        ])->save();

        Log::warning('bc.reception.annulee', [
            'reservation_id' => $reservation->id,
            'par'            => auth()->id(),
        ]);

        return $reservation;
    }

    // ══════════════════════════════════════════════════════════════
    // 4 — DOSSIERS CONCURRENTS
    // ══════════════════════════════════════════════════════════════

    /**
     * Les AUTRES dossiers qui attendent un BC sur au moins un des mêmes
     * panneaux, sur une période qui se chevauche.
     *
     * Conséquence assumée du nouveau flux (validée le 2026-11-13) : comme
     * l'acceptation du client ne bloque plus fermement, deux clients
     * peuvent attendre un BC sur le même panneau. Le premier BC gagne.
     * Les écrans DOIVENT l'afficher — sinon on vend deux fois sans le
     * savoir.
     *
     * @return Collection<int, object> {id, reference, client, jusqu_au, panneaux}
     */
    public function dossiersConcurrents(Reservation $reservation): Collection
    {
        $panelIds = $reservation->relationLoaded('panels')
            ? $reservation->panels->pluck('id')->all()
            : $reservation->panels()->pluck('panels.id')->all();

        if (empty($panelIds)) return collect();

        $debut = Carbon::parse($reservation->start_date)->toDateString();
        $fin   = Carbon::parse($reservation->end_date)->toDateString();

        return collect(DB::table('reservations as r')
            ->join('reservation_panels as rp', 'rp.reservation_id', '=', 'r.id')
            ->leftJoin('clients as c', 'c.id', '=', 'r.client_id')
            ->whereIn('rp.panel_id', $panelIds)
            ->where('r.id', '!=', $reservation->id)
            ->whereNull('r.deleted_at')
            ->whereNotNull('r.bc_attendu_jusqu_au')
            ->whereNull('r.bc_recu_at')
            ->where('r.status', 'en_attente')
            // Chevauchement inclusif, comme AvailabilityService.
            ->where('r.start_date', '<=', $fin)
            ->where('r.end_date',   '>=', $debut)
            ->groupBy('r.id', 'r.reference', 'c.name', 'r.bc_attendu_jusqu_au')
            ->selectRaw('r.id, r.reference, c.name as client, r.bc_attendu_jusqu_au as jusqu_au, COUNT(DISTINCT rp.panel_id) as panneaux')
            ->orderBy('r.bc_attendu_jusqu_au')
            ->get());
    }

    /** Phrase d'avertissement prête à afficher, ou null s'il n'y a personne. */
    public function messageConcurrence(Reservation $reservation): ?string
    {
        $autres = $this->dossiersConcurrents($reservation);
        if ($autres->isEmpty()) return null;

        $n = $autres->count() + 1;

        return "⚠ {$n} dossiers attendent un bon de commande sur ces panneaux. "
             . "Le premier BC reçu emporte les panneaux : "
             . $autres->map(fn ($d) => $d->reference . ' (' . ($d->client ?: 'client ?') . ')')->join(', ') . '.';
    }

    // ══════════════════════════════════════════════════════════════
    // BALAYAGES (commandes planifiées)
    // ══════════════════════════════════════════════════════════════

    /** Dossiers en attente de BC dont l'échéance tombe dans N jours. */
    public function aRelancer(int $joursRestants): Collection
    {
        $cible = Carbon::today()->addDays($joursRestants)->toDateString();
        $colonne = self::colonneRelance($joursRestants);

        return Reservation::query()
            ->with(['client', 'commercial'])
            ->whereNull('deleted_at')
            ->whereNotNull('bc_attendu_jusqu_au')
            ->whereNull('bc_recu_at')
            ->where('status', 'en_attente')
            ->whereDate('bc_attendu_jusqu_au', $cible)
            ->when($colonne, fn ($q) => $q->whereNull($colonne))
            ->get();
    }

    /** Dossiers dont la fenêtre BC est dépassée : à libérer. */
    public function aExpirer(): Collection
    {
        return Reservation::query()
            ->with(['panels', 'externalPanels'])
            ->whereNull('deleted_at')
            ->whereNotNull('bc_attendu_jusqu_au')
            ->whereNull('bc_recu_at')
            ->where('status', 'en_attente')
            ->whereDate('bc_attendu_jusqu_au', '<', Carbon::today()->toDateString())
            ->get();
    }

    /** Marque une relance comme partie, pour ne pas la renvoyer demain. */
    public function noterRelance(Reservation $reservation, int $joursRestants): void
    {
        if ($colonne = self::colonneRelance($joursRestants)) {
            $reservation->forceFill([$colonne => now()])->save();
        }
    }
}
