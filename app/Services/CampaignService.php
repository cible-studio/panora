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
    // AJOUTER DES PANNEAUX À UNE CAMPAGNE
    // ══════════════════════════════════════════════════════════════
    /**
     * Ajoute des panneaux à une campagne.
     * Si la campagne n'a pas de réservation liée → crée une réservation technique auto.
     * Si elle en a une → ajoute les panneaux à cette réservation aussi.
     */
    public function addPanels(Campaign $campaign, array $panelIds): array
    {
        if (!in_array($campaign->status->value, ['actif', 'pose'])) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        // Doublons dans la campagne
        $alreadyIn = $campaign->panels->pluck('id')
            ->intersect($panelIds)->toArray();
        if (!empty($alreadyIn)) {
            $refs = Panel::whereIn('id', $alreadyIn)->pluck('reference')->join(', ');
            return ['ok' => false, 'error' => "Ces panneaux sont déjà dans la campagne : {$refs}"];
        }

        return DB::transaction(function () use ($campaign, $panelIds) {

            // Verrou pessimiste anti race condition
            Panel::whereIn('id', $panelIds)->lockForUpdate()->get();

            // Source de vérité — disponibilité via reservation_panels
            $conflicts = $this->availability->getUnavailablePanelIds(
                $panelIds,
                $campaign->start_date->format('Y-m-d'),
                $campaign->end_date->format('Y-m-d'),
                $campaign->reservation_id
            );

            if (!empty($conflicts)) {
                $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                return ['ok' => false, 'error' => "Panneaux non disponibles sur cette période : {$refs}"];
            }

            $months = $this->monthsBetween(
                $campaign->start_date->format('Y-m-d'),
                $campaign->end_date->format('Y-m-d')
            );

            if ($campaign->reservation_id) {
                // ── Cas 1 : Réservation existante → ajouter les panneaux dedans
                $reservation = $campaign->reservation;
                $attach      = $this->buildAttach($panelIds, $months);

                $reservation->panels()->syncWithoutDetaching($attach);
                $this->recalculateReservationAmount($reservation);
            } else {
                // ── Cas 2 : Pas de réservation → créer une réservation technique
                $this->createTechnicalReservation($campaign, $panelIds, $months);
            }

            // Attacher à la table campaign_panels
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
    // RETIRER UN PANNEAU — avec règle "dernier panneau"
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel): array
    {
        if (!in_array($campaign->status->value, ['actif', 'pose'])) {
            return ['ok' => false, 'error' => 'Campagne non modifiable.'];
        }

        $remainingCount = $campaign->panels()->count();

        return DB::transaction(function () use ($campaign, $panel, $remainingCount) {

            // Détacher de la campagne
            $campaign->panels()->detach($panel->id);

            // Détacher aussi de la réservation liée si elle existe
            if ($campaign->reservation_id) {
                $reservation = $campaign->reservation;
                if ($reservation) {
                    $reservation->panels()->detach($panel->id);
                    $this->recalculateReservationAmount($reservation);
                }
            }

            if ($remainingCount <= 1) {
                // Dernier panneau → annulation automatique de la campagne
                $this->cancel($campaign, 'Dernier panneau retiré automatiquement.');

                // Annuler la réservation si elle est maintenant vide
                if ($campaign->reservation_id) {
                    $reservation = $campaign->reservation()->first();
                    if ($reservation && $reservation->panels()->count() === 0) {
                        $reservation->update(['status' => ReservationStatus::ANNULE->value]);
                    }
                }

                $this->availability->syncPanelStatuses([$panel->id]);

                return [
                    'ok'      => true,
                    'warning' => 'Campagne annulée automatiquement — plus aucun panneau.',
                ];
            }

            // Panneau retiré, campagne continue
            $this->recalculateCampaignAmount($campaign);
            $this->availability->syncPanelStatuses([$panel->id]);

            return ['ok' => true];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // ANNULER UNE CAMPAGNE
    // ══════════════════════════════════════════════════════════════
    public function cancel(Campaign $campaign, string $reason = ''): void
    {
        if ($campaign->status->isTerminal()) {
            return; // idempotent
        }

        DB::transaction(function () use ($campaign, $reason) {
            $campaignPanelIds    = $campaign->panels->pluck('id')->toArray();
            $reservationPanelIds = $campaign->reservation
                ? $campaign->reservation->panels->pluck('id')->toArray()
                : [];

            // Panneaux exclusifs à la campagne (ajoutés manuellement, pas dans la réservation)
            // Ces panneaux ne seront pas synchés par l'Observer → on les gère ici
            $exclusivePanelIds = array_diff($campaignPanelIds, $reservationPanelIds);

            $campaign->update([
                'status'     => CampaignStatus::ANNULE->value,
                'notes'      => trim(($campaign->notes ?? '') . "\n[Auto] " . $reason),
                'updated_by' => auth()->id() ?? null,
            ]);

            if (!empty($exclusivePanelIds)) {
                $this->availability->syncPanelStatuses($exclusivePanelIds);
            }

            Log::info('campaign.cancelled', [
                'campaign_id'            => $campaign->id,
                'reason'                 => $reason,
                'exclusive_panels_freed' => count($exclusivePanelIds),
                'user_id'                => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER UNE CAMPAGNE (soft delete)
    // ══════════════════════════════════════════════════════════════
    public function delete(Campaign $campaign): array
    {
        if ($campaign->status->value !== CampaignStatus::ANNULE->value) {
            return [
                'ok'    => false,
                'error' => 'Annulez la campagne avant de la supprimer.',
            ];
        }

        $panelIds = $campaign->panels->pluck('id')->toArray();

        DB::transaction(function () use ($campaign, $panelIds) {
            $campaign->delete(); // SoftDelete

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

    /**
     * Crée une réservation technique "auto" pour maintenir la cohérence
     * de la source de vérité (reservation_panels) quand des panneaux sont
     * ajoutés à une campagne sans réservation préalable.
     */
    private function createTechnicalReservation(
        Campaign $campaign,
        array    $panelIds,
        float    $months
    ): void {
        // Chercher si une réservation technique existe déjà pour cette campagne
        // On l'identifie par la note auto
        $existing = Reservation::where('client_id', $campaign->client_id)
            ->where('start_date', $campaign->start_date->format('Y-m-d'))
            ->where('end_date', $campaign->end_date->format('Y-m-d'))
            ->where('status', ReservationStatus::CONFIRME->value)
            ->whereNull('campaign_id_auto') // champ optionnel pour tracking
            ->where('notes', 'like', '%[Auto] Réservation technique liée à la campagne #' . $campaign->id . '%')
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
                'notes'        => '[Auto] Réservation technique liée à la campagne #' . $campaign->id,
            ]);

            $reservation->panels()->attach($attach);
            $campaign->update(['reservation_id' => $reservation->id]);
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
            $panel        = $panelData[$id] ?? null;
            $unit         = (float)($panel?->monthly_rate ?? 0);
            $attach[$id]  = [
                'unit_price'  => $unit,
                'total_price' => $unit * $months,
            ];
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