<?php

namespace App\Console\Commands;

use App\Services\Barometre\BarometreService;
use Illuminate\Console\Command;

/**
 * Baromètre clients (2026-11-17) : retire des clients de l'envoi par leur
 * nom exact (majuscules, accents et espaces ignorés), avec un motif.
 * Réversible à l'écran (« Remettre »). Un nom introuvable ou ambigu n'est
 * jamais deviné : il est listé.
 *
 *   php artisan barometre:exclure "JCDECAUX" "PUB RÉGIE" --motif="Régie"
 */
class BarometreExclure extends Command
{
    protected $signature = 'barometre:exclure {noms* : noms exacts des clients} {--motif= : raison affichée à l\'écran}';

    protected $description = 'Baromètre clients : retire des clients de l\'envoi, par leur nom';

    public function handle(BarometreService $service): int
    {
        $motif = trim((string) $this->option('motif'));
        if ($motif === '') {
            $this->error('Indiquez un motif : --motif="Régie" par exemple.');

            return self::FAILURE;
        }

        $r = $service->exclureParNoms($this->argument('noms'), $motif);

        $this->info(count($r['exclus']) . " client(s) exclu(s) — motif « {$motif} » :");
        foreach ($r['exclus'] as $n) {
            $this->line("  ✓ {$n}");
        }
        foreach ($r['introuvables'] as $n) {
            $this->warn("  ? {$n} : aucun client de ce nom");
        }
        foreach ($r['ambigus'] as $n) {
            $this->warn("  ! {$n} : plusieurs clients de ce nom, à exclure depuis l'écran");
        }

        return self::SUCCESS;
    }
}
