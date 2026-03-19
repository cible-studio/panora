<?php
namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncExpiredReservations extends Command
{
    protected $signature   = 'reservations:sync-expired';
    protected $description = 'Libère les panneaux des réservations expirées';

    public function handle(AvailabilityService $availability): int
    {
        $today = now()->startOfDay();

        $expired = Reservation::whereIn('status', [
                ReservationStatus::EN_ATTENTE->value,
                ReservationStatus::CONFIRME->value,
            ])
            ->where('end_date', '<', $today)
            ->with('panels')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('✓ Aucune réservation expirée.');
            return self::SUCCESS;
        }

        $allPanelIds = [];

        DB::transaction(function () use ($expired, &$allPanelIds) {
            foreach ($expired as $reservation) {
                $panelIds    = $reservation->panels->pluck('id')->toArray();
                $allPanelIds = array_merge($allPanelIds, $panelIds);

                $reservation->update(['status' => ReservationStatus::ANNULE->value]);

                Log::info('reservation.auto_expired', [
                    'reservation_id' => $reservation->id,
                    'reference'      => $reservation->reference,
                    'end_date'       => $reservation->end_date->toDateString(),
                    'panels_freed'   => count($panelIds),
                ]);

                $this->line("  → {$reservation->reference} expirée");
            }
        });

        // Sync global en une passe après toutes les annulations
        if (!empty($allPanelIds)) {
            $availability->syncPanelStatuses(array_unique($allPanelIds));
        }

        $this->info("✓ {$expired->count()} réservation(s) expirée(s) traitée(s).");
        return self::SUCCESS;
    }
}