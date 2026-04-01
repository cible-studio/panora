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
use Illuminate\Support\Str;

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
                // ── Dernier panneau → annulation complète via cancel() ──
                // cancel() gère tout : campagne + réservation + panneaux
                $this->cancel($campaign, 'Dernier panneau retiré automatiquement.');
                return ['ok' => true, 'warning' => 'Campagne annulée — plus aucun panneau.'];
            }

            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncPanelStatuses([$panel->id]);
            return ['ok' => true];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // ANNULER UNE CAMPAGNE — SOURCE DE VÉRITÉ UNIQUE
    //
    // Séquence garantie :
    //   1. Annuler la RÉSERVATION liée (libère les panneaux via reservation_panels)
    //   2. Annuler la CAMPAGNE
    //   3. Sync statuts panneaux
    //
    // Résultat : 1 seul clic utilisateur → tout est libéré
    // ══════════════════════════════════════════════════════════════
    public function cancel(Campaign $campaign, string $reason = ''): void
    {
        // Idempotent — si déjà terminal, on ne fait rien
        if ($campaign->status->isTerminal()) {
            Log::debug('campaign.cancel_skipped_already_terminal', [
                'campaign_id' => $campaign->id,
                'status'      => $campaign->status->value,
            ]);
            return;
        }

        DB::transaction(function () use ($campaign, $reason) {

            // ── Étape 1 : Collecter TOUS les panneaux à libérer AVANT d'annuler ──
            // On collecte maintenant car après annulation les relations peuvent être vidées
            $campaignPanelIds = $campaign->panels()->pluck('panels.id')->toArray();

            $reservationPanelIds = [];
            $reservation         = null;

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();
            }

            if ($reservation) {
                $reservationPanelIds = $reservation->panels()->pluck('panels.id')->toArray();
            }

            // Union de tous les panneaux concernés (campagne + réservation)
            $allPanelIds = array_unique(
                array_merge($campaignPanelIds, $reservationPanelIds)
            );

            // ── Étape 2 : Annuler la RÉSERVATION liée ──────────────────────────
            // C'est elle qui bloque les panneaux dans reservation_panels.
            // Sans cette étape, syncPanelStatuses() lirait encore la réservation
            // active et garderait les panneaux comme occupés.
            if ($reservation && !$reservation->status->isTerminal()) {
                // On bypasse l'Observer en mettant à jour directement
                // (évite une boucle Observer → CampaignService::cancel → Observer)
                Reservation::withoutObservers(function () use ($reservation, $campaign) {
                    $reservation->update([
                        'status' => ReservationStatus::ANNULE->value,
                        'notes'  => trim(
                            ($reservation->notes ?? '') .
                            "\n[Auto] Annulée — campagne #{$campaign->id} annulée"
                        ),
                    ]);
                });

                Log::info('reservation.cancelled_by_campaign', [
                    'reservation_id' => $reservation->id,
                    'campaign_id'    => $campaign->id,
                    'reason'         => $reason,
                ]);
            }

            // ── Étape 3 : Annuler la CAMPAGNE ──────────────────────────────────
            $campaign->update([
                'status'     => CampaignStatus::ANNULE->value,
                'notes'      => trim(($campaign->notes ?? '') . "\n[Auto] " . $reason),
                'updated_by' => auth()->id() ?? null,
            ]);

            // ── Étape 4 : Sync statuts panneaux ────────────────────────────────
            // Maintenant que la réservation est annulée, syncPanelStatuses()
            // ne trouvera plus de réservations actives → panneaux → libre
            if (!empty($allPanelIds)) {
                $this->availability->syncPanelStatuses($allPanelIds);
            }

            Log::info('campaign.cancelled', [
                'campaign_id'     => $campaign->id,
                'reason'          => $reason,
                'reservation_id'  => $reservation?->id,
                'panels_freed'    => count($allPanelIds),
                'user_id'         => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER UNE CAMPAGNE (soft delete)
    // La campagne doit être annulée au préalable.
    // ══════════════════════════════════════════════════════════════
    public function delete(Campaign $campaign): array
    {
        // Si pas encore annulée, on l'annule d'abord automatiquement
        if (!$campaign->status->isTerminal()) {
            $this->cancel($campaign, 'Annulation automatique avant suppression.');
            $campaign->refresh(); // recharger après cancel()
        }

        $panelIds = $campaign->panels()->pluck('panels.id')->toArray();

        DB::transaction(function () use ($campaign, $panelIds) {

            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation()->first();

                if ($reservation && $reservation->is_technical) {
                    // Réservation technique auto-créée → supprimer proprement
                    $resPanelIds = $reservation->panels()->pluck('panels.id')->toArray();
                    $reservation->panels()->detach();
                    $reservation->forceDelete();

                    Log::info('reservation.hard_deleted_with_campaign', [
                        'reservation_id' => $reservation->id,
                        'campaign_id'    => $campaign->id,
                    ]);

                    // Re-sync au cas où (is_technical supprimée = panneaux forcément libres)
                    if (!empty($resPanelIds)) {
                        $this->availability->syncPanelStatuses($resPanelIds);
                    }
                }
                // Réservation normale → on la conserve dans l'historique
            }

            $campaign->delete(); // SoftDelete

            // Sync final
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