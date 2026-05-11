<?php
namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Console\Command;

class ExpireReservationOptions extends Command
{
    protected $signature   = 'reservations:expire-old-options {--days=7}';
    protected $description = 'Expire les options non confirmées créées depuis plus de N jours (défaut: 7), même si leur période est future';

    public function handle(AvailabilityService $availability): void
    {
        $days    = (int) $this->option('days');
        $expired = Reservation::where('status', 'en_attente')
            ->where('type', 'option')
            ->where('created_at', '<', now()->subDays($days))
            ->with(['panels', 'externalPanels'])
            ->get();

        if ($expired->isEmpty()) {
            $this->info("Aucune option expirée (> {$days}j).");
            return;
        }

        foreach ($expired as $reservation) {
            $panelIds    = $reservation->panels->pluck('id')->toArray();
            $externalIds = $reservation->externalPanels->pluck('id')->toArray();

            $reservation->update(['status' => 'annule']);

            if (!empty($panelIds)) {
                $availability->syncPanelStatuses($panelIds);
            }
            if (!empty($externalIds)) {
                $availability->syncExternalPanelStatuses($externalIds);
            }

            $this->line("→ Option {$reservation->reference} expirée.");
        }

        $this->info("{$expired->count()} option(s) expirée(s).");
    }
}