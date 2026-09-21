<?php

namespace App\Console\Commands;

use App\Enums\PoseTaskStatus;
use App\Models\Pige;
use App\Models\PoseTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill : clôture les PoseTasks qui ont déjà une pige valide mais
 * qui sont restées en statut non-terminal.
 *
 * Contexte (feedback user 2026-09-21, urgent) : entre août et
 * septembre 2026, la clôture auto de la PoseTask sur création de pige
 * avait été retirée. Résultat : toutes les poses dont la pige a été
 * ajoutée manuellement par le media planner depuis le back-office sont
 * restées « à faire » dans l'espace technicien, alors que la preuve de
 * pose existait.
 *
 * Cette commande rattrape l'historique. Le nouveau comportement
 * (PigeObserver::created → closePoseTask) gère les piges futures.
 *
 * Périmètre : uniquement les tâches ayant AU MOINS une pige non
 * rejetée. Une tâche dont toutes les piges ont été rejetées reste
 * ouverte (c'est le comportement voulu).
 *
 * Usage :
 *   php artisan posetasks:backfill-from-piges --dry-run
 *   php artisan posetasks:backfill-from-piges
 */
class BackfillPoseTasksFromPiges extends Command
{
    protected $signature = 'posetasks:backfill-from-piges
                            {--dry-run : Affiche ce qui serait modifié sans rien écrire}
                            {--relink : Rebranche d\'abord les piges rattachées à une pose déjà clôturée alors qu\'un rechange ouvert existe}';

    protected $description = "Clôture les poses qui ont déjà une pige valide mais sont restées 'à faire'";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('relink')) {
            $this->relinkPigesToOpenTasks($dryRun);
        }

        // Tâches non terminales qui ont au moins une pige non rejetée.
        $tasks = PoseTask::query()
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                  ->from('piges')
                  ->whereColumn('piges.pose_task_id', 'pose_tasks.id')
                  ->where('piges.status', '!=', 'rejete');
            })
            ->with(['panel:id,reference'])
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('Rien à rattraper — toutes les poses avec pige valide sont déjà clôturées.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info("{$tasks->count()} pose(s) à clôturer :");
        $this->line('');

        $updated = 0;

        foreach ($tasks as $task) {
            // Pige de référence : la plus ancienne non rejetée (c'est
            // elle qui atteste de la date réelle de la pose).
            $pige = Pige::where('pose_task_id', $task->id)
                ->where('status', '!=', 'rejete')
                ->orderBy('taken_at')
                ->orderBy('id')
                ->first();

            if (!$pige) {
                continue; // garde-fou, ne devrait pas arriver via le whereExists
            }

            $doneAt = $pige->taken_at ?? $pige->created_at ?? now();
            $ref    = $task->panel?->reference ?? '#' . $task->panel_id;

            $this->line(sprintf(
                '  · Pose #%d (%s) — pige #%d du %s',
                $task->id,
                $ref,
                $pige->id,
                $doneAt->format('d/m/Y')
            ));

            if ($dryRun) {
                continue;
            }

            try {
                $task->forceFill([
                    'status'           => PoseTaskStatus::COMPLETED->value,
                    'done_at'          => $doneAt,
                    'progress_percent' => 100,
                    // On ne connaît pas l'auteur réel a posteriori : on
                    // laisse completed_by_user_id à NULL et on marque la
                    // source 'admin' (la majorité de ces cas viennent de
                    // saisies back-office du MP). completedByLabel()
                    // retombera sur le technicien assigné pour l'affichage.
                    'completed_source' => 'admin',
                ])->save();

                $updated++;
            } catch (\Throwable $e) {
                $this->error("    ✗ Échec pose #{$task->id} : " . $e->getMessage());
                Log::warning('posetasks.backfill_failed', [
                    'pose_task_id' => $task->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        $this->line('');

        if ($dryRun) {
            $this->warn("[DRY-RUN] {$tasks->count()} pose(s) seraient clôturées. Relance sans --dry-run pour appliquer.");
            return self::SUCCESS;
        }

        $this->info("✓ {$updated} pose(s) clôturée(s). Elles disparaissent des « à faire » de l'espace tech.");

        Log::info('posetasks.backfill_from_piges.done', [
            'candidates' => $tasks->count(),
            'updated'    => $updated,
        ]);

        return self::SUCCESS;
    }

    /**
     * Rebranche les piges mal rattachées.
     *
     * Cas traité : sur un panneau avec rechange, une pige uploadée
     * APRÈS la création du rechange s'est rattachée à l'ancienne pose
     * (déjà réalisée) au lieu du rechange encore ouvert. Résultat : le
     * rechange reste éternellement « à faire » côté technicien alors
     * que sa photo existe.
     *
     * Critère de rebranchement — tous doivent être réunis :
     *   - la pige pointe vers une pose RÉALISÉE
     *   - cette pose a été REMPLACÉE (replaced_at non nul)
     *   - une pose plus récente, encore ouverte, existe sur le même
     *     couple panneau × campagne
     *   - la pige a été prise APRÈS la création de cette pose ouverte
     *     (sinon elle documente bien l'ancienne pose, pas le rechange)
     */
    private function relinkPigesToOpenTasks(bool $dryRun): void
    {
        $this->line('');
        $this->info('─── Étape 1 : rebranchement des piges mal rattachées ───');

        $candidates = Pige::query()
            ->whereNotNull('pose_task_id')
            ->whereHas('poseTask', function ($q) {
                $q->where('status', PoseTaskStatus::COMPLETED->value)
                  ->whereNotNull('replaced_at');
            })
            ->with(['poseTask', 'panel:id,reference'])
            ->get();

        if ($candidates->isEmpty()) {
            $this->line('  Aucune pige à rebrancher.');
            $this->line('');
            return;
        }

        $relinked = 0;

        foreach ($candidates as $pige) {
            $openTask = PoseTask::where('panel_id', $pige->panel_id)
                ->where('campaign_id', $pige->campaign_id)
                ->whereNotIn('status', [
                    PoseTaskStatus::COMPLETED->value,
                    PoseTaskStatus::CANCELLED->value,
                ])
                ->where('id', '>', $pige->pose_task_id)
                ->orderByDesc('id')
                ->first();

            if (!$openTask) {
                continue;
            }

            // La pige doit être postérieure à la création de la pose
            // ouverte, sinon elle documente l'ancienne pose.
            $pigeDate = $pige->taken_at ?? $pige->created_at;
            if ($pigeDate && $openTask->created_at && $pigeDate->lt($openTask->created_at)) {
                continue;
            }

            $this->line(sprintf(
                '  · Pige #%d (%s) : tâche #%d (réalisée, remplacée) → tâche #%d (%s)',
                $pige->id,
                $pige->panel?->reference ?? '#' . $pige->panel_id,
                $pige->pose_task_id,
                $openTask->id,
                $openTask->pose_kind ?? 'initiale'
            ));

            if (!$dryRun) {
                $pige->forceFill(['pose_task_id' => $openTask->id])->saveQuietly();
                $relinked++;
            }
        }

        if ($dryRun) {
            $this->warn('  [DRY-RUN] Aucune pige rebranchée.');
        } else {
            $this->info("  ✓ {$relinked} pige(s) rebranchée(s).");
            Log::info('piges.relinked_to_open_task', ['count' => $relinked]);
        }

        $this->line('');
    }
}
