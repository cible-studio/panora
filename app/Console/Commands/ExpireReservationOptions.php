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
            // ═══ Ne pas tuer une attente de bon de commande (2026-11-13) ═══
            // Un dossier en attente de BC vit 30 jours (config/bon_de_commande)
            // avec des relances à J-15, J-7, J-2. Sans ce garde-fou, cette
            // commande — planifiée tous les jours à 01h20 — l'annulait dès le
            // 7e jour, AVANT même la première relance.
            // Ces dossiers-là sont expirés par `reservations:expire-bc`,
            // qui respecte leur propre échéance.
            ->whereNull('bc_attendu_jusqu_au')
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