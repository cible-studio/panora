<?php
namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\PanelStatus;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Reservation;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        protected AvailabilityService $availability
    ) {}

    // ══════════════════════════════════════════════════════════════
    // CRÉER UNE RÉSERVATION
    // ══════════════════════════════════════════════════════════════
    public function createFromSelection(
        array  $data,
        array  $panelIds,
        string $campaignName = ''
    ): array {
        return DB::transaction(function () use ($data, $panelIds, $campaignName) {

            Panel::whereIn('id', $panelIds)->lockForUpdate()->get();

            $conflicts = $this->availability->getUnavailablePanelIds(
                $panelIds,
                $data['start_date'],
                $data['end_date']
            );

            if (!empty($conflicts)) {
                $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                throw new \RuntimeException("CONFLICT:{$refs}");
            }

            $status    = $data['type'] === 'ferme'
                ? ReservationStatus::CONFIRME
                : ReservationStatus::EN_ATTENTE;

            $months    = $this->monthsBetween($data['start_date'], $data['end_date']);
            $panelData = Panel::whereIn('id', $panelIds)->get()->keyBy('id');
            $total     = 0;
            $attach    = [];

            foreach ($panelIds as $id) {
                $unit        = (float) ($panelData[$id]->monthly_rate ?? 0);
                $tot         = $unit * $months;
                $total      += $tot;
                $attach[$id] = ['unit_price' => $unit, 'total_price' => $tot];
            }

            $reservation = Reservation::create([
                'reference'    => $this->generateReference(),
                'client_id'    => $data['client_id'],
                'user_id'      => auth()->id(),
                'start_date'   => $data['start_date'],
                'end_date'     => $data['end_date'],
                'status'       => $status,
                'type'         => $data['type'],
                'notes'        => $data['notes'] ?? null,
                'total_amount' => $total,
                'confirmed_at' => $data['type'] === 'ferme' ? now() : null,
            ]);

            $reservation->panels()->attach($attach);
            $this->availability->syncPanelStatuses($panelIds);

            Log::info('reservation.created', [
                'reservation_id' => $reservation->id,
                'reference'      => $reservation->reference,
                'type'           => $data['type'],
                'panel_count'    => count($panelIds),
                'user_id'        => auth()->id(),
            ]);

            $campaign = null;
            if ($data['type'] === 'ferme' && $campaignName !== '') {
                $campaign = $this->createCampaignFromReservation(
                    $reservation,
                    $campaignName,
                    $panelIds,
                    $total
                );
            }

            return ['reservation' => $reservation, 'campaign' => $campaign];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // METTRE À JOUR
    // ══════════════════════════════════════════════════════════════
    public function updateReservation(
        Reservation $reservation,
        array       $data,
        array       $oldPanels
    ): void {
        DB::transaction(function () use ($reservation, $data, $oldPanels) {

            Panel::whereIn('id', $data['panel_ids'])->lockForUpdate()->get();

            $conflicts = $this->availability->getUnavailablePanelIds(
                $data['panel_ids'],
                $data['start_date'],
                $data['end_date'],
                $reservation->id
            );

            if (!empty($conflicts)) {
                $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                throw new \RuntimeException("CONFLICT:{$refs}");
            }

            $months    = $this->monthsBetween($data['start_date'], $data['end_date']);
            $panelData = Panel::whereIn('id', $data['panel_ids'])->get()->keyBy('id');
            $sync      = [];
            $total     = 0;

            foreach ($data['panel_ids'] as $id) {
                $unit       = (float) ($panelData[$id]->monthly_rate ?? 0);
                $tot        = $unit * $months;
                $total     += $tot;
                $sync[$id]  = ['unit_price' => $unit, 'total_price' => $tot];
            }

            $reservation->update([
                'client_id'    => $data['client_id'],
                'start_date'   => $data['start_date'],
                'end_date'     => $data['end_date'],
                'notes'        => $data['notes'] ?? null,
                'total_amount' => $total,
            ]);

            $reservation->panels()->sync($sync);

            $allAffected = array_unique(array_merge($oldPanels, $data['panel_ids']));
            $this->availability->syncPanelStatuses($allAffected);

            Log::info('reservation.updated', [
                'reservation_id' => $reservation->id,
                'user_id'        => auth()->id(),
                'ip'             => request()->ip(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // ANNULER — avec motif documenté (extraData optionnel)
    // ══════════════════════════════════════════════════════════════
    public function cancel(Reservation $reservation, array $extraData = []): void
    {
        $panelIds  = $reservation->panels->pluck('id')->toArray();
        $oldStatus = $reservation->status->value;

        $reservation->update(array_merge(
            ['status' => ReservationStatus::ANNULE->value],
            $extraData // cancel_type, cancel_reason, cancelled_at, cancelled_by
        ));

        $this->availability->syncPanelStatuses($panelIds);

        Log::info('reservation.cancelled', [
            'reservation_id' => $reservation->id,
            'from_status'    => $oldStatus,
            'cancel_type'    => $extraData['cancel_type'] ?? null,
            'panel_ids'      => $panelIds,
            'user_id'        => auth()->id(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // CHANGER LE STATUT
    // ══════════════════════════════════════════════════════════════
    public function changeStatus(Reservation $reservation, string $newStatus): void
    {
        $oldStatus = $reservation->status->value;
        $data      = ['status' => $newStatus];

        if ($newStatus === ReservationStatus::CONFIRME->value) {
            $data['confirmed_at'] = now();
            $data['type']         = 'ferme';
        }

        $reservation->update($data);
        $this->availability->syncPanelStatuses(
            $reservation->panels->pluck('id')->toArray()
        );

        Log::info('reservation.status_changed', [
            'reservation_id' => $reservation->id,
            'from'           => $oldStatus,
            'to'             => $newStatus,
            'user_id'        => auth()->id(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER
    // ══════════════════════════════════════════════════════════════
    public function delete(Reservation $reservation): void
    {
        $panelIds = $reservation->panels()->pluck('panels.id')->toArray();
        $campaign = $reservation->campaign;

        DB::transaction(function () use ($reservation, $panelIds, $campaign) {
            if ($campaign) {
                $campaign->update([
                    'status' => CampaignStatus::ANNULE->value,
                    'notes'  => trim(
                        ($campaign->notes ?? '') .
                        "\n[Auto] Campagne annulée — réservation #{$reservation->reference} supprimée"
                    ),
                ]);

                Log::info('campaign.cancelled_by_reservation_deletion', [
                    'campaign_id'    => $campaign->id,
                    'reservation_id' => $reservation->id,
                    'user_id'        => auth()->id(),
                ]);
            }

            $reservation->panels()->detach();
            $reservation->delete();

            if (!empty($panelIds)) {
                $this->availability->syncPanelStatuses($panelIds);
            }

            Log::info('reservation.deleted', [
                'reservation_id'   => $reservation->id,
                'reference'        => $reservation->reference,
                'campaign_annuled' => $campaign !== null,
                'panels_freed'     => count($panelIds),
                'user_id'          => auth()->id(),
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    private function createCampaignFromReservation(
        Reservation $reservation,
        string      $campaignName,
        array       $panelIds,
        float       $total
    ): Campaign {
        if (Campaign::where('client_id', $reservation->client_id)
            ->where('name', $campaignName)->exists()) {
            throw new \RuntimeException(
                'CAMPAIGN_EXISTS:Une campagne avec ce nom existe déjà pour ce client.'
            );
        }

        // Statut selon date de début
        $campStatus = now()->startOfDay()->lt(
            Carbon::parse($reservation->start_date)->startOfDay()
        ) ? CampaignStatus::PLANIFIE->value : CampaignStatus::ACTIF->value;

        $campaign = Campaign::create([
            'name'           => $campaignName,
            'client_id'      => $reservation->client_id,
            'reservation_id' => $reservation->id,
            'user_id'        => auth()->id(),
            'start_date'     => $reservation->start_date,
            'end_date'       => $reservation->end_date,
            'status'         => $campStatus,
            'total_panels'   => count($panelIds),
            'total_amount'   => $total,
            'notes'          => $reservation->notes,
        ]);

        $campaign->panels()->sync($panelIds);

        Log::info('campaign.auto_created', [
            'campaign_id'    => $campaign->id,
            'reservation_id' => $reservation->id,
            'status'         => $campStatus,
            'user_id'        => auth()->id(),
        ]);

        return $campaign;
    }

    private function generateReference(): string
    {
        $attempts = 0;
        do {
            // Après 10 tentatives → fallback UUID
            $candidate = $attempts < 10
                ? 'RES-' . strtoupper(Str::random(8))
                : 'RES-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));

            if (++$attempts > 20) {
                throw new \RuntimeException('SYSTEM:Référence impossible à générer.');
            }
        } while (Reservation::where('reference', $candidate)->exists());

        return $candidate;
    }

    // ══════════════════════════════════════════════════════════════
    // RÈGLE FACTURATION CIBLE CI — 15j = demi-mois
    // Identique à ReservationController::monthsBetween()
    // et à la fonction JS _months()
    // ══════════════════════════════════════════════════════════════
    public function monthsBetween(string $start, string $end): float
    {
        $s         = Carbon::parse($start)->startOfDay();
        $e         = Carbon::parse($end)->startOfDay();
        $totalDays = (int) $s->diffInDays($e);

        if ($totalDays <= 0) return 0.5;

        $fullMonths = (int) floor($totalDays / 30);
        $remainDays = $totalDays % 30;

        $fraction = 0;
        if ($remainDays >= 1 && $remainDays <= 15) {
            $fraction = 0.5;
        } elseif ($remainDays > 15) {
            $fraction = 1;
        }

        return max($fullMonths + $fraction, 0.5);
    }
}