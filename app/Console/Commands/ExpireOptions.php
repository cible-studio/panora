<?php
namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireOptions extends Command
{
    protected $signature   = 'reservations:expire-options {--days=7}';
    protected $description = 'Expire les options non confirmées après N jours';

    public function handle(AvailabilityService $availability): int
    {
        $days   = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $oldOptions = Reservation::where('status', ReservationStatus::EN_ATTENTE->value)
            ->where('type', 'option')
            ->where('created_at', '<', $cutoff)
            ->with('panels')
            ->get();

        if ($oldOptions->isEmpty()) {
            $this->info("✓ Aucune option expirée (seuil : {$days} jours).");
            return self::SUCCESS;
        }

        $allPanelIds = [];

        DB::transaction(function () use ($oldOptions, &$allPanelIds) {
            foreach ($oldOptions as $reservation) {
                $panelIds    = $reservation->panels->pluck('id')->toArray();
                $allPanelIds = array_merge($allPanelIds, $panelIds);

                $reservation->update(['status' => ReservationStatus::ANNULE->value]);

                Log::info('reservation.option_expired', [
                    'reservation_id' => $reservation->id,
                    'reference'      => $reservation->reference,
                    'days_old'       => $days,
                ]);

                $this->line("  → {$reservation->reference} option expirée ({$days}j)");
            }
        });

        if (!empty($allPanelIds)) {
            $availability->syncPanelStatuses(array_unique($allPanelIds));
        }

        $this->info("✓ {$oldOptions->count()} option(s) expirée(s).");
        return self::SUCCESS;
    }
}