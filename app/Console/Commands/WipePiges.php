<?php
// app/Console/Commands/WipePiges.php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Models\Pige;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime TOUTES les piges (et leurs photos disque) et réinitialise les
 * panneaux qui avaient été auto-géolocalisés à partir d'elles.
 *
 * Pour repartir d'une base propre quand les piges actuelles sont des tests
 * et qu'on veut accumuler des vraies données terrain.
 *
 * NE TOUCHE PAS :
 *  - aux panneaux avec gps_source='manual' (saisie humaine, intouchable).
 *  - aux panneaux legacy (lat/lng présents + gps_source NULL — coordonnées
 *    historiques antérieures à la feature géoloc).
 *
 * Sécurité : confirmation par défaut + flag --dry-run + transaction DB.
 */
class WipePiges extends Command
{
    protected $signature = 'piges:wipe
        {--dry-run     : Affiche ce qui serait supprimé sans rien toucher}
        {--keep-files  : Conserve les fichiers photo dans storage (par défaut, on supprime)}
        {--force       : Saute la confirmation (déconseillé hors script)}';

    protected $description = 'Supprime toutes les piges + photos disque + réinitialise les panneaux auto-géolocalisés. Ne touche pas aux coordonnées manuelles/legacy.';

    public function handle(): int
    {
        $dry        = (bool) $this->option('dry-run');
        $keepFiles  = (bool) $this->option('keep-files');

        $pigesCount = Pige::count();
        $autoPanels = Panel::whereIn('gps_source', ['pige_provisional', 'pige_confirmed'])->count();

        // Liste les chemins à supprimer AVANT le wipe DB (sinon on les perd).
        $paths  = Pige::whereNotNull('photo_path')->pluck('photo_path')->all();
        $thumbs = array_filter(Pige::whereNotNull('photo_thumb')->pluck('photo_thumb')->all());

        $this->info('═══ piges:wipe — état actuel ═══');
        $this->line("  Piges en base        : {$pigesCount}");
        $this->line("  Photos disque        : " . count($paths) . " (+ " . count($thumbs) . " thumbnails)");
        $this->line("  Panneaux auto-géoloc : {$autoPanels} (gps_source=pige_provisional/pige_confirmed)");
        $this->line("  Panneaux manuels     : " . Panel::where('gps_source', 'manual')->count() . " → PROTÉGÉS, non touchés.");

        if ($pigesCount === 0 && $autoPanels === 0) {
            $this->info('Rien à faire — base déjà propre.');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->newLine();
            $this->warn('— DRY RUN — aucune écriture.');
            $this->line('Seraient effectués :');
            $this->line("  • DELETE {$pigesCount} piges (table piges).");
            $this->line('  • UPDATE des '.$autoPanels.' panneaux auto-géolocalisés → lat/lng/gps_source/dispersion/computed_at = NULL.');
            if (!$keepFiles) {
                $this->line('  • Suppression des '.count($paths).' fichiers photo (+ thumbnails) du storage public.');
            } else {
                $this->line('  • Fichiers photo CONSERVÉS (--keep-files).');
            }
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->newLine();
            $this->warn('⚠️  Action destructive et irréversible.');
            $confirm = $this->confirm(
                "Confirmer la suppression de {$pigesCount} piges et la réinit de {$autoPanels} panneaux ?",
                false
            );
            if (!$confirm) {
                $this->info('Annulé.');
                return self::SUCCESS;
            }
        }

        // ── Wipe en transaction (atomique : si la requête échoue, on
        //    n'a pas non plus supprimé les fichiers disque). ───────────
        DB::transaction(function () use ($autoPanels) {
            // 1. Reset des panneaux auto-géolocalisés UNIQUEMENT (manual + legacy intacts)
            $reset = Panel::whereIn('gps_source', ['pige_provisional', 'pige_confirmed'])
                ->update([
                    'latitude'            => null,
                    'longitude'           => null,
                    'gps_source'          => null,
                    'gps_dispersion_flag' => false,
                    'gps_computed_at'     => null,
                ]);
            $this->line("  ✓ {$reset} panneaux réinitialisés.");

            // 2. DELETE (pas TRUNCATE) → respecte les FK + ne reset pas l'auto-increment
            //    (les références futures par ID ne sont pas un risque, mais l'historique
            //    des logs reste cohérent en gardant la séquence).
            $deleted = DB::table('piges')->delete();
            $this->line("  ✓ {$deleted} piges supprimées de la base.");
        });

        // 3. Suppression des fichiers (HORS transaction — best-effort, ne ré-ouvre
        //    pas la fenêtre de cohérence : la DB est déjà nettoyée).
        if (!$keepFiles && (count($paths) + count($thumbs)) > 0) {
            $bar = $this->output->createProgressBar(count($paths) + count($thumbs));
            $bar->start();
            $deletedFiles = 0;
            foreach ($paths as $p) {
                try {
                    if (Storage::disk('public')->exists($p)) {
                        Storage::disk('public')->delete($p);
                        $deletedFiles++;
                    }
                } catch (\Throwable $e) {
                    // On continue : un fichier manquant ou bloqué ne doit pas
                    // arrêter le nettoyage du reste.
                }
                $bar->advance();
            }
            foreach ($thumbs as $t) {
                try {
                    if (Storage::disk('public')->exists($t)) {
                        Storage::disk('public')->delete($t);
                    }
                } catch (\Throwable $e) {}
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->line("  ✓ {$deletedFiles} fichiers photo supprimés du storage.");
        } elseif ($keepFiles) {
            $this->line('  → Fichiers photo CONSERVÉS (--keep-files).');
        }

        $this->newLine();
        $this->info('═══ Terminé — base prête à recevoir de vraies piges. ═══');
        return self::SUCCESS;
    }
}
