<?php

namespace App\Console\Commands;

use App\Enums\PoseTaskStatus;
use App\Models\PoseTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Annule les tâches de pose « orphelines » : celles dont le panneau
 * n'est plus rattaché à la campagne.
 *
 * Contexte (feedback user 2026-09-21) : jusqu'ici, retirer un panneau
 * d'une campagne (CampaignService::removePanel) détachait le pivot
 * campaign_panels mais laissait les PoseTasks intactes. Résultat :
 * des poses fantômes dans la gestion des poses côté admin ET dans les
 * « à faire » de l'espace technicien, pour des panneaux qui ne font
 * plus partie de la campagne.
 *
 * Le fix applicatif (cancelPendingPoseTasks) traite les retraits
 * futurs. Cette commande rattrape l'historique.
 *
 * ⚠ Les poses RÉALISÉES ne sont jamais touchées : elles constituent
 * une trace historique (pige, performance tech, facturation).
 *
 * Usage :
 *   php artisan posetasks:cancel-orphans --dry-run
 *   php artisan posetasks:cancel-orphans
 */
class CancelOrphanPoseTasks extends Command
{
    protected $signature = 'posetasks:cancel-orphans
                            {--dry-run : Affiche ce qui serait annulé sans rien écrire}';

    protected $description = "Annule les poses dont le panneau n'est plus rattaché à la campagne";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Tâches ouvertes dont le couple (campagne, panneau) n'existe
        // plus dans le pivot campaign_panels.
        $orphans = PoseTask::query()
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->whereNotNull('campaign_id')
            ->whereNotNull('panel_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw(1)
                  ->from('campaign_panels')
                  ->whereColumn('campaign_panels.campaign_id', 'pose_tasks.campaign_id')
                  ->whereColumn('campaign_panels.panel_id', 'pose_tasks.panel_id');
            })
            ->with(['panel:id,reference', 'campaign:id,name'])
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('Aucune pose orpheline — toutes les tâches ouvertes ont un panneau rattaché.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->warn("{$orphans->count()} pose(s) orpheline(s) détectée(s) :");
        $this->line('');

        foreach ($orphans as $task) {
            $this->line(sprintf(
                '  · Pose #%d — %s · campagne « %s » (statut : %s)',
                $task->id,
                $task->panel?->reference ?? '#' . $task->panel_id,
                $task->campaign?->name ?? '#' . $task->campaign_id,
                $task->status
            ));
        }

        $this->line('');

        if ($dryRun) {
            $this->warn("[DRY-RUN] {$orphans->count()} pose(s) seraient annulées. Relance sans --dry-run pour appliquer.");
            return self::SUCCESS;
        }

        $note = '[Auto] Annulée le ' . now()->format('d/m/Y à H:i')
              . ' — panneau retiré de la campagne (rattrapage historique).';

        $updated = 0;

        DB::transaction(function () use ($orphans, $note, &$updated) {
            foreach ($orphans as $task) {
                $task->forceFill([
                    'status' => PoseTaskStatus::CANCELLED->value,
                    'notes'  => trim(($task->notes ? $task->notes . "\n" : '') . $note),
                ])->save();
                $updated++;
            }
        });

        $this->info("✓ {$updated} pose(s) annulée(s). Elles disparaissent de la gestion des poses et de l'espace tech.");

        Log::info('posetasks.cancel_orphans.done', [
            'candidates' => $orphans->count(),
            'updated'    => $updated,
        ]);

        return self::SUCCESS;
    }
}
