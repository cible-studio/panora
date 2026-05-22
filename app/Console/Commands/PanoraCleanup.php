<?php

namespace App\Console\Commands;

use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nettoyage périodique des entités orphelines / archivées.
 *
 * Par défaut : --dry-run (simulation). Pour exécuter réellement,
 * passer --apply. Aucune destruction n'est jamais lancée
 * automatiquement par un cron.
 *
 * Règles appliquées :
 *
 *   1. Archive les piges dont la campagne est soft-deleted MAIS
 *      dont archived_at est encore NULL (filet pour le cas où
 *      l'observer a manqué un delete — historique, bugs, etc.).
 *
 *   2. Hard-delete les réservations techniques (is_technical=true)
 *      soft-deleted depuis > 90 j. Ces résas n'ont pas de valeur
 *      métier en elles-mêmes (juste un wrapper pour les pivots), et
 *      les piges associées sont déjà conservées séparément.
 *
 *   3. Hard-delete les pose_tasks ANNULEE depuis > 90 j ET SANS
 *      pige associée. Si une pose annulée a une pige, on la garde
 *      (audit : "pourquoi cette pose a-t-elle été annulée alors
 *      qu'une photo a été uploadée ?").
 *
 * Tout est loggé. Mode --apply demande confirmation interactive
 * sauf si --force est passé.
 */
class PanoraCleanup extends Command
{
    protected $signature = 'panora:cleanup
                            {--apply       : Exécute réellement le nettoyage (sinon simulation)}
                            {--force       : Pas de confirmation interactive en mode --apply}
                            {--days-tech=90 : Seuil de purge pour les réservations techniques}
                            {--days-pose=90 : Seuil de purge pour les pose_tasks annulées}';

    protected $description = 'Nettoie les entités orphelines / archivées (piges, poses, résas techniques)';

    public function handle(): int
    {
        $apply       = (bool) $this->option('apply');
        $daysTech    = (int)  $this->option('days-tech');
        $daysPose    = (int)  $this->option('days-pose');

        $mode = $apply ? '🔧 MODE APPLY (modifications réelles)' : '🔍 MODE SIMULATION (--dry-run)';
        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  PANORA CLEANUP — ' . $mode);
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // ── 1) Piges à archiver (campagne soft-deleted mais pige pas archivée) ──
        $pigesToArchive = Pige::whereNull('archived_at')
            ->whereHas('campaign', fn($q) => $q->onlyTrashed())
            ->pluck('id');

        $this->line(' 1) Piges à archiver (campagne supprimée mais archived_at NULL)');
        $this->line('    Concernées : ' . $pigesToArchive->count());

        // ── 2) Réservations techniques purgeables ───────────────────
        $techToPurge = Reservation::onlyTrashed()
            ->where('is_technical', true)
            ->where('deleted_at', '<', now()->subDays($daysTech))
            ->pluck('id');

        $this->line(' 2) Réservations techniques soft-deleted > ' . $daysTech . ' j');
        $this->line('    Concernées : ' . $techToPurge->count());

        // ── 3) Pose tasks annulées purgeables (sans pige associée) ──
        $poseTaskCandidates = PoseTask::where('status', 'annulee')
            ->where('updated_at', '<', now()->subDays($daysPose));

        // Exclure celles qui ont au moins une pige (preuve terrain).
        $pigeTaskIds = Pige::whereNotNull('pose_task_id')
            ->pluck('pose_task_id')
            ->unique();

        $posesToPurge = (clone $poseTaskCandidates)
            ->whereNotIn('id', $pigeTaskIds)
            ->pluck('id');
        $posesKeptForAudit = (clone $poseTaskCandidates)
            ->whereIn('id', $pigeTaskIds)
            ->count();

        $this->line(' 3) Pose tasks annulées > ' . $daysPose . ' j');
        $this->line('    Purgeables (sans pige) : ' . $posesToPurge->count());
        if ($posesKeptForAudit > 0) {
            $this->line('    Conservées (audit pige) : ' . $posesKeptForAudit);
        }
        $this->newLine();

        $totalActions = $pigesToArchive->count() + $techToPurge->count() + $posesToPurge->count();

        if ($totalActions === 0) {
            $this->info(' ✅ Rien à nettoyer.');
            $this->newLine();
            return Command::SUCCESS;
        }

        if (!$apply) {
            $this->warn(' ⚠️ Simulation uniquement — rien n\'a été modifié.');
            $this->line('    Pour exécuter : php artisan panora:cleanup --apply');
            $this->newLine();
            return Command::SUCCESS;
        }

        // ── APPLY : confirmation interactive sauf --force ───────────
        if (!$this->option('force')) {
            $this->warn(' Tu vas effectuer les actions ci-dessus DE FAÇON IRRÉVERSIBLE.');
            if (!$this->confirm(' Continuer ?', false)) {
                $this->info(' Annulé.');
                return Command::SUCCESS;
            }
        }

        // ── Exécution ──────────────────────────────────────────────
        DB::beginTransaction();
        try {
            // 1) Archive piges
            if ($pigesToArchive->isNotEmpty()) {
                Pige::whereIn('id', $pigesToArchive)->update(['archived_at' => now()]);
                $this->info(" ✓ {$pigesToArchive->count()} pige(s) archivée(s).");
            }

            // 2) Hard-delete réservations techniques
            if ($techToPurge->isNotEmpty()) {
                // forceDelete bypass softdelete → vrai DELETE SQL
                $purged = Reservation::onlyTrashed()->whereIn('id', $techToPurge)->forceDelete();
                $this->info(" ✓ {$purged} réservation(s) technique(s) supprimée(s) définitivement.");
            }

            // 3) Hard-delete pose_tasks annulées sans pige
            if ($posesToPurge->isNotEmpty()) {
                $purged = PoseTask::whereIn('id', $posesToPurge)->delete();
                $this->info(" ✓ {$purged} pose task(s) annulée(s) supprimée(s).");
            }

            DB::commit();

            Log::warning('panora.cleanup.applied', [
                'piges_archived'      => $pigesToArchive->count(),
                'tech_resa_purged'    => $techToPurge->count(),
                'pose_tasks_purged'   => $posesToPurge->count(),
                'days_tech'           => $daysTech,
                'days_pose'           => $daysPose,
                'actor'               => 'cli',
            ]);

            $this->newLine();
            $this->info(' 🎉 Nettoyage terminé.');
            $this->newLine();
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error(' ❌ Erreur — rollback effectué : ' . $e->getMessage());
            Log::error('panora.cleanup.failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
