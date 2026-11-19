<?php
// ══════════════════════════════════════════════════════════════════
// app/Services/PropositionService.php
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropositionService
{
    // ── VALIDER LE TOKEN ────────────────────────────────────────────

    /**
     * Valide un token de proposition.
     * Lance une RuntimeException si expiré ou déjà traité.
     */
    public function validerToken(string $token): Reservation
    {
        $reservation = Reservation::with(['client', 'panels.photos', 'panels.commune', 'panels.format', 'panels.category'])
            ->where('proposition_token', $token)
            ->firstOrFail();

        // Vérifier expiration (end_date dépassée = proposition caduque)
        if ($reservation->end_date < now()->startOfDay()) {
            throw new \RuntimeException('Cette proposition a expiré.');
        }

        // Vérifier statut
        if (in_array($reservation->status->value, ['confirme', 'annule', 'refuse'])) {
            throw new \RuntimeException("Cette proposition a déjà été {$reservation->status->value}.");
        }

        return $reservation;
    }

    // ── MARQUER VUE ─────────────────────────────────────────────────

    public function marquerVue(Reservation $reservation): void
    {
        if (!$reservation->proposition_viewed_at) {
            $reservation->update(['proposition_viewed_at' => now()]);
        }
    }

    // ── CONFIRMER ───────────────────────────────────────────────────

    /**
     * Confirme une proposition → crée une campagne si pas encore créée.
     * Retourne la Campaign créée ou existante.
     */
    /**
     * ═══ LE CLIENT ACCEPTE — 2026-11-13, règle validée par écrit ═══
     *
     * Avant, le clic du client appelait directement confirmer(), qui
     * bloquait fermement les panneaux, annulait les options concurrentes
     * ET CRÉAIT LA CAMPAGNE. Or le client n'a pas forcément envoyé son bon
     * de commande, et « sans BC, pas de campagne ».
     *
     * Désormais l'acceptation ne fait qu'ouvrir la fenêtre BC :
     *   - la réservation RESTE en option (status en_attente, type option) ;
     *   - les panneaux ne sont PAS bloqués fermement ;
     *   - les options concurrentes ne sont PAS annulées — plusieurs
     *     dossiers peuvent attendre un BC sur le même panneau, le premier
     *     BC gagne (conséquence assumée, les écrans l'affichent) ;
     *   - aucune campagne n'est créée.
     *
     * Le commercial enregistrera la réception du BC, puis le MP créera la
     * campagne via confirmer().
     *
     * On vérifie quand même qu'aucun panneau n'a été pris FERMEMENT
     * entre-temps : accepter sur un panneau déjà vendu n'aurait aucun sens.
     */
    public function accepterClient(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            $availability = app(AvailabilityService::class);

            $panelIds = $reservation->panels->pluck('id')->toArray();
            $start    = Carbon::parse($reservation->start_date)->toDateString();
            $end      = Carbon::parse($reservation->end_date)->toDateString();

            if (!empty($panelIds)) {
                \App\Models\Panel::whereIn('id', $panelIds)->lockForUpdate()->get();
                // Bloque uniquement sur du FERME (résa confirmée, campagne
                // active) — pas sur les autres options, qui coexistent.
                $this->assertPanneauxLibresFermement($reservation, $availability, $start, $end);
            }

            app(\App\Services\BonDeCommandeService::class)->ouvrirAttente($reservation);

            // Les panneaux restent en « option » : on resynchronise leur
            // cache de lecture sans changer la nature du blocage.
            if (!empty($panelIds)) {
                $availability->syncPanelStatuses($panelIds);
            }

            return $reservation->refresh();
        });
    }

    /**
     * Crée la campagne d'une réservation (bouton « Créer la campagne » du
     * MP, et confirmation d'une proposition par l'admin).
     *
     * 2026-11-13 — N'est plus appelée par le clic du client : elle
     * intervient APRÈS réception du bon de commande. Le verrou est posé ici,
     * pas chez les appelants : aucun chemin ne doit pouvoir créer une
     * campagne sans BC.
     *
     * 2026-11-19 — Coupée en deux (règles validées par écrit) : la
     * CONFIRMATION (blocage ferme, dossiers concurrents annulés et
     * prévenus) a lieu dès la réception du BC — confirmerReservation(),
     * appelée par BonDeCommandeService. Il reste ici la création de la
     * campagne ; une réservation pas encore confirmée (dossier antérieur à
     * la règle) l'est au passage.
     */
    public function confirmer(Reservation $reservation, ?string $nomCampagne = null): ?Campaign
    {
        $bc = app(\App\Services\BonDeCommandeService::class);
        if (!$bc->peutCreerCampagne($reservation)) {
            throw new \RuntimeException('BC_MANQUANT:' . $bc->motifBlocage($reservation));
        }

        if ($reservation->status !== ReservationStatus::CONFIRME) {
            $this->confirmerReservation($reservation);
        }

        return $this->creerCampagne($reservation, $nomCampagne);
    }

    /**
     * Confirme une réservation : panneaux bloqués fermement, dossiers
     * concurrents (options en attente sur les mêmes panneaux et la même
     * période) annulés avec leur motif, leur commercial prévenu.
     *
     * SOURCE UNIQUE de la confirmation (2026-11-19) : BC reçu, « Confirmer
     * sans BC » de l'admin (motif obligatoire, contrôlé par l'appelant),
     * confirmation groupée, création de campagne d'un dossier ancien.
     *
     * @return Collection<int, Reservation> les dossiers concurrents annulés
     * @throws \App\Exceptions\BookingConflictException|\RuntimeException si un panneau est déjà pris ferme ailleurs
     */
    public function confirmerReservation(Reservation $reservation, ?int $parUserId = null, ?string $motifSansBc = null): Collection
    {
        $parUserId ??= auth()->id();
        $annules = collect();

        DB::transaction(function () use ($reservation, $parUserId, $motifSansBc, &$annules) {

            $availability = app(AvailabilityService::class);
            $reservation->loadMissing(['panels', 'externalPanels', 'client']);

            $panelIds    = $reservation->panels->pluck('id')->toArray();
            $extIds      = $reservation->externalPanels->pluck('id')->toArray();
            $start       = Carbon::parse($reservation->start_date)->toDateString();
            $end         = Carbon::parse($reservation->end_date)->toDateString();

            // On bloque si un autre dossier tient déjà le panneau FERMEMENT :
            // réservation CONFIRMÉE, ou campagne planifiée / active / en pause
            // (2026-10-30 : les campagnes n'étaient pas vérifiées — une campagne
            // importée par PDF, sans réservation, laissait passer). Pas les
            // options EN_ATTENTE : elles tombent ci-dessous, le premier
            // confirmé gagne ; la lockForUpdate garantit l'atomicité.
            // Overlap INCLUSIF (<= >=), cf. AvailabilityService::conflitsFermes().
            if (!empty($panelIds)) {
                \App\Models\Panel::whereIn('id', $panelIds)->lockForUpdate()->get();
                $this->assertPanneauxLibresFermement($reservation, $availability, $start, $end);
            }

            if (!empty($extIds)) {
                \App\Models\ExternalPanel::whereIn('id', $extIds)->lockForUpdate()->get();
                $extMap = $availability->getExternalPanelBookingMap($extIds, $start, $end, $reservation->id);
                $conflictIds = $extMap->filter(fn($b) => $b->has_confirmed)->keys()->toArray();
                if (!empty($conflictIds)) {
                    $refs = \App\Models\ExternalPanel::whereIn('id', $conflictIds)->pluck('reference')->join(', ');
                    throw new \RuntimeException("Ce panneau est déjà confirmé pour cette période : {$refs}.");
                }
            }

            // Statut → confirmé, type option → ferme, et qui a confirmé.
            $motif = $motifSansBc !== null && trim($motifSansBc) !== ''
                ? mb_substr(trim($motifSansBc), 0, 500)
                : null;
            $reservation->update([
                'status'                 => ReservationStatus::CONFIRME,
                'type'                   => 'ferme',
                'confirmed_at'           => now(),
                'confirme_par'           => $parUserId,
                'confirme_sans_bc_motif' => $motif,
            ]);

            if (!empty($panelIds)) $availability->syncPanelStatuses($panelIds);
            if (!empty($extIds))   $availability->syncExternalPanelStatuses($extIds);

            $annules = $this->annulerConcurrents($reservation, $panelIds, $extIds, $start, $end, $parUserId);

            Log::info('reservation.confirmee', [
                'reservation_id' => $reservation->id,
                'par'            => $parUserId,
                'sans_bc'        => $motif !== null,
                'concurrents'    => $annules->pluck('reference')->all(),
            ]);
        });

        // Une fois la transaction validée seulement : prévenir le commercial
        // de chaque dossier tombé (règle 3 du 19/11).
        foreach ($annules as $autre) {
            $this->prevenirConcurrentAnnule($autre, $reservation);
        }

        return $annules;
    }

    /**
     * Les panneaux de la réservation sont-ils libres FERMEMENT ? (2026-11-19)
     *
     * Source unique pour l'acceptation du client et la confirmation (BC reçu,
     * « Ferme — BC reçu », « Confirmer sans BC »). Un panneau à démarrage
     * différé (panel_start_date) n'est vérifié qu'à partir de son propre
     * début : sinon la campagne qui le libère juste avant ferait échouer
     * la confirmation. Son propre renouvellement confirmé ne bloque pas le
     * client (2026-11-11).
     *
     * @throws \App\Exceptions\BookingConflictException
     */
    private function assertPanneauxLibresFermement(Reservation $reservation, AvailabilityService $availability, string $start, string $end): void
    {
        $parDebut = $reservation->panels->groupBy(function ($p) use ($start) {
            $debut = $p->pivot?->panel_start_date
                ? Carbon::parse($p->pivot->panel_start_date)->toDateString()
                : $start;
            return max($debut, $start);
        });

        $campagneId = $reservation->campaign()->value('id');
        foreach ($parDebut as $debut => $groupe) {
            $availability->assertLibresFermement(
                $groupe->pluck('id')->all(), (string) $debut, $end,
                $reservation->id, $campagneId, (int) $reservation->client_id
            );
        }
    }

    /**
     * Annule les options EN_ATTENTE qui chevauchent les mêmes panneaux sur
     * la même période. Avant (2026-11-19) : mise à jour en masse, sans
     * motif, sans date, sans auteur, sans prévenir personne. Maintenant :
     * chaque dossier garde la raison de sa chute.
     *
     * @return Collection<int, Reservation>
     */
    private function annulerConcurrents(Reservation $reservation, array $panelIds, array $extIds, string $start, string $end, ?int $parUserId): Collection
    {
        $availability = app(AvailabilityService::class);
        $competingIds = collect();

        if (!empty($panelIds)) {
            $competingIds = $competingIds->merge(DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->whereIn('reservation_panels.panel_id', $panelIds)
                ->where('reservations.status', ReservationStatus::EN_ATTENTE->value)
                ->where('reservations.id', '!=', $reservation->id)
                ->whereNull('reservations.deleted_at')
                ->where('reservations.start_date', '<=', $end)
                ->where('reservations.end_date',   '>=', $start)
                ->distinct()
                ->pluck('reservations.id'));
        }
        if (!empty($extIds)) {
            $competingIds = $competingIds->merge(DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->whereIn('reservation_panels.external_panel_id', $extIds)
                ->where('reservation_panels.source', 'externe')
                ->where('reservations.status', ReservationStatus::EN_ATTENTE->value)
                ->where('reservations.id', '!=', $reservation->id)
                ->whereNull('reservations.deleted_at')
                ->where('reservations.start_date', '<=', $end)
                ->where('reservations.end_date',   '>=', $start)
                ->distinct()
                ->pluck('reservations.id'));
        }
        if ($competingIds->isEmpty()) {
            return collect();
        }

        $ids    = $competingIds->unique()->values()->all();
        $autres = Reservation::with(['client:id,name', 'commercial:id,name,email', 'user:id,name,email'])
            ->whereIn('id', $ids)->get();

        $motif = sprintf(
            'Panneaux pris par %s (%s), confirmée le %s.',
            $reservation->reference,
            $reservation->client?->name ?? 'autre client',
            now()->format('d/m/Y')
        );

        foreach ($autres as $autre) {
            // update() par modèle (et non en masse) : l'observateur libère
            // les panneaux et trace l'annulation, comme une annulation manuelle.
            $autre->update([
                'status'        => ReservationStatus::ANNULE->value,
                'cancel_type'   => 'panneaux_pris',
                'cancel_reason' => $motif,
                'cancelled_at'  => now(),
                'cancelled_by'  => $parUserId,
            ]);
        }

        // Les panneaux des dossiers tombés qui ne font pas partie de notre
        // confirmation (sinon ils restent en cache avec le statut « option »).
        $extraInternalIds = DB::table('reservation_panels')
            ->whereIn('reservation_id', $ids)->where('source', 'interne')->whereNotNull('panel_id')
            ->when(!empty($panelIds), fn($q) => $q->whereNotIn('panel_id', $panelIds))
            ->distinct()->pluck('panel_id')->toArray();
        $extraExternalIds = DB::table('reservation_panels')
            ->whereIn('reservation_id', $ids)->where('source', 'externe')->whereNotNull('external_panel_id')
            ->when(!empty($extIds), fn($q) => $q->whereNotIn('external_panel_id', $extIds))
            ->distinct()->pluck('external_panel_id')->toArray();

        if (!empty($extraInternalIds)) $availability->syncPanelStatuses($extraInternalIds);
        if (!empty($extraExternalIds)) $availability->syncExternalPanelStatuses($extraExternalIds);

        return $autres;
    }

    /** Alerte + mail au commercial d'un dossier tombé (règle 3 du 2026-11-19). */
    private function prevenirConcurrentAnnule(Reservation $autre, Reservation $gagnant): void
    {
        $destinataire = $autre->commercial ?? $autre->user;
        $titre = '🚫 Réservation annulée : panneaux pris — ' . $autre->reference;
        $corps = sprintf(
            "La réservation %s de %s est annulée : ses panneaux ont été confirmés pour %s (%s).\n"
            . "Motif enregistré : %s\nProposez d'autres emplacements au client depuis les disponibilités.",
            $autre->reference,
            $autre->client?->name ?? 'ce client',
            $gagnant->reference,
            $gagnant->client?->name ?? 'un autre client',
            $autre->cancel_reason
        );

        try {
            AlertService::notify('reservation_annulee_concurrence', $titre, $corps, $autre, [
                'niveau'  => 'danger',
                'user_id' => $destinataire?->id,
                'lien'    => route('admin.reservations.show', $autre->id),
            ]);
        } catch (\Throwable $e) {
            Log::error('reservation.concurrent.alerte', ['resa' => $autre->id, 'erreur' => $e->getMessage()]);
        }

        if ($destinataire?->email) {
            app(NotificationMailer::class)->sendSilently(
                $destinataire->email,
                new \App\Mail\AdminAlertMail(
                    severity: 'danger',
                    title:    'Réservation annulée : panneaux pris — ' . $autre->reference,
                    summary:  $corps,
                    ctaLabel: 'Ouvrir la réservation',
                    ctaUrl:   route('admin.reservations.show', $autre->id),
                    emoji:    '🚫',
                ),
                context: ['reservation_id' => $autre->id, 'gagnant_id' => $gagnant->id],
            );
        }
    }

    /**
     * Crée la campagne d'une réservation CONFIRMÉE (2026-11-19 : le nom est
     * demandé au MP, prérempli avec le nom prévu ; à défaut « Campagne RES-… »).
     */
    public function creerCampagne(Reservation $reservation, ?string $nomCampagne = null): Campaign
    {
        $reservation->refresh()->loadMissing(['panels', 'externalPanels', 'campaign']);

        if ($reservation->status !== ReservationStatus::CONFIRME) {
            throw new \RuntimeException('NON_CONFIRMEE:La réservation doit être confirmée avant de créer sa campagne.');
        }
        if ($reservation->campaign) {
            return $reservation->campaign;
        }

        $nom = $nomCampagne !== null && trim($nomCampagne) !== ''
            ? mb_substr(trim($nomCampagne), 0, 190)
            : "Campagne {$reservation->reference}";
        $campaign = null;

        DB::transaction(function () use ($reservation, $nom, &$campaign) {
            $availability = app(AvailabilityService::class);
            $panelIds = $reservation->panels->pluck('id')->toArray();
            $extIds   = $reservation->externalPanels->pluck('id')->toArray();

            // Si la campagne commence dans le futur → PLANIFIE, sinon ACTIF.
            // Cohérent avec ReservationController::store + scheduler
            // campaigns:activate-planned qui basculera ensuite à ACTIF
            // au premier jour de la campagne.
            $campStatus = now()->startOfDay()->lt(
                Carbon::parse($reservation->start_date)->startOfDay()
            )
                ? CampaignStatus::PLANIFIE->value
                : CampaignStatus::ACTIF->value;

            $campaign = Campaign::create([
                'name'           => $nom,
                'client_id'      => $reservation->client_id,
                'client_contact_id' => $reservation->client_contact_id, // interlocuteur (2026-10-29)
                'reservation_id' => $reservation->id,
                'start_date'     => $reservation->start_date,
                'end_date'       => $reservation->end_date,
                'status'         => $campStatus,
                'total_panels'   => count($panelIds) + count($extIds),
                'total_amount'   => $reservation->total_amount,
                'user_id'        => $reservation->user_id,
            ]);

            if (!empty($panelIds)) {
                $campaign->panels()->sync($panelIds);
            }

            // Le pivot campaign_panels supporte external_panel_id avec
            // type='externe' — même schéma que ReservationController::store.
            if (!empty($extIds)) {
                $rows = array_map(fn($extId) => [
                    'campaign_id'       => $campaign->id,
                    'external_panel_id' => $extId,
                    'type'              => 'externe',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ], $extIds);
                DB::table('campaign_panels')->insert($rows);
            }

            // Lot 9.1 — Auto-création des tâches de pose APRÈS le sync
            // des panneaux (l'observer `created` est trop tôt, panneaux
            // pas encore liés à ce moment-là).
            $campaign->ensurePoseTasksAutoCreated();

            // Campagne créée directement en ACTIF : les panneaux passent à
            // OCCUPE tout de suite (sinon ils restaient à CONFIRME). Pour une
            // campagne PLANIFIE, le sync maintient CONFIRME.
            if (!empty($panelIds)) $availability->syncPanelStatuses($panelIds);
            if (!empty($extIds))   $availability->syncExternalPanelStatuses($extIds);

            Log::info('proposition.confirmed', [
                'reservation_id' => $reservation->id,
                'campaign_id'    => $campaign->id,
                'client_id'      => $reservation->client_id,
                'panels'         => count($panelIds),
                'externals'      => count($extIds),
            ]);
        });

        return $campaign;
    }

    // ── REFUSER ─────────────────────────────────────────────────────

    /**
     * Refus client d'une proposition.
     *
     * @param  string|null  $reasonCode  Code prédéfini (cf. Reservation::REFUS_REASONS).
     *                                   Permet des stats fiables sans parser le texte libre.
     * @param  string|null  $motif       Texte libre éventuel saisi par le client.
     */
    public function refuser(Reservation $reservation, ?string $motif = null, ?string $reasonCode = null): void
    {
        // Ne persiste un code que s'il est dans la liste blanche.
        $validCode = $reasonCode && array_key_exists($reasonCode, Reservation::REFUS_REASONS)
            ? $reasonCode : null;

        // Construit la note historique avec le label du motif + le texte libre.
        $reasonLabel = $validCode ? Reservation::REFUS_REASONS[$validCode] : null;
        $noteLine = trim(($reasonLabel ? $reasonLabel : '') . ($motif ? ' — ' . $motif : ''));
        $newNotes = $noteLine !== ''
            ? ($reservation->notes ? $reservation->notes . "\n\nRefus client : " . $noteLine : "Refus client : " . $noteLine)
            : $reservation->notes;

        $reservation->update([
            'status'            => ReservationStatus::REFUSE,
            'refus_reason_code' => $validCode,
            'notes'             => $newNotes,
        ]);

        // Libérer les panneaux (internes + externes) — le statut de la
        // réservation passé à ANNULE n'est plus bloquant, mais Panel.status
        // et ExternalPanel.availability_status doivent être resynchronisés
        // pour refléter la libération immédiatement dans les UI.
        $availability = app(AvailabilityService::class);

        $panelIds = $reservation->panels->pluck('id')->toArray();
        if (!empty($panelIds)) {
            $availability->syncPanelStatuses($panelIds);
        }

        $externalIds = $reservation->externalPanels->pluck('id')->toArray();
        if (!empty($externalIds)) {
            $availability->syncExternalPanelStatuses($externalIds);
        }

        Log::info('proposition.refused', [
            'reservation_id' => $reservation->id,
            'reason_code'    => $validCode,
            'motif'          => $motif,
            'client_id'      => $reservation->client_id,
            'panels_freed'   => count($panelIds),
            'externals_freed'=> count($externalIds),
        ]);
    }

    // ── EXPIRER EN BATCH ────────────────────────────────────────────

    /**
     * Expire les propositions dont la date de fin est dépassée.
     * Appelé par un Job/Scheduler.
     */
    public function expireEnBatch(): int
    {
        $expired = Reservation::where('status', ReservationStatus::EN_ATTENTE->value)
            ->whereNotNull('proposition_token')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        $availability = app(AvailabilityService::class);
        $count = 0;

        foreach ($expired as $r) {
            $r->update(['status' => ReservationStatus::ANNULE->value]);

            $panelIds = $r->panels->pluck('id')->toArray();
            if (!empty($panelIds)) {
                $availability->syncPanelStatuses($panelIds);
            }

            $externalIds = $r->externalPanels->pluck('id')->toArray();
            if (!empty($externalIds)) {
                $availability->syncExternalPanelStatuses($externalIds);
            }

            $count++;
        }

        return $count;
    }
}