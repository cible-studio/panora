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
    public function confirmer(Reservation $reservation): ?Campaign
    {
        $campaign = null;

        DB::transaction(function () use ($reservation, &$campaign) {

            // Changer statut → confirmé + promouvoir type option → ferme
            $reservation->update([
                'status'       => ReservationStatus::CONFIRME,
                'type'         => 'ferme',
                'confirmed_at' => now(),
            ]);

            $availability = app(AvailabilityService::class);

            // Sync panneaux internes
            $panelIds = $reservation->panels->pluck('id')->toArray();
            if (!empty($panelIds)) {
                $availability->syncPanelStatuses($panelIds);
            }

            // Sync panneaux externes
            $externalIds = $reservation->externalPanels->pluck('id')->toArray();
            if (!empty($externalIds)) {
                $availability->syncExternalPanelStatuses($externalIds);
            }

            // Créer campagne si elle n'existe pas
            if (!$reservation->campaign) {
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
                    'name'           => "Campagne {$reservation->reference}",
                    'client_id'      => $reservation->client_id,
                    'reservation_id' => $reservation->id,
                    'start_date'     => $reservation->start_date,
                    'end_date'       => $reservation->end_date,
                    'status'         => $campStatus,
                    'total_panels'   => count($panelIds) + count($externalIds),
                    'total_amount'   => $reservation->total_amount,
                    'user_id'        => $reservation->user_id,
                ]);

                if (!empty($panelIds)) {
                    $campaign->panels()->sync($panelIds);
                }

                // Le pivot campaign_panels supporte external_panel_id avec
                // type='externe' — même schéma que ReservationController::store.
                if (!empty($externalIds)) {
                    $rows = array_map(fn($extId) => [
                        'campaign_id'       => $campaign->id,
                        'external_panel_id' => $extId,
                        'type'              => 'externe',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ], $externalIds);
                    DB::table('campaign_panels')->insert($rows);
                }

                // Lot 9.1 — Auto-création des tâches de pose APRÈS le sync
                // des panneaux (l'observer `created` est trop tôt, panneaux
                // pas encore liés à ce moment-là).
                $campaign->ensurePoseTasksAutoCreated();
            } else {
                $campaign = $reservation->campaign;
            }

            Log::info('proposition.confirmed', [
                'reservation_id' => $reservation->id,
                'campaign_id'    => $campaign->id,
                'client_id'      => $reservation->client_id,
                'panels'         => count($panelIds),
                'externals'      => count($externalIds),
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