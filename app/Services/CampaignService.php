<?php
namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(
        protected AvailabilityService $availability
    ) {}

    // ══════════════════════════════════════════════════════════════
    // AJOUTER DES PANNEAUX
    // ══════════════════════════════════════════════════════════════
    public function addPanels(Campaign $campaign, array $panelIds): array
    {
        if (!in_array($campaign->status->value, ['actif', 'pose'])) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        $alreadyIn = $campaign->panels->pluck('id')->intersect($panelIds)->toArray();
        if (!empty($alreadyIn)) {
            $refs = Panel::whereIn('id', $alreadyIn)->pluck('reference')->join(', ');
            return ['ok' => false, 'error' => "Ces panneaux sont déjà dans la campagne : {$refs}"];
        }

        return DB::transaction(function () use ($campaign, $panelIds) {
            Panel::whereIn('id', $panelIds)->lockForUpdate()->get();

            $conflicts = $this->availability->getUnavailablePanelIds(
                $panelIds,
                $campaign->start_date->format('Y-m-d'),
                $campaign->end_date->format('Y-m-d'),
                $campaign->reservation_id
            );

            if (!empty($conflicts)) {
                $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                return ['ok' => false, 'error' => "Panneaux non disponibles : {$refs}"];
            }

            $months = $this->monthsBetween(
                $campaign->start_date->format('Y-m-d'),
                $campaign->end_date->format('Y-m-d')
            );

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation;
                $attach      = $this->buildAttach($panelIds, $months);
                $reservation->panels()->syncWithoutDetaching($attach);
                $this->recalculateReservationAmount($reservation);
            } else {
                $this->createTechnicalReservation($campaign, $panelIds, $months);
            }

            $campaign->panels()->syncWithoutDetaching($panelIds);
            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncPanelStatuses($panelIds);

            Log::info('campaign.panels_added', [
                'campaign_id' => $campaign->id,
                'panel_ids'   => $panelIds,
                'user_id'     => auth()->id(),
            ]);

            return ['ok' => true, 'added' => count($panelIds)];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // RETIRER UN PANNEAU
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel): array
    {
        if (!in_array($campaign->status->value, ['actif', 'pose'])) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        $remainingCount = $campaign->panels()->count();

        return DB::transaction(function () use ($campaign, $panel, $remainingCount) {
            $campaign->panels()->detach($panel->id);

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation;
                if ($reservation) {
                    $reservation->panels()->detach($panel->id);
                    $this->recalculateReservationAmount($reservation);
                }
            }

            if ($remainingCount <= 1) {
                $this->cancel($campaign, 'Dernier panneau retiré automatiquement.');
                return ['ok' => true, 'warning' => 'Campagne annulée — plus aucun panneau.'];
            }

            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncPanelStatuses([$panel->id]);
            return ['ok' => true];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // ANNULER UNE CAMPAGNE
    //
    // Cas : annulation volontaire, dernier panneau retiré, suppression.
    // La réservation est marquée ANNULE et les panneaux libérés.
    //
    // ⚠️ updateWithoutObservers() est obligatoire pour éviter :
    //    Observer.updated() → campaignService.cancel() → boucle infinie
    // ══════════════════════════════════════════════════════════════
    public function cancel(Campaign $campaign, string $reason = ''): void
    {
        if ($campaign->status->isTerminal()) {
            return; // idempotent
        }

        DB::transaction(function () use ($campaign, $reason) {

            // ── Collecter les panneaux AVANT toute modification ──────────
            $campaignPanelIds    = $campaign->panels()->pluck('panels.id')->toArray();
            $reservationPanelIds = [];
            $reservation         = null;

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();
            }
            if ($reservation) {
                $reservationPanelIds = $reservation->panels()->pluck('panels.id')->toArray();
            }

            $allPanelIds = array_unique(array_merge($campaignPanelIds, $reservationPanelIds));

            // ── Annuler la réservation sans déclencher l'Observer ────────
            // NE PAS detach() les panels ici — syncPanelStatuses() en a besoin
            // La réservation ANNULE est filtrée par BLOCKING_STATUSES → panneaux libres
            if ($reservation && !$reservation->status->isTerminal()) {
                $reservation->updateWithoutObservers([
                    'status' => ReservationStatus::ANNULE->value,
                    'notes'  => trim(
                        ($reservation->notes ?? '') .
                        "\n[Auto] Annulée — campagne #{$campaign->id} annulée le " . now()->format('d/m/Y')
                    ),
                ]);

                Log::info('reservation.cancelled_by_campaign', [
                    'reservation_id' => $reservation->id,
                    'campaign_id'    => $campaign->id,
                ]);
            }

            // ── Annuler la campagne ──────────────────────────────────────
            $campaign->update([
                'status'     => CampaignStatus::ANNULE->value,
                'notes'      => trim(($campaign->notes ?? '') . "\n[Auto] " . $reason),
                'updated_by' => auth()->id() ?? null,
            ]);

            // ── Sync panneaux → libre ────────────────────────────────────
            // reservation_panels toujours présents mais réservation ANNULE
            // → exclue des BLOCKING_STATUSES → panneaux passent à libre
            if (!empty($allPanelIds)) {
                $this->availability->syncPanelStatuses($allPanelIds);
            }

            Log::info('campaign.cancelled', [
                'campaign_id'    => $campaign->id,
                'reason'         => $reason,
                'reservation_id' => $reservation?->id,
                'panels_freed'   => count($allPanelIds),
                'user_id'        => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // TERMINER UNE CAMPAGNE MANUELLEMENT (avant ou à l'échéance)
    //
    // LOGIQUE MÉTIER CIBLE CI :
    // "Terminer" = résiliation anticipée ou clôture normale.
    // → Réservation → TERMINE (pas annulée, historique propre)
    // → Panneaux libérés immédiatement
    // → Différent de cancel() : trace que la campagne s'est bien déroulée
    // ══════════════════════════════════════════════════════════════
    public function terminate(Campaign $campaign, string $reason = ''): void
    {
        if ($campaign->status->isTerminal()) {
            return; // idempotent
        }

        DB::transaction(function () use ($campaign, $reason) {

            $campaignPanelIds    = $campaign->panels()->pluck('panels.id')->toArray();
            $reservationPanelIds = [];
            $reservation         = null;

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();
            }
            if ($reservation) {
                $reservationPanelIds = $reservation->panels()->pluck('panels.id')->toArray();
            }

            $allPanelIds = array_unique(array_merge($campaignPanelIds, $reservationPanelIds));

            // Terminer la réservation sans déclencher l'Observer
            if ($reservation && !$reservation->status->isTerminal()) {
                $reservation->updateWithoutObservers([
                    'status' => ReservationStatus::TERMINE->value,
                    'notes'  => trim(
                        ($reservation->notes ?? '') .
                        "\n[Auto] Terminée — campagne #{$campaign->id} terminée le " . now()->format('d/m/Y')
                    ),
                ]);

                Log::info('reservation.terminated_by_campaign', [
                    'reservation_id' => $reservation->id,
                    'campaign_id'    => $campaign->id,
                ]);
            }

            // Terminer la campagne
            $campaign->update([
                'status'     => CampaignStatus::TERMINE->value,
                'notes'      => trim(($campaign->notes ?? '') . ($reason ? "\n[Fin] " . $reason : '')),
                'updated_by' => auth()->id() ?? null,
            ]);

            // Libérer les panneaux immédiatement
            // TERMINE est hors BLOCKING_STATUSES → syncPanelStatuses → libre
            if (!empty($allPanelIds)) {
                $this->availability->syncPanelStatuses($allPanelIds);
            }

            Log::info('campaign.terminated', [
                'campaign_id'    => $campaign->id,
                'reason'         => $reason,
                'reservation_id' => $reservation?->id,
                'panels_freed'   => count($allPanelIds),
                'user_id'        => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER UNE CAMPAGNE (soft delete)
    // ══════════════════════════════════════════════════════════════
    public function delete(Campaign $campaign): array
    {
        if (!$campaign->status->isTerminal()) {
            $this->cancel($campaign, 'Annulation automatique avant suppression.');
            $campaign->refresh();
        }

        $panelIds = $campaign->panels()->pluck('panels.id')->toArray();

        DB::transaction(function () use ($campaign, $panelIds) {

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();

                if ($reservation && $reservation->is_technical) {
                    $resPanelIds = $reservation->panels()->pluck('panels.id')->toArray();
                    $reservation->panels()->detach();
                    $reservation->forceDelete();

                    if (!empty($resPanelIds)) {
                        $this->availability->syncPanelStatuses($resPanelIds);
                    }

                    Log::info('reservation.hard_deleted_with_campaign', [
                        'reservation_id' => $reservation->id,
                        'campaign_id'    => $campaign->id,
                    ]);
                }
                // Réservation normale → conservée dans l'historique
            }

            $campaign->delete();

            if (!empty($panelIds)) {
                $this->availability->syncPanelStatuses($panelIds);
            }

            Log::info('campaign.deleted', [
                'campaign_id'  => $campaign->id,
                'panels_count' => count($panelIds),
                'user_id'      => auth()->id(),
            ]);
        });

        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    private function createTechnicalReservation(
        Campaign $campaign,
        array    $panelIds,
        float    $months
    ): void {
        $existing = Reservation::where('client_id', $campaign->client_id)
            ->where('start_date', $campaign->start_date->format('Y-m-d'))
            ->where('end_date', $campaign->end_date->format('Y-m-d'))
            ->where('status', ReservationStatus::CONFIRME->value)
            ->where('is_technical', true)
            ->where('notes', 'like', '%[Auto] Réservation technique — campagne #' . $campaign->id . '%')
            ->first();

        $attach = $this->buildAttach($panelIds, $months);
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
                'notes'        => '[Auto] Réservation technique — campagne #' . $campaign->id,
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

    private function buildAttach(array $panelIds, float $months): array
    {
        $panelData = Panel::whereIn('id', $panelIds)->get()->keyBy('id');
        $attach    = [];
        foreach ($panelIds as $id) {
            $unit        = (float)(($panelData[$id] ?? null)?->monthly_rate ?? 0);
            $attach[$id] = ['unit_price' => $unit, 'total_price' => $unit * $months];
        }
        return $attach;
    }

    private function recalculateReservationAmount(Reservation $reservation): void
    {
        $total = $reservation->panels()->get()
            ->sum(fn($p) => (float)($p->pivot->total_price ?? 0));
        $reservation->update(['total_amount' => $total]);
    }

    public function recalculateCampaignAmount(Campaign $campaign): void
    {
        $months   = $campaign->durationInMonths();
        $newTotal = $campaign->panels()->get()
            ->sum(fn($p) => (float)($p->monthly_rate ?? 0) * $months);
        $campaign->update([
            'total_panels' => $campaign->panels()->count(),
            'total_amount' => $newTotal,
            'updated_by'   => auth()->id(),
        ]);
    }

    private function generateTechReference(int $campaignId): string
    {
        return 'AUTO-' . str_pad($campaignId, 6, '0', STR_PAD_LEFT) . '-' . now()->format('YmdHis');
    }

    public function monthsBetween(string $start, string $end): float
    {
        $s      = Carbon::parse($start)->startOfDay();
        $e      = Carbon::parse($end)->endOfDay();
        $months = (int)$s->diffInMonths($e);
        $remain = $s->copy()->addMonths($months)->diffInDays($e);
        return max((float)($remain > 0 ? $months + 1 : $months), 1.0);
    }
}