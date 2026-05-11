<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Réduit la table zones à 2 entrées : Abidjan + Intérieur Pays.
 *
 * Métier : chez CIBLE CI, "zone" désigne la grande région (Abidjan ou
 * Intérieur), pas une sous-division par commune. Le SeedParc historique
 * créait 1 zone par commune ("ADJAME - Centre"…) ; cette commande corrige
 * et remappe les panneaux via `communes.region`.
 *
 * Idempotent : relançable sans risque (les panneaux pointent toujours
 * vers la bonne zone après).
 *
 *   php artisan zones:simplify --dry-run
 *   php artisan zones:simplify --force
 */
class SimplifyZones extends Command
{
    protected $signature   = 'zones:simplify
        {--dry-run : Affiche le plan sans rien modifier}
        {--force   : Saute la confirmation interactive}';
    protected $description = 'Réduit zones à 2 (Abidjan + Intérieur) et remappe les panneaux via commune.region.';

    private const ABIDJAN  = 'Abidjan';
    private const INTERIEUR = 'Intérieur Pays';

    public function handle(): int
    {
        $dry   = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('═══════════════════════════════════════════════');
        $this->info('  zones:simplify — 2 zones (Abidjan / Intérieur)');
        $this->info('═══════════════════════════════════════════════');

        // Analyse : combien de communes / panneaux par région ?
        $regions = DB::table('communes')
            ->select('region', DB::raw('COUNT(*) as nb_communes'))
            ->groupBy('region')
            ->get();

        $this->line('  Communes par région :');
        foreach ($regions as $r) {
            $this->line(sprintf('    %-20s %4d commune(s)', $r->region ?: '(NULL)', $r->nb_communes));
        }

        $panelsByRegion = DB::table('panels')
            ->join('communes', 'communes.id', '=', 'panels.commune_id')
            ->select('communes.region', DB::raw('COUNT(panels.id) as nb_panels'))
            ->groupBy('communes.region')
            ->get();

        $this->line('  Panneaux par région :');
        foreach ($panelsByRegion as $r) {
            $this->line(sprintf('    %-20s %4d panneau(x)', $r->region ?: '(NULL)', $r->nb_panels));
        }

        $oldZonesCount = DB::table('zones')->count();
        $this->line('  Zones actuelles : '.$oldZonesCount);

        if ($dry) {
            $this->warn('  ▶ DRY-RUN : aucune modification.');
            return self::SUCCESS;
        }
        if (!$force && !$this->confirm('  ⚠  Réduire à 2 zones et remapper les panneaux ?', false)) {
            $this->line('  Annulé.');
            return self::SUCCESS;
        }

        // ── Exécution ────────────────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('zones')->truncate();

            DB::table('zones')->insert([
                'id'           => 1,
                'name'         => self::ABIDJAN,
                'description'  => 'Communes de la zone du district autonome d\'Abidjan',
                'demand_level' => 'haute',
                'commune_id'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            DB::table('zones')->insert([
                'id'           => 2,
                'name'         => self::INTERIEUR,
                'description'  => 'Communes des autres régions du pays',
                'demand_level' => 'normale',
                'commune_id'   => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $this->info('  ✓ 2 zones créées (Abidjan, Intérieur Pays)');

            // Remappe : zone_id selon communes.region
            $updatedAbidjan = DB::table('panels')
                ->join('communes', 'communes.id', '=', 'panels.commune_id')
                ->where('communes.region', self::ABIDJAN)
                ->update(['panels.zone_id' => 1]);

            $updatedInterieur = DB::table('panels')
                ->join('communes', 'communes.id', '=', 'panels.commune_id')
                ->where('communes.region', '!=', self::ABIDJAN)
                ->update(['panels.zone_id' => 2]);

            $this->info('  ✓ '.$updatedAbidjan.' panneau(x) → Abidjan');
            $this->info('  ✓ '.$updatedInterieur.' panneau(x) → Intérieur Pays');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return self::SUCCESS;
    }
}
