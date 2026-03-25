<?php
// ══════════════════════════════════════════════════════════════════════
// FICHIER 2 — app/Console/Commands/ExpireOptions.php
// Options (en_attente) dont end_date est passée → statut 'annule'
// Libère les panneaux en option non confirmés
// ══════════════════════════════════════════════════════════════════════

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireOptions extends Command
{
    protected $signature   = 'reservations:expire-options';
    protected $description = 'Annule les réservations en option dont la date de fin est passée et libère les panneaux';

    public function __construct(protected AvailabilityService $availability)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today()->format('Y-m-d');

        // Options (en_attente) expirées — la date de fin est passée
        $expired = Reservation::where('status', ReservationStatus::EN_ATTENTE->value)
            ->where('end_date', '<', $today)
            ->with('panels')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Aucune option expirée à traiter.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $reservation) {
            $panelIds = $reservation->panels->pluck('id')->toArray();

            $reservation->update(['status' => ReservationStatus::ANNULE->value]);

            if (!empty($panelIds)) {
                $this->availability->syncPanelStatuses($panelIds);
            }

            Log::info('reservation.option_expired', [
                'reservation_id' => $reservation->id,
                'reference'      => $reservation->reference,
                'end_date'       => $reservation->end_date->format('Y-m-d'),
                'panels_freed'   => count($panelIds),
            ]);

            $count++;
        }

        $this->info("$count option(s) expirée(s) annulée(s) et panneaux libérés.");
        return Command::SUCCESS;
    }
}