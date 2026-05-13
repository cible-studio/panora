<?php

namespace App\Observers;

use App\Enums\PoseTaskStatus;
use App\Models\Pige;
use Illuminate\Support\Facades\Log;

/**
 * Auto-transition PoseTask quand une Pige est uploadée par le technicien.
 *
 * Workflow unifié :
 *   1. Tech upload sa photo → Pige::created
 *   2. Observer ici : la PoseTask liée bascule à COMPLETED
 *   3. Alerte MP/admin pour validation
 *
 * Si la pige est créée sans lien explicite (pose_task_id NULL), on tente
 * de la rattacher en cherchant la PoseTask (panel + campaign) — couvre
 * le cas legacy de l'ancien lien campagne.
 */
class PigeObserver
{
    public function creating(Pige $pige): void
    {
        // Backfill automatique du pose_task_id si manquant — recherche
        // la PoseTask (panel + campaign) la plus récente.
        if (!$pige->pose_task_id && $pige->panel_id && $pige->campaign_id) {
            $poseTask = \App\Models\PoseTask::where('panel_id', $pige->panel_id)
                ->where('campaign_id', $pige->campaign_id)
                ->latest()
                ->first();
            if ($poseTask) {
                $pige->pose_task_id = $poseTask->id;
            }
        }
    }

    public function created(Pige $pige): void
    {
        // Pas de PoseTask liée → rien à transitionner.
        $poseTask = $pige->poseTask;
        if (!$poseTask) return;

        // Bascule la pose à TERMINEE si pas déjà à un statut final.
        $finalStatuses = [
            PoseTaskStatus::COMPLETED->value,
            PoseTaskStatus::CANCELLED->value,
        ];
        $currentStatus = $poseTask->status?->value ?? $poseTask->status;

        if (in_array($currentStatus, $finalStatuses, true)) {
            return;
        }

        $poseTask->forceFill([
            'status'  => PoseTaskStatus::COMPLETED->value,
            'done_at' => $poseTask->done_at ?? now(),
        ])->save();

        Log::info('pose_task.auto_completed_from_pige', [
            'pose_task_id' => $poseTask->id,
            'pige_id'      => $pige->id,
            'panel_id'     => $pige->panel_id,
            'campaign_id'  => $pige->campaign_id,
        ]);

        // Alerte MP/admin : nouvelle pige à valider sur le terrain.
        try {
            \App\Services\AlertService::notify(
                'avancement_pose',
                '📸 Nouvelle pige à valider — ' . ($pige->panel?->reference ?? '#' . $pige->panel_id),
                'Le technicien a uploadé une photo pour la pose du panneau '
                    . ($pige->panel?->reference ?? '#' . $pige->panel_id)
                    . ($pige->campaign ? ' (campagne « ' . $pige->campaign->name . ' »)' : '')
                    . '. À valider depuis l\'admin.',
                $pige,
                ['lien' => route('admin.piges.show', $pige)]
            );
        } catch (\Throwable $e) {
            Log::warning('pige.alert_failed', ['error' => $e->getMessage()]);
        }
    }
}
