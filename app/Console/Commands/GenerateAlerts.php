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

        $this->line("  Réservations en attente >48h : {$counts['reservations_attente']} nouvelle(s)");
        $this->line("  Maintenances urgentes        : {$counts['maintenances']} nouvelle(s)");
        $this->line("  Fin de campagne (J7/J3/J0)   : {$counts['campagnes_fin']} nouvelle(s)");
        $this->line("  Panneaux maintenance longue  : {$counts['panneaux_maintenance']} nouvelle(s)");
        $this->line("  Poses en retard              : {$counts['poses_retard']} nouvelle(s)");
        $this->line("  Poses sans pige              : {$counts['piges_manquantes']} nouvelle(s)");

        $total = array_sum($counts);
        $this->info("Total : {$total} alerte(s) générée(s).");

        return Command::SUCCESS;
    }
}
