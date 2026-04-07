<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AlertService;

class GenerateAlerts extends Command
{
    protected $signature   = 'alerts:generate';
    protected $description = 'Générer les alertes automatiques (réservations, maintenances, campagnes)';

    public function handle(AlertService $service): int
    {
        $this->info('Génération des alertes...');

        $counts = $service->generateAll();

        $this->line("  ✅ Réservations en attente : {$counts['reservations']} nouvelle(s)");
        $this->line("  ✅ Maintenances urgentes   : {$counts['maintenances']} nouvelle(s)");
        $this->line("  ✅ Campagnes expirant      : {$counts['campagnes']} nouvelle(s)");
        $this->line("  ✅ Panneaux maintenance    : {$counts['panneaux']} nouvelle(s)");

        $total = array_sum($counts);
        $this->info("Total : {$total} alerte(s) générée(s).");

        return Command::SUCCESS;
    }
}
