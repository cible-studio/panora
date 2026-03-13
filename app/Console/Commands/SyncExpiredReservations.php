<?php
namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Console\Command;

class SyncExpiredReservations extends Command
{
    protected $signature   = 'reservations:sync-expired';
    protected $description = 'Libère les panneaux dont la réservation est expirée (end_date dépassée)';

    public function handle(AvailabilityService $availability): void
    {
        // Réservations actives dont la date de fin est passée
        $expired = Reservation::whereIn('status', ['en_attente', 'confirme'])
            ->where('end_date', '<', now()->toDateString())
            ->with('panels')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Aucune réservation expirée.');
            return;
        }

        $totalPanels = 0;

        foreach ($expired as $reservation) {
            $panelIds = $reservation->panels->pluck('id')->toArray();
            $reservation->update(['status' => 'annule']);
            $availability->syncPanelStatuses($panelIds);
            $totalPanels += count($panelIds);

            $this->line("→ {$reservation->reference} expirée — {$count} panneau(x) libéré(s).");
        }

        $this->info("{$expired->count()} réservation(s) expirée(s). $totalPanels panneau(x) libéré(s).");
    }
}