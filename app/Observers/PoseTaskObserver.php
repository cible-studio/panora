<?php

namespace App\Observers;

use App\Models\PoseTask;
use App\Services\PoseService;
use Illuminate\Support\Facades\Log;

/**
 * Auto-envoi du lien WhatsApp au technicien dès qu'il est assigné à
 * une pose (assigned_user_id passé de null → user, ou changé d'un user
 * à un autre).
 *
 * Pose la transition standard du workflow unifié :
 *   1. MP crée la PoseTask + assigne un tech     → WhatsApp auto
 *   2. Tech reçoit le lien, fait l'intervention  → upload pige
 *   3. Pige::created Observer marque la pose COMPLETED + alerte MP
 *
 * Le MP/admin peut toujours forcer le renvoi manuel via le bouton
 * "Renvoyer le lien WhatsApp" (route admin.pose-tasks.notify).
 */
class PoseTaskObserver
{
    public function created(PoseTask $task): void
    {
        // Création avec tech déjà assigné → envoi immédiat
        if ($task->assigned_user_id) {
            $this->sendWhatsAppLink($task);
        }
    }

    public function updated(PoseTask $task): void
    {
        // Pas un changement d'assignation → on n'agit pas
        if (!$task->wasChanged('assigned_user_id')) return;

        // Nouvelle assignation (ou réassignation) avec un tech valide
        if ($task->assigned_user_id) {
            $this->sendWhatsAppLink($task);
        }
    }

    /**
     * Envoie le lien public WhatsApp au technicien assigné.
     * Best-effort : on log mais on ne casse pas le flow si SMTP/WhatsApp
     * échouent. L'admin peut renvoyer manuellement.
     */
    private function sendWhatsAppLink(PoseTask $task): void
    {
        try {
            // Eager load pour que le service ait toutes les données
            $task->loadMissing(['technicien', 'panel.commune', 'campaign']);

            $tech = $task->technicien;
            if (!$tech || empty($tech->whatsapp_number)) {
                Log::info('pose_task.whatsapp.skipped_no_number', [
                    'pose_task_id' => $task->id,
                    'tech_id'      => $task->assigned_user_id,
                ]);
                return;
            }

            // Ne pas spammer un tech qui aurait été déjà notifié il y a
            // peu (cas update successifs sur le même user). Cache 60s.
            $cacheKey = "pose_task_notify_lock_{$task->id}_{$tech->id}";
            if (!\Illuminate\Support\Facades\Cache::add($cacheKey, 1, 60)) {
                return;
            }

            $service = app(PoseService::class);
            $sent    = $service->notifyTechnicianOnWhatsApp($task);

            Log::info('pose_task.whatsapp.auto_sent', [
                'pose_task_id' => $task->id,
                'tech_id'      => $tech->id,
                'sent'         => (bool) $sent,
            ]);
        } catch (\Throwable $e) {
            Log::warning('pose_task.whatsapp.auto_failed', [
                'pose_task_id' => $task->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
