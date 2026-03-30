<?php
// app/Console/Commands/SyncPanelStatusesCommand.php
namespace App\Console\Commands;

use App\Models\Panel;
use App\Services\AvailabilityService;
use Illuminate\Console\Command;

class SyncPanelStatusesCommand extends Command
{
    protected $signature   = 'panels:sync-statuses {--dry-run}';
    protected $description = 'Resync tous les statuts panels depuis reservation_panels';

    public function handle(AvailabilityService $availability): void
    {
        $panelIds = Panel::whereNotIn('status', ['maintenance'])
            ->pluck('id')->toArray();

        if ($this->option('dry-run')) {
            $this->info('Dry run — ' . count($panelIds) . ' panneaux à synchroniser');
            return;
        }

        // Traiter par chunks de 200 pour éviter les timeouts
        collect($panelIds)->chunk(200)->each(function ($chunk) use ($availability) {
            $availability->syncPanelStatuses($chunk->toArray());
        });

        $this->info('✅ ' . count($panelIds) . ' panneaux synchronisés.');
    }
}