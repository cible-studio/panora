<?php

namespace App\Console\Commands;

use App\Services\Barometre\BarometreService;
use Illuminate\Console\Command;

/**
 * Baromètre clients (2026-11-17) : envoie les invitations et relances
 * DEMANDÉES depuis l'écran. Planifiée chaque minute (routes/console.php) ;
 * une seule requête quand rien n'attend. Ne décide jamais d'envoyer seule.
 */
class BarometreEnvoyer extends Command
{
    protected $signature = 'barometre:envoyer';

    protected $description = 'Baromètre clients : envoie par Brevo les invitations et relances demandées à l\'écran';

    public function handle(BarometreService $service): int
    {
        $bilan = $service->traiterDemandes();

        if (array_sum($bilan) > 0) {
            $this->info("Invitations : {$bilan['invitations']} · relances : {$bilan['relances']} · échecs : {$bilan['echecs']}");
        }

        return self::SUCCESS;
    }
}
