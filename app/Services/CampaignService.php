<?php
namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\PoseTaskStatus;
use App\Enums\ReservationStatus;
use App\Mail\CampaignStartedMail;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\PoseTask;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    /** Statuts permettant la modification du panel (ajout/retrait).
     *  'termine' inclus pour permettre la correction de l'historique
     *  (saisie d'anciennes campagnes importées dont les dates sont passées). */
    private const MODIFIABLE_STATUSES = ['planifie', 'actif', 'termine'];

    public function __construct(
        protected AvailabilityService $availability,
        protected NotificationMailer  $mailer,
    ) {}

    // ══════════════════════════════════════════════════════════════
    // AJOUTER DES PANNEAUX
    // ══════════════════════════════════════════════════════════════
    /**
     * Ajoute un ou plusieurs panneaux à une campagne.
     *
     * @param Campaign $campaign
     * @param array<int> $panelIds
     * @param array<int,float>|null $unitPrices Override de prix par panel_id
     *        (négociation commerciale). Si null → utilise panel.monthly_rate.
     */
    public function addPanels(Campaign $campaign, array $panelIds, ?array $unitPrices = null): array
    {
        if (!in_array($campaign->status->value, self::MODIFIABLE_STATUSES)) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        $existingIds = $campaign->panels()->pluck('panels.id')->all();
        $alreadyIn   = array_intersect($existingIds, $panelIds);

        if (!empty($alreadyIn)) {
            $refs = Panel::whereIn('id', $alreadyIn)->pluck('reference')->join(', ');
            return ['ok' => false, 'error' => "Ces panneaux sont déjà dans la campagne : {$refs}"];
        }

        return DB::transaction(function () use ($campaign, $panelIds, $unitPrices) {
            // Verrou pessimiste sur les panels
            Panel::whereIn('id', $panelIds)->lockForUpdate()->get();

            // Anti-double-booking : on saute ce contrôle pour les campagnes
            // TERMINÉES — la saisie d'historique enregistre la réalité passée,
            // le verrou de dispo (qui protège les réservations actives) n'a
            // pas de sens sur une période révolue.
            // ⚠️ Garde-fou : tout retour de TERMINE vers un statut actif
            // re-vérifie les conflits (cf. detectConflictsOnCurrentPanels +
            // activate()) — donc on ne peut pas exploiter cette dérogation
            // pour glisser un panneau qui sera ensuite "réveillé".
            if ($campaign->status->value !== 'termine') {
                $conflicts = $this->availability->getUnavailablePanelIds(
                    $panelIds,
                    $campaign->start_date->format('Y-m-d'),
                    $campaign->end_date->format('Y-m-d'),
                    $campaign->reservation_id,
                    $campaign->id   // s'exclure soi-même (panneaux déjà attachés)
                );

                if (!empty($conflicts)) {
                    $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                    return ['ok' => false, 'error' => "Panneaux non disponibles : {$refs}"];
                }
            } else {
                Log::warning('campaign.addPanels.termine_bypass', [
                    'campaign_id' => $campaign->id,
                    'panel_ids'   => $panelIds,
                    'user_id'     => auth()->id(),
                ]);
            }

            $months = $campaign->billableMonths();

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation;
                $attach      = $this->buildAttach($panelIds, $months, $unitPrices);
                $reservation->panels()->syncWithoutDetaching($attach);
                $this->recalculateReservationAmount($reservation);
            } else {
                $this->createTechnicalReservation($campaign, $panelIds, $months, $unitPrices);
            }

            $campaign->panels()->syncWithoutDetaching($panelIds);
            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncPanelStatuses($panelIds);

            // FIX critique : créer les PoseTasks pour les panneaux ajoutés
            // (sans cela, le tech terrain n'est pas convoqué pour les
            // nouveaux panneaux d'une campagne déjà créée).
            $campaign->refresh();
            $posesCreated = $campaign->ensurePoseTasksAutoCreated();

            Log::info('campaign.panels_added', [
                'campaign_id'  => $campaign->id,
                'panel_ids'    => $panelIds,
                'count'        => count($panelIds),
                'poses_created'=> $posesCreated,
                'custom_prices'=> $unitPrices ? array_keys($unitPrices) : [],
                'user_id'      => auth()->id(),
            ]);

            return ['ok' => true, 'added' => count($panelIds), 'poses_created' => $posesCreated];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // AJOUTER DES PANNEAUX EXTERNES (régies partenaires)
    //
    // Logique parallèle à addPanels() mais pour les ExternalPanel :
    //   - Verrou pessimiste sur chaque panneau externe
    //   - Re-check anti-double-booking via AvailabilityService
    //   - Insertion dans campaign_panels (external_panel_id, type='externe')
    //   - Si reservation_id (résa réelle ou technique) : mirroring dans
    //     reservation_panels (external_panel_id, source='externe') pour
    //     que les calculs CA + le détail de facturation restent cohérents
    //   - Si pas de reservation_id : création d'une résa technique d'abord
    //   - Sync availability_status externe à la fin
    //
    // @param array<int> $externalPanelIds  IDs ExternalPanel à attacher
    // @param array<int,float>|null $unitPrices  Prix négocié par id externe
    // ══════════════════════════════════════════════════════════════
    public function addExternalPanels(Campaign $campaign, array $externalPanelIds, ?array $unitPrices = null): array
    {
        if (!in_array($campaign->status->value, self::MODIFIABLE_STATUSES)) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        $existingExtIds = $campaign->externalPanels()->pluck('external_panels.id')->all();
        $alreadyIn      = array_intersect($existingExtIds, $externalPanelIds);
        if (!empty($alreadyIn)) {
            $refs = \App\Models\ExternalPanel::whereIn('id', $alreadyIn)->pluck('code_panneau')->join(', ');
            return ['ok' => false, 'error' => "Ces panneaux externes sont déjà dans la campagne : {$refs}"];
        }

        return DB::transaction(function () use ($campaign, $externalPanelIds, $unitPrices) {
            // Verrou + re-check disponibilité pour TOUS les externes en lot.
            $locked = \App\Models\ExternalPanel::whereIn('id', $externalPanelIds)
                ->lockForUpdate()
                ->get(['id', 'code_panneau', 'monthly_rate']);

            $startDate = $campaign->start_date->format('Y-m-d');
            $endDate   = $campaign->end_date->format('Y-m-d');

            $conflicts = $this->availability->getExternalPanelBookingMap(
                $externalPanelIds,
                $startDate,
                $endDate,
                $campaign->reservation_id
            );

            // Retourne erreur claire si au moins un externe est en conflit
            // confirmé — sauf campagne TERMINÉE (saisie d'historique : on
            // enregistre la réalité passée, pas de blocage anti-double-booking).
            if ($campaign->status->value !== 'termine') {
                $blocked = [];
                foreach ($externalPanelIds as $eid) {
                    $b = $conflicts->get($eid);
                    if ($b && !empty($b->has_confirmed)) {
                        $ref = $locked->firstWhere('id', $eid)?->code_panneau ?? "#{$eid}";
                        $blocked[] = $ref;
                    }
                }
                if (!empty($blocked)) {
                    return ['ok' => false, 'error' => 'Panneaux externes en conflit : ' . implode(', ', $blocked)];
                }
            }

            $months = $campaign->billableMonths();

            // S'assure qu'il y a une reservation_id (technique si nécessaire).
            // Pour les externes on a besoin du pivot reservation_panels pour
            // stocker le prix négocié — cohérence avec les internes.
            $this->ensureTechnicalReservation($campaign, $months);

            // Refresh : reservation_id peut venir d'être créé
            $campaign->refresh();
            $reservation = $campaign->reservation;

            // Attach dans reservation_panels (source='externe')
            $now = now();
            $rows = [];
            foreach ($locked as $ext) {
                $unit = $unitPrices[$ext->id] ?? (float) ($ext->monthly_rate ?? 0);
                $rows[] = [
                    'reservation_id'    => $reservation->id,
                    'external_panel_id' => $ext->id,
                    'source'            => 'externe',
                    'unit_price'        => $unit,
                    'total_price'       => round($unit * $months, 2),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
            DB::table('reservation_panels')->insert($rows);

            // Attach dans campaign_panels (type='externe')
            $campRows = array_map(fn($id) => [
                'campaign_id'       => $campaign->id,
                'external_panel_id' => $id,
                'type'              => 'externe',
                'created_at'        => $now,
                'updated_at'        => $now,
            ], $externalPanelIds);
            DB::table('campaign_panels')->insert($campRows);

            // Recalcul du total réservation + campagne
            $this->recalculateReservationAmount($reservation);
            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncExternalPanelStatuses($externalPanelIds);

            Log::info('campaign.external_panels_added', [
                'campaign_id'   => $campaign->id,
                'external_ids'  => $externalPanelIds,
                'count'         => count($externalPanelIds),
                'custom_prices' => $unitPrices ? array_keys($unitPrices) : [],
                'user_id'       => auth()->id(),
            ]);

            return ['ok' => true, 'added' => count($externalPanelIds)];
        });
    }

    /**
     * Garantit qu'une campagne a une `reservation_id` (réelle ou technique).
     * Utilisé par addExternalPanels quand la campagne est créée en direct
     * et n'a pas encore eu d'ajout interne (et donc pas de résa technique).
     */
    private function ensureTechnicalReservation(Campaign $campaign, float $months): void
    {
        if ($campaign->reservation_id) return;

        $marker = '[Auto] Réservation technique — campagne #' . $campaign->id;

        $reservation = Reservation::create([
            'reference'    => $this->generateTechReference($campaign->id),
            'client_id'    => $campaign->client_id,
            'user_id'      => auth()->id(),
            'start_date'   => $campaign->start_date->format('Y-m-d'),
            'end_date'     => $campaign->end_date->format('Y-m-d'),
            'status'       => ReservationStatus::CONFIRME->value,
            'type'         => 'ferme',
            'total_amount' => 0,
            'confirmed_at' => now(),
            'is_technical' => true,
            'notes'        => $marker,
        ]);

        $campaign->update(['reservation_id' => $reservation->id]);

        Log::info('reservation.technical_created.external_only', [
            'reservation_id' => $reservation->id,
            'campaign_id'    => $campaign->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // RETIRER UN PANNEAU EXTERNE
    //
    // Détache un ExternalPanel d'une campagne (et de la résa pivot
    // correspondante). Même garde qu'un retrait interne : on refuse si
    // c'est le dernier panneau (toutes origines confondues).
    // ══════════════════════════════════════════════════════════════
    public function removeExternalPanel(Campaign $campaign, \App\Models\ExternalPanel $externalPanel): array
    {
        if (!in_array($campaign->status->value, self::MODIFIABLE_STATUSES)) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        $internalCount = (int) $campaign->panels()->count();
        $externalCount = (int) $campaign->externalPanels()->count();
        if (($internalCount + $externalCount) <= 1) {
            return [
                'ok'    => false,
                'error' => "Impossible de retirer le dernier panneau. Une campagne doit avoir au moins 1 panneau — ajoutez-en un autre avant de retirer celui-ci, ou annulez la campagne.",
            ];
        }

        return DB::transaction(function () use ($campaign, $externalPanel) {
            // Détache du pivot campaign_panels
            DB::table('campaign_panels')
                ->where('campaign_id', $campaign->id)
                ->where('external_panel_id', $externalPanel->id)
                ->where('type', 'externe')
                ->delete();

            // Détache du pivot reservation_panels (résa réelle ou technique)
            if ($campaign->reservation_id) {
                DB::table('reservation_panels')
                    ->where('reservation_id', $campaign->reservation_id)
                    ->where('external_panel_id', $externalPanel->id)
                    ->where('source', 'externe')
                    ->delete();

                $reservation = $campaign->reservation()->first();
                if ($reservation) {
                    $this->recalculateReservationAmount($reservation);
                }
            }

            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncExternalPanelStatuses([$externalPanel->id]);

            Log::info('campaign.external_panel_removed', [
                'campaign_id'  => $campaign->id,
                'external_id'  => $externalPanel->id,
                'external_ref' => $externalPanel->code_panneau,
                'user_id'      => auth()->id(),
            ]);

            return ['ok' => true];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // RETIRER UN PANNEAU
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel): array
    {
        if (!in_array($campaign->status->value, self::MODIFIABLE_STATUSES)) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        // Garde : on REFUSE de retirer le dernier panneau d'une campagne.
        // Une campagne doit toujours avoir au moins 1 panneau (interne ou
        // externe). Pour vider entièrement la campagne, l'utilisateur doit
        // explicitement l'annuler depuis l'interface (bouton "Annuler").
        //
        // Décision UX 2026-05 — l'auto-annulation silencieuse précédente
        // surprenait les utilisateurs : ils retiraient un panneau à tort
        // et voyaient leur campagne disparaître. Mieux vaut un refus
        // explicite avec un message clair.
        $internalCount = (int) $campaign->panels()->count();
        $externalCount = (int) $campaign->externalPanels()->count();
        if (($internalCount + $externalCount) <= 1) {
            return [
                'ok'    => false,
                'error' => "Impossible de retirer le dernier panneau. Une campagne doit avoir au moins 1 panneau — ajoutez-en un autre avant de retirer celui-ci, ou annulez la campagne.",
            ];
        }

        return DB::transaction(function () use ($campaign, $panel) {
            $campaign->panels()->detach($panel->id);

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation;
                if ($reservation) {
                    $reservation->panels()->detach($panel->id);
                    $this->recalculateReservationAmount($reservation);
                }
            }

            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncPanelStatuses([$panel->id]);

            Log::info('campaign.panel_removed', [
                'campaign_id' => $campaign->id,
                'panel_id'    => $panel->id,
                'user_id'     => auth()->id(),
            ]);

            return ['ok' => true];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // ACTIVER UNE CAMPAGNE — PLANIFIE ou PAUSE → ACTIF + mail client
    //
    // Garde : refus si 0 panneau. Le mail "votre campagne démarre" part
    // seulement à la première activation (PLANIFIE → ACTIF), pas à la
    // reprise depuis PAUSE (le client a déjà reçu un mail au démarrage
    // initial — pas la peine de le renotifier).
    //
    // Retour : ['ok' => bool, 'error' => ?string, 'mail_sent' => bool].
    // ══════════════════════════════════════════════════════════════
    /**
     * Re-vérifie que les panneaux actuellement attachés à la campagne
     * (internes + externes) n'entrent pas en conflit avec un autre
     * engagement actif (autre campagne ou autre réservation).
     *
     * À appeler au moment d'une transition vers un statut bloquant
     * (PLANIFIE / ACTIF / PAUSE) pour empêcher la "résurrection" d'une
     * campagne TERMINE dont des panneaux ont été saisis à l'historique
     * (bypass de addPanels), et plus généralement détecter toute dérive.
     *
     * @return string|null  null si OK, message d'erreur explicite sinon.
     */
    public function detectConflictsOnCurrentPanels(Campaign $campaign): ?string
    {
        $internalIds = $campaign->panels()->pluck('panels.id')->all();
        if (empty($internalIds)) {
            return null;
        }

        $conflicts = $this->availability->getUnavailablePanelIds(
            $internalIds,
            $campaign->start_date->format('Y-m-d'),
            $campaign->end_date->format('Y-m-d'),
            $campaign->reservation_id,
            $campaign->id   // s'exclure soi-même
        );

        if (empty($conflicts)) {
            return null;
        }

        $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
        return "Conflit de panneaux : {$refs} déjà engagés sur la période par une autre campagne ou réservation.";
    }

    public function activate(Campaign $campaign): array
    {
        // Statut actuel exploitable ?
        $allowedFrom = [CampaignStatus::PLANIFIE, CampaignStatus::PAUSE];
        if (!in_array($campaign->status, $allowedFrom, true)) {
            return ['ok' => false, 'error' => 'Cette campagne ne peut pas être activée depuis le statut « '
                . $campaign->status->label() . ' ».'];
        }

        // Garde panneaux : impossible sans au moins 1 panneau.
        $totalPanels = $campaign->panels()->count() + $campaign->externalPanels()->count();
        if ($totalPanels === 0) {
            return ['ok' => false, 'error' => 'Ajoutez au moins un panneau à la campagne avant de l\'activer.'];
        }

        // ⚠️ Anti double-booking au moment de l'activation : re-valide que
        // les panneaux actuellement attachés ne sont pas déjà engagés ailleurs
        // (autre campagne active, autre réservation). Sans cette garde, on
        // pouvait sortir un panneau d'une campagne TERMINE (dont l'ajout
        // bypass le check, cf. addPanels) en réactivant la campagne, et créer
        // un conflit silencieux.
        if ($err = $this->detectConflictsOnCurrentPanels($campaign)) {
            return ['ok' => false, 'error' => $err];
        }

        // ⚠ Garde anti-démarrage prématuré : une campagne planifiée pour
        // une date future ne doit pas pouvoir être activée manuellement
        // avant sa start_date. Sinon le statut campagne passe ACTIF mais
        // le sync des panneaux ne les bumpe pas à OCCUPE (filtre
        // start_date <= today), créant un état incohérent. Le scheduler
        // 'campaigns:activate-planned' se charge déjà de la transition
        // automatique au bon jour.
        // Exception : transition PAUSE → ACTIF (reprise) reste autorisée
        // même si on est encore avant start_date — l'admin sait ce qu'il fait.
        if ($campaign->status === CampaignStatus::PLANIFIE
            && $campaign->start_date
            && $campaign->start_date->copy()->startOfDay()->isFuture()) {
            $startFmt = $campaign->start_date->format('d/m/Y');
            $days     = (int) now()->startOfDay()->diffInDays($campaign->start_date->copy()->startOfDay());
            return [
                'ok'    => false,
                'error' => "📅 Cette campagne est planifiée pour le {$startFmt} (dans {$days} jour" . ($days > 1 ? 's' : '') . "). "
                    . "Elle s'activera automatiquement à cette date. "
                    . "Pour démarrer plus tôt, modifie d'abord la date de début depuis « ✏️ Modifier ».",
            ];
        }

        $wasFromPlanifie = $campaign->status === CampaignStatus::PLANIFIE;

        $campaign->update([
            'status'     => CampaignStatus::ACTIF->value,
            'updated_by' => auth()->id(),
        ]);

        // ⚠ Bug fix : avant ce sync, les panneaux héritaient du statut
        // CONFIRME de leur Réservation parente et n'étaient JAMAIS bumpés
        // à OCCUPE quand la campagne démarrait (cf. AvailabilityService::
        // syncPanelStatuses qui détecte les campagnes actives). L'admin
        // voyait alors "Confirmé" éternellement même pendant l'affichage.
        // On force la sync sur tous les panneaux de la campagne après
        // l'activation pour propager OCCUPE immédiatement.
        $this->syncAllPanels(
            $this->collectAllPanelIds($campaign),
            $this->collectAllExternalPanelIds($campaign),
        );

        // Mail au client : uniquement à la première activation (pas à la
        // reprise depuis PAUSE — le client a déjà reçu l'annonce initiale).
        $mailSent = false;
        if ($wasFromPlanifie) {
            $mailSent = $this->sendStartedMailToClient($campaign->fresh());
        }

        Log::info('campaign.activated', [
            'campaign_id'  => $campaign->id,
            'from_status'  => $wasFromPlanifie ? 'planifie' : 'pause',
            'panels_count' => $totalPanels,
            'mail_sent'    => $mailSent,
            'user_id'      => auth()->id(),
        ]);

        return ['ok' => true, 'mail_sent' => $mailSent];
    }

    /**
     * Envoie le mail "campagne démarre" / "campagne planifiée" à tous
     * les destinataires utiles du client (email principal + interlocuteurs).
     * Best-effort : un envoi qui rate ne casse pas le flow appelant.
     *
     * Public : invoqué aussi à la création directe d'une campagne
     * (CampaignController::store).
     */
    public function sendStartedMailToClient(Campaign $campaign): bool
    {
        $campaign->loadMissing(['client.contacts']);
        $client = $campaign->client;
        if (!$client) {
            Log::warning('campaign.activate.mail.skip.no_client', ['campaign_id' => $campaign->id]);
            return false;
        }

        $recipients = collect()
            ->push($client->email)
            ->merge($client->contacts->pluck('email'))
            ->filter(fn($e) => $e && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            Log::warning('campaign.activate.mail.skip.no_recipient', [
                'campaign_id' => $campaign->id,
                'client_id'   => $client->id,
            ]);
            return false;
        }

        $sent = $this->mailer->sendSilently(
            $recipients->all(),
            new CampaignStartedMail($campaign),
            context: [
                'campaign_id'      => $campaign->id,
                'client_id'        => $client->id,
                'recipients_count' => $recipients->count(),
                'event'            => 'campaign.started',
            ]
        );

        return $sent;
    }

    // ══════════════════════════════════════════════════════════════
    // ANNULER UNE CAMPAGNE
    //
    // Idempotent : si déjà terminale, ne fait rien.
    // ⚠️ updateWithoutObservers() sur Reservation = obligatoire pour
    //    éviter ReservationObserver → CampaignService::cancel() → boucle.
    // ══════════════════════════════════════════════════════════════
    public function cancel(Campaign $campaign, string $reason = '', ?string $cancellationReason = null, ?string $cancellationNotes = null): void
    {
        if ($campaign->status->isTerminal()) return;

        DB::transaction(function () use ($campaign, $reason, $cancellationReason, $cancellationNotes) {
            $internalIds = $this->collectAllPanelIds($campaign);
            $externalIds = $this->collectAllExternalPanelIds($campaign);

            // Annuler la réservation liée (sans observer)
            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();
                if ($reservation && !$reservation->status->isTerminal()) {
                    $reservation->updateWithoutObservers([
                        'status' => ReservationStatus::ANNULE->value,
                        'notes'  => $this->appendNote(
                            $reservation->notes,
                            "[Auto] Annulée — campagne #{$campaign->id} annulée le " . now()->format('d/m/Y')
                        ),
                    ]);

                    Log::info('reservation.cancelled_by_campaign', [
                        'reservation_id' => $reservation->id,
                        'campaign_id'    => $campaign->id,
                    ]);
                }
            }

            $campaign->update([
                'status'               => CampaignStatus::ANNULE->value,
                'cancellation_reason'  => $cancellationReason,
                'cancellation_notes'   => $cancellationNotes ?: null,
                'notes'                => $this->appendNote($campaign->notes, $reason ? "[Auto] {$reason}" : null),
                'updated_by'           => auth()->id(),
            ]);

            $cancelledPoses = $this->cancelPendingPoseTasks($campaign, 'Campagne annulée');

            $this->syncAllPanels($internalIds, $externalIds);

            Log::info('campaign.cancelled', [
                'campaign_id'    => $campaign->id,
                'reason'         => $reason,
                'panels_freed'   => count($internalIds),
                'externals_freed'=> count($externalIds),
                'poses_cancelled'=> $cancelledPoses,
                'user_id'        => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // TERMINER UNE CAMPAGNE (clôture normale ou résiliation anticipée)
    // → Réservation = TERMINE (historique propre, ≠ annulé)
    // → Panneaux libérés immédiatement
    // ══════════════════════════════════════════════════════════════
    public function terminate(Campaign $campaign, string $reason = ''): void
    {
        if ($campaign->status->isTerminal()) return;

        DB::transaction(function () use ($campaign, $reason) {
            $internalIds = $this->collectAllPanelIds($campaign);
            $externalIds = $this->collectAllExternalPanelIds($campaign);

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();
                if ($reservation && !$reservation->status->isTerminal()) {
                    $reservation->updateWithoutObservers([
                        'status' => ReservationStatus::TERMINE->value,
                        'notes'  => $this->appendNote(
                            $reservation->notes,
                            "[Auto] Terminée — campagne #{$campaign->id} terminée le " . now()->format('d/m/Y')
                        ),
                    ]);

                    Log::info('reservation.terminated_by_campaign', [
                        'reservation_id' => $reservation->id,
                        'campaign_id'    => $campaign->id,
                    ]);
                }
            }

            $campaign->update([
                'status'     => CampaignStatus::TERMINE->value,
                'notes'      => $this->appendNote($campaign->notes, $reason ? "[Fin] {$reason}" : null),
                'updated_by' => auth()->id(),
            ]);

            // À la clôture, les poses non-faites sont annulées (campagne
            // terminée → plus de raison de poser des bâches). Les COMPLETED
            // restent comme historique.
            $cancelledPoses = $this->cancelPendingPoseTasks($campaign, 'Campagne terminée');

            $this->syncAllPanels($internalIds, $externalIds);

            Log::info('campaign.terminated', [
                'campaign_id'    => $campaign->id,
                'reason'         => $reason,
                'panels_freed'   => count($internalIds),
                'externals_freed'=> count($externalIds),
                'poses_cancelled'=> $cancelledPoses,
                'user_id'        => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER UNE CAMPAGNE (soft delete + nettoyage)
    // ══════════════════════════════════════════════════════════════
    public function delete(Campaign $campaign): array
    {
        if (!$campaign->status->isTerminal()) {
            $this->cancel($campaign, 'Annulation automatique avant suppression.');
            $campaign->refresh();
        }

        $panelIds         = $campaign->panels()->pluck('panels.id')->all();
        $externalPanelIds = $campaign->externalPanels()->pluck('external_panels.id')->all();

        DB::transaction(function () use ($campaign, $panelIds, $externalPanelIds) {
            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();

                // Réservation technique = créée par le système → suppression dure
                if ($reservation && $reservation->is_technical) {
                    $resPanelIds         = $reservation->panels()->pluck('panels.id')->all();
                    $resExternalPanelIds = $reservation->externalPanels()->pluck('external_panels.id')->all();

                    // Detach internes via la relation + delete des lignes
                    // externes du pivot reservation_panels (non gérées par
                    // la relation Eloquent filtrée).
                    $reservation->panels()->detach();
                    DB::table('reservation_panels')
                        ->where('reservation_id', $reservation->id)
                        ->where('source', 'externe')
                        ->delete();

                    $reservation->forceDelete();

                    $this->syncAllPanels($resPanelIds, $resExternalPanelIds);

                    Log::info('reservation.hard_deleted_with_campaign', [
                        'reservation_id' => $reservation->id,
                        'campaign_id'    => $campaign->id,
                    ]);
                }
                // Réservation manuelle → conservée
            }

            // Filet de sécurité : si des poses étaient encore en PLANNED
            // ou IN_PROGRESS (cas d'une campagne déjà TERMINE/ANNULE
            // datant d'avant le fix), on les annule maintenant pour ne
            // pas laisser de poses orphelines après le soft-delete.
            $cancelledPoses = $this->cancelPendingPoseTasks($campaign, 'Campagne supprimée');

            $campaign->delete();

            $this->syncAllPanels($panelIds, $externalPanelIds);

            Log::info('campaign.deleted', [
                'campaign_id'    => $campaign->id,
                'panels_count'   => count($panelIds),
                'externals_count'=> count($externalPanelIds),
                'poses_cancelled'=> $cancelledPoses,
                'user_id'        => auth()->id(),
            ]);
        });

        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════════════
    // RECALCUL DU MONTANT (utilisé par add/remove panel, prolonger, update)
    //
    // Source unique de vérité : Campaign::billableMonths()
    // ══════════════════════════════════════════════════════════════
    /**
     * Recalcule le total_amount d'une campagne.
     *
     * Source de vérité par ordre de priorité :
     *   1. Si campaign.reservation_id existe (résa réelle OU technique) →
     *      somme exacte des `reservation_panels.total_price` (respecte les
     *      prix négociés/custom).
     *   2. Sinon (cas exceptionnel : campagne sans aucune résa) →
     *      panels.monthly_rate × billableMonths.
     */
    public function recalculateCampaignAmount(Campaign $campaign): void
    {
        $countInternal = (int) $campaign->panels()->count();
        $countExternal = (int) $campaign->externalPanels()->count();

        $reservationId = $campaign->reservation_id;
        if ($reservationId) {
            // Somme exacte des total_price pivot — respecte les prix négociés
            $total = (float) DB::table('reservation_panels')
                ->where('reservation_id', $reservationId)
                ->sum('total_price');
        } else {
            // Fallback fallback : tarif catalogue × mois
            $months = $campaign->billableMonths();
            $sumRateInternal = (float) $campaign->panels()->sum('monthly_rate');
            $sumRateExternal = (float) $campaign->externalPanels()->sum('monthly_rate');
            $total = ($sumRateInternal + $sumRateExternal) * $months;
        }

        $totalRounded = round($total, 2);

        // Quand on recalcule (ajout/retrait panneau, modif prix unitaire),
        // l'éventuel override forfaitaire perd de sa pertinence — on le
        // nettoie pour repartir sur un total "calculé propre". L'admin
        // pourra ré-overrider après si besoin.
        $campaign->update([
            'total_panels'                  => $countInternal + $countExternal,
            'total_amount'                  => $totalRounded,
            'total_amount_overridden_at'    => null,
            'total_amount_overridden_by_id' => null,
            'updated_by'                    => auth()->id(),
        ]);

        // Synchronise aussi la réservation liée (réelle ou technique).
        // Sans ça, la fiche réservation affichait un total désynchronisé
        // après modification d'un prix unitaire sur la campagne.
        if ($reservationId) {
            DB::table('reservations')
                ->where('id', $reservationId)
                ->update([
                    'total_amount' => $totalRounded,
                    'updated_at'   => now(),
                ]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    /** Collecte tous les panel_id liés (campaign_panels + reservation_panels) */
    private function collectAllPanelIds(Campaign $campaign): array
    {
        $campaignPanels = $campaign->panels()->pluck('panels.id')->all();

        if (!$campaign->reservation_id) {
            return array_unique($campaignPanels);
        }

        $reservation = $campaign->reservation()->first();
        if (!$reservation) return array_unique($campaignPanels);

        $reservationPanels = $reservation->panels()->pluck('panels.id')->all();
        return array_unique(array_merge($campaignPanels, $reservationPanels));
    }

    /**
     * Collecte tous les external_panel_id liés (campaign_panels + reservation_panels).
     * Pendant pour les externes — les sync passent par syncExternalPanelStatuses.
     */
    private function collectAllExternalPanelIds(Campaign $campaign): array
    {
        $campaignExternals = $campaign->externalPanels()->pluck('external_panels.id')->all();

        if (!$campaign->reservation_id) {
            return array_unique($campaignExternals);
        }

        $reservation = $campaign->reservation()->first();
        if (!$reservation) return array_unique($campaignExternals);

        $reservationExternals = $reservation->externalPanels()->pluck('external_panels.id')->all();
        return array_unique(array_merge($campaignExternals, $reservationExternals));
    }

    /** Sync centralisé : libère internes + externes en une passe. */
    private function syncAllPanels(array $internalIds, array $externalIds): void
    {
        if (!empty($internalIds)) {
            $this->availability->syncPanelStatuses($internalIds);
        }
        if (!empty($externalIds)) {
            $this->availability->syncExternalPanelStatuses($externalIds);
        }
    }

    /**
     * Crée (ou complète) la réservation technique auto associée à une
     * campagne directe (créée sans résa explicite). Tient le pivot
     * reservation_panels à jour pour que le rapport CA + le décompte
     * commercial restent cohérents.
     *
     * @param array<int,float>|null $unitPrices  Override de prix par panel_id
     */
    private function createTechnicalReservation(Campaign $campaign, array $panelIds, float $months, ?array $unitPrices = null): void
    {
        $marker = '[Auto] Réservation technique — campagne #' . $campaign->id;

        $existing = Reservation::where('client_id', $campaign->client_id)
            ->where('start_date', $campaign->start_date->format('Y-m-d'))
            ->where('end_date',   $campaign->end_date->format('Y-m-d'))
            ->where('status',     ReservationStatus::CONFIRME->value)
            ->where('is_technical', true)
            ->where('notes', 'like', '%' . $marker . '%')
            ->first();

        $attach = $this->buildAttach($panelIds, $months, $unitPrices);
        $total  = array_sum(array_column($attach, 'total_price'));

        if (!$existing) {
            $reservation = Reservation::create([
                'reference'    => $this->generateTechReference($campaign->id),
                'client_id'    => $campaign->client_id,
                'user_id'      => auth()->id(),
                'start_date'   => $campaign->start_date->format('Y-m-d'),
                'end_date'     => $campaign->end_date->format('Y-m-d'),
                'status'       => ReservationStatus::CONFIRME->value,
                'type'         => 'ferme',
                'total_amount' => $total,
                'confirmed_at' => now(),
                'is_technical' => true,
                'notes'        => $marker,
            ]);

            $reservation->panels()->attach($attach);
            $campaign->update(['reservation_id' => $reservation->id]);

            Log::info('reservation.technical_created', [
                'reservation_id' => $reservation->id,
                'campaign_id'    => $campaign->id,
                'panel_count'    => count($panelIds),
            ]);
        } else {
            $existing->panels()->syncWithoutDetaching($attach);
            $this->recalculateReservationAmount($existing);
        }
    }

    /**
     * Construit le tableau d'attachement pivot reservation_panels avec
     * support des prix négociés. Si `unitPrices[$panel_id]` est défini,
     * il prime sur `panel.monthly_rate`.
     *
     * @param array<int,float>|null $unitPrices
     */
    private function buildAttach(array $panelIds, float $months, ?array $unitPrices = null): array
    {
        $rates = Panel::whereIn('id', $panelIds)
            ->pluck('monthly_rate', 'id')
            ->map(fn($r) => (float) ($r ?? 0));

        $attach = [];
        foreach ($panelIds as $id) {
            // Prix custom si fourni et > 0, sinon tarif catalogue
            $custom = isset($unitPrices[$id]) ? (float) $unitPrices[$id] : null;
            $unit   = ($custom !== null && $custom >= 0)
                ? $custom
                : (float) ($rates[$id] ?? 0);

            $attach[$id] = [
                'unit_price'  => $unit,
                'total_price' => round($unit * $months, 2),
                'source'      => 'interne',
            ];
        }
        return $attach;
    }

    private function recalculateReservationAmount(Reservation $reservation): void
    {
        $total = (float) DB::table('reservation_panels')
            ->where('reservation_id', $reservation->id)
            ->sum('total_price');

        $reservation->updateWithoutObservers(['total_amount' => round($total, 2)]);
    }

    private function generateTechReference(int $campaignId): string
    {
        return 'AUTO-' . str_pad((string) $campaignId, 6, '0', STR_PAD_LEFT) . '-' . now()->format('YmdHis');
    }

    private function appendNote(?string $existing, ?string $new): ?string
    {
        if (!$new) return $existing;
        return trim(($existing ?? '') . "\n" . $new);
    }

    /**
     * Annule en bloc les PoseTask non commencées (PLANNED) ou en cours
     * (IN_PROGRESS) d'une campagne, lors d'une annulation, terminaison ou
     * suppression de campagne.
     *
     * Les COMPLETED restent intactes — c'est l'historique terrain : la
     * bâche a été posée, la pige photo existe peut-être, on garde la
     * traçabilité même si la campagne disparaît.
     *
     * @return int Nombre de PoseTask annulées.
     */
    private function cancelPendingPoseTasks(Campaign $campaign, string $reason): int
    {
        $now = now();
        $note = "[Auto] {$reason} #" . $campaign->id . ' le ' . $now->format('d/m/Y');

        $affected = PoseTask::where('campaign_id', $campaign->id)
            ->whereIn('status', [
                PoseTaskStatus::PLANNED->value,
                PoseTaskStatus::IN_PROGRESS->value,
            ])
            ->get();

        foreach ($affected as $task) {
            $task->update([
                'status' => PoseTaskStatus::CANCELLED->value,
                'notes'  => $this->appendNote($task->notes, $note),
            ]);
        }

        if ($affected->isNotEmpty()) {
            Log::info('campaign.poses_cancelled', [
                'campaign_id' => $campaign->id,
                'count'       => $affected->count(),
                'reason'      => $reason,
            ]);
        }

        return $affected->count();
    }
}
