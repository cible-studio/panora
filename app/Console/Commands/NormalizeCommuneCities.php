<?php

namespace App\Console\Commands;

use App\Models\Commune;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Normalise la colonne communes.city pour distinguer Ville et Commune.
 *
 * Règle métier CI :
 *   - Les 14 communes du district autonome d'Abidjan ont city = "Abidjan"
 *     (Adjamé, Attécoubé, Cocody, Koumassi, Marcory, Plateau, Port-Bouët,
 *      Treichville, Yopougon, Abobo, Songon, Bingerville, Anyama, Bassam-
 *      la Mé n'en fait pas partie mais souvent traité ainsi).
 *   - Les autres communes (Bouaké, Daloa, San Pedro…) ont city = nom de
 *     la commune (qui correspond généralement à la ville hors Abidjan).
 *
 * Idempotent : on peut le relancer sans risque (UPDATE avec condition
 * sur la valeur cible).
 *
 *   php artisan communes:normalize-cities --dry-run
 *   php artisan communes:normalize-cities --force
 */
class NormalizeCommuneCities extends Command
{
    protected $signature   = 'communes:normalize-cities
        {--dry-run : Affiche les changements sans rien modifier}
        {--force   : Saute la confirmation interactive}';
    protected $description = 'Normalise communes.city — distingue ville et commune (Évolution Frontend dispos).';

    /**
     * Liste explicite des communes considérées comme "Abidjan" en city.
     * Comparaison case + accents insensitive via normalize().
     */
    private const ABIDJAN_COMMUNES = [
        'adjame', 'attecoube', 'cocody', 'koumassi', 'marcory',
        'plateau', 'port-bouet', 'treichville', 'treich-ville',
        'yopougon', 'abobo', 'songon', 'bingerville', 'anyama',
        'autoroute du nord', 'assinie',
    ];

    public function handle(): int
    {
        $dry   = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $changes = [];
        foreach (Commune::orderBy('name')->get() as $c) {
            $expected = $this->expectedCity($c);
            if ($c->city !== $expected) {
                $changes[] = ['id' => $c->id, 'name' => $c->name, 'old' => $c->city, 'new' => $expected];
            }
        }

        if (empty($changes)) {
            $this->info('✓ Toutes les communes ont déjà la bonne ville.');
            return self::SUCCESS;
        }

        $this->line('  Changements à appliquer (' . count($changes) . ') :');
        foreach ($changes as $ch) {
            $this->line(sprintf("    %-25s : %-12s → %s", $ch['name'], $ch['old'] ?: '∅', $ch['new']));
        }

        if ($dry) {
            $this->warn('  ▶ DRY-RUN : aucune écriture.');
            return self::SUCCESS;
        }

        if (!$force && !$this->confirm('  Appliquer ?', false)) {
            $this->line('  Annulé.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $ch) {
                Commune::where('id', $ch['id'])->update(['city' => $ch['new']]);
            }
        });

        $this->info('  ✓ ' . count($changes) . ' commune(s) mise(s) à jour.');
        return self::SUCCESS;
    }

    private function expectedCity(Commune $c): string
    {
        $normalized = $this->normalize($c->name);
        if (in_array($normalized, self::ABIDJAN_COMMUNES, true)) {
            return 'Abidjan';
        }
        // Hors Abidjan, la ville = nom de la commune (en Title Case).
        return mb_convert_case(mb_strtolower($c->name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(
            ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç'],
            ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c'],
            $s
        );
        return $s;
    }
}
