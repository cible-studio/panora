<?php

namespace App\Console\Commands;

use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Console\Command;

/**
 * Supprime les PDF des envois de test trop anciens (règle du 2026-11-09 :
 * 7 jours, DIFFUSION_JOURS_PDF_TESTS). Le 9/11, ils pesaient 342 Mo sur les
 * 545 Mo de la sauvegarde. La règle est dans
 * DiffusionDisponibilitesService::purgerPdfTests().
 *
 * Planifiée à 1h15, avant la sauvegarde de la nuit (cf. routes/console.php).
 */
class PurgerPdfTestsDiffusion extends Command
{
    protected $signature = 'dispos:purger-pdf-tests
        {--simuler : Affiche ce qui serait supprimé, sans rien toucher}';

    protected $description = 'Supprime les PDF des envois de test de plus de 7 jours (les envois aux clients ne sont pas concernés)';

    public function handle(DiffusionDisponibilitesService $diffusion): int
    {
        $simuler = (bool) $this->option('simuler');
        $bilan = $diffusion->purgerPdfTests($simuler);

        $this->info(sprintf(
            '%s : %d envoi(s) de test, %d fichier(s), %d Mo.',
            $simuler ? 'Simulation, rien n\'est supprimé' : 'Supprimé',
            $bilan['envois'],
            $bilan['fichiers'],
            (int) round($bilan['octets'] / 1048576)
        ));

        return self::SUCCESS;
    }
}
