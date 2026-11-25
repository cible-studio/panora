<?php

namespace App\Console\Commands;

use App\Services\Promos\PromoService;
use Illuminate\Console\Command;

/**
 * Clics comptés par Panora sur la page d'une promotion (2026-11-25).
 * Brevo donne, lui, le détail client par client (rapport de la campagne).
 */
class BilanPromo extends Command
{
    protected $signature = 'promos:clics {code=interieur-35 : code de la promotion (config/promos.php)}';

    protected $description = "Clics sur la page d'une promotion envoyée par mail (robots à part)";

    public function handle(PromoService $promos): int
    {
        $code = (string) $this->argument('code');
        if ($promos->promo($code) === null) {
            $this->error("Promotion inconnue : $code (codes : " . implode(', ', array_keys((array) config('promos'))) . ')');

            return self::FAILURE;
        }

        $b = $promos->bilan($code);
        $this->info("Promotion « $code » : {$b['clics']} clic(s), {$b['visiteurs']} visiteur(s) distinct(s)"
            . ($b['robots'] ? " · {$b['robots']} ouverture(s) par des robots, non comptées" : ''));

        if ($b['formats']) {
            $this->table(['Format', 'Clics'], collect($b['formats'])->map(fn ($n, $f) => [$f, $n])->values()->all());
        }
        if ($b['jours']) {
            $this->table(['Jour', 'Clics'], collect($b['jours'])->map(fn ($n, $j) => [$j, $n])->values()->all());
        }

        return self::SUCCESS;
    }
}
