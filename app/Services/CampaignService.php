<?php
namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    /** Statuts permettant la modification du panel (ajout/retrait) */
    private const MODIFIABLE_STATUSES = ['planifie', 'actif', 'pose'];

    public function __construct(
        protected AvailabilityService $availability
    ) {}

    // ══════════════════════════════════════════════════════════════
    // AJOUTER DES PANNEAUX
    // ══════════════════════════════════════════════════════════════
    public function addPanels(Campaign $campaign, array $panelIds): array
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

        return DB::transaction(function () use ($campaign, $panelIds) {
            // Verrou pessimiste sur les panels
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

            $months = $campaign->billableMonths();

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
                'count'       => count($panelIds),
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
        if (!in_array($campaign->status->value, self::MODIFIABLE_STATUSES)) {
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

            // Si c'était le dernier panneau, on annule la campagne
            if ($remainingCount <= 1) {
                $this->cancel($campaign, 'Dernier panneau retiré automatiquement.');
                return ['ok' => true, 'warning' => 'Campagne annulée — plus aucun panneau.'];
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
    // ANNULER UNE CAMPAGNE
    //
    // Idempotent : si déjà terminale, ne fait rien.
    // ⚠️ updateWithoutObservers() sur Reservation = obligatoire pour
    //    éviter ReservationObserver → CampaignService::cancel() → boucle.
    // ══════════════════════════════════════════════════════════════
    public function cancel(Campaign $campaign, string $reason = ''): void
    {
        if ($campaign->status->isTerminal()) return;

        DB::transaction(function () use ($campaign, $reason) {
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
                'status'     => CampaignStatus::ANNULE->value,
                'notes'      => $this->appendNote($campaign->notes, $reason ? "[Auto] {$reason}" : null),
                'updated_by' => auth()->id(),
            ]);

            $this->syncAllPanels($internalIds, $externalIds);

            Log::info('campaign.cancelled', [
                'campaign_id'    => $campaign->id,
                'reason'         => $reason,
                'panels_freed'   => count($internalIds),
                'externals_freed'=> count($externalIds),
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

            $this->syncAllPanels($internalIds, $externalIds);

            Log::info('campaign.terminated', [
                'campaign_id'    => $campaign->id,
                'reason'         => $reason,
                'panels_freed'   => count($internalIds),
                'externals_freed'=> count($externalIds),
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

            $campaign->delete();

            $this->syncAllPanels($panelIds, $externalPanelIds);

            Log::info('campaign.deleted', [
                'campaign_id'    => $campaign->id,
                'panels_count'   => count($panelIds),
                'externals_count'=> count($externalPanelIds),
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
    public function recalculateCampaignAmount(Campaign $campaign): void
    {
        $months = $campaign->billableMonths();

        // Internes + externes : monthly_rate sommé sur les deux relations.
        $sumRateInternal = (float) $campaign->panels()->sum('monthly_rate');
        $sumRateExternal = (float) $campaign->externalPanels()->sum('monthly_rate');
        $countInternal   = (int)   $campaign->panels()->count();
        $countExternal   = (int)   $campaign->externalPanels()->count();

        $campaign->update([
            'total_panels' => $countInternal + $countExternal,
            'total_amount' => round(($sumRateInternal + $sumRateExternal) * $months, 2),
            'updated_by'   => auth()->id(),
        ]);
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

    private function createTechnicalReservation(Campaign $campaign, array $panelIds, float $months): void
    {
        $marker = '[Auto] Réservation technique — campagne #' . $campaign->id;

        $existing = Reservation::where('client_id', $campaign->client_id)
            ->where('start_date', $campaign->start_date->format('Y-m-d'))
            ->where('end_date',   $campaign->end_date->format('Y-m-d'))
            ->where('status',     ReservationStatus::CONFIRME->value)
            ->where('is_technical', true)
            ->where('notes', 'like', '%' . $marker . '%')
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

    private function buildAttach(array $panelIds, float $months): array
    {
        $rates = Panel::whereIn('id', $panelIds)
            ->pluck('monthly_rate', 'id')
            ->map(fn($r) => (float) ($r ?? 0));

        $attach = [];
        foreach ($panelIds as $id) {
            $unit        = (float) ($rates[$id] ?? 0);
            $attach[$id] = [
                'unit_price'  => $unit,
                'total_price' => round($unit * $months, 2),
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
}
