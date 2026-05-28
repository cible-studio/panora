<?php
// app/Console/Commands/GeoBackfill.php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Models\Pige;
use App\Services\GeoService;
use App\Services\PanelGeoLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule l'existant (historique) pour les 2 chantiers géo :
 *   1. Panneaux : géolocalise depuis les piges DÉJÀ validées (médiane).
 *   2. Piges    : classe la cohérence GPS (geo_distance_m / geo_check) via
 *      la MÊME logique que PigeObserver (GeoService::pigePanelCheck).
 *
 * Idempotent : relançable sans dégât.
 *   - Panneaux manuels / legacy non typés : protégés par PanelGeoLocator.
 *   - Piges : seules celles avec geo_check NULL sont traitées, sauf --all-piges.
 */
class GeoBackfill extends Command
{
    protected $signature = 'geo:backfill
        {--dry-run    : Affiche ce qui serait fait sans rien écrire}
        {--panels     : Ne traiter que les panneaux}
        {--piges      : Ne traiter que les piges}
        {--all-piges  : Recalculer AUSSI les piges déjà classées (sinon seulement geo_check NULL)}';

    protected $description = 'Backfill géoloc : géolocalise les panneaux depuis les piges validées et classe la cohérence GPS des piges historiques';

    public function handle(GeoService $geo, PanelGeoLocator $locator): int
    {
        $dry      = (bool) $this->option('dry-run');
        $only     = $this->option('panels') || $this->option('piges');
        $doPanels = !$only || $this->option('panels');
        $doPiges  = !$only || $this->option('piges');

        if ($dry) {
            $this->warn('— DRY RUN — aucune écriture.');
        }

        $panelsGeolocated = 0;
        $pigesClassified  = 0;

        // ── 1. PANNEAUX : géoloc depuis les piges validées ───────────────
        if ($doPanels) {
            // Seuls les panneaux ayant ≥1 pige validée géolocalisée peuvent
            // changer ; on restreint à ceux-là.
            $panelIds = Pige::query()
                ->where('status', 'verifie')
                ->whereNotNull('gps_lat')
                ->whereNotNull('gps_lng')
                ->distinct()
                ->pluck('panel_id')
                ->filter()
                ->values();

            $this->info("Panneaux candidats (piges validées géolocalisées) : {$panelIds->count()}");

            if ($dry) {
                $this->line('  → seraient (re)calculés (hors manuels/legacy protégés).');
            } else {
                $bar = $this->output->createProgressBar($panelIds->count());
                $bar->start();
                foreach ($panelIds->chunk(200) as $chunk) {
                    foreach (Panel::whereIn('id', $chunk)->get() as $panel) {
                        try {
                            if ($locator->recomputeFromPiges($panel)) {
                                $panelsGeolocated++;
                            }
                        } catch (\Throwable $e) {
                            $this->newLine();
                            $this->error("  panneau #{$panel->id} : {$e->getMessage()}");
                        }
                        $bar->advance();
                    }
                }
                $bar->finish();
                $this->newLine();
            }
        }

        // ── 2. PIGES : classification cohérence GPS ──────────────────────
        if ($doPiges) {
            $base = Pige::query()->with('panel:id,latitude,longitude');
            if (!$this->option('all-piges')) {
                $base->whereNull('geo_check');
            }

            $total = (clone $base)->toBase()->count();
            $this->info("Piges à classer : {$total}" . ($this->option('all-piges') ? ' (toutes)' : ' (geo_check NULL)'));

            if (!$dry && $total > 0) {
                $bar = $this->output->createProgressBar($total);
                $bar->start();
                $base->chunkById(500, function ($piges) use ($geo, &$pigesClassified, $bar) {
                    foreach ($piges as $pige) {
                        $panel = $pige->panel;
                        $verdict = $geo->pigePanelCheck(
                            $pige->gps_lat !== null ? (float) $pige->gps_lat : null,
                            $pige->gps_lng !== null ? (float) $pige->gps_lng : null,
                            ($panel && $panel->latitude  !== null) ? (float) $panel->latitude  : null,
                            ($panel && $panel->longitude !== null) ? (float) $panel->longitude : null,
                        );

                        // Écriture directe : pas de timestamps ni d'observer
                        // (on ne ré-émet pas l'événement creating/updating).
                        DB::table('piges')->where('id', $pige->id)->update([
                            'geo_distance_m' => $verdict['distance'],
                            'geo_check'      => $verdict['check'],
                        ]);
                        $pigesClassified++;
                        $bar->advance();
                    }
                });
                $bar->finish();
                $this->newLine();
            }
        }

        $this->newLine();
        $this->info('═══ Récapitulatif ═══');
        if ($doPanels) $this->line("  Panneaux géolocalisés : {$panelsGeolocated}" . ($dry ? ' (dry-run)' : ''));
        if ($doPiges)  $this->line("  Piges classées        : {$pigesClassified}" . ($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
