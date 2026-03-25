<?php
// ══════════════════════════════════════════════════════════════════════
// FICHIER 1 — app/Console/Commands/SyncExpiredReservations.php
// Réservations confirmées dont end_date est passée → statut 'termine'
// Libère les panneaux associés
// ══════════════════════════════════════════════════════════════════════

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncExpiredReservations extends Command
{
    protected $signature   = 'reservations:sync-expired';
    protected $description = 'Passe les réservations confirmées expirées en "termine" et libère les panneaux';

    public function __construct(protected AvailabilityService $availability)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today()->format('Y-m-d');

        // Réservations confirmées dont la date de fin est passée
        $expired = Reservation::where('status', ReservationStatus::CONFIRME->value)
            ->where('end_date', '<', $today)
            ->with('panels')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Aucune réservation expirée à traiter.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $reservation) {
            $panelIds = $reservation->panels->pluck('id')->toArray();

            $reservation->update(['status' => 'termine']);

            if (!empty($panelIds)) {
                $this->availability->syncPanelStatuses($panelIds);
            }

            Log::info('reservation.auto_expired', [
                'reservation_id' => $reservation->id,
                'reference'      => $reservation->reference,
                'end_date'       => $reservation->end_date->format('Y-m-d'),
                'panels_freed'   => count($panelIds),
            ]);

            $count++;
        }

        $this->info("$count réservation(s) expirée(s) traitée(s).");
        return Command::SUCCESS;
    }
}