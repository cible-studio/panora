<?php
namespace App\Http\Controllers;

use App\Enums\PoseTaskStatus;
use App\Models\PoseTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PoseTaskPublicController — interface publique mobile pour le technicien.
 *
 * Accès SANS authentification, mais protégé par un public_token unique
 * (32 chars, généré à la création de la tâche). Le technicien reçoit le
 * lien par WhatsApp.
 *
 * Toutes les routes sont throttle:30,1 (30 req/min) côté routes/admin.php
 * pour limiter l'abus en cas de fuite du lien.
 */
class PoseTaskPublicController extends Controller
{
    /**
     * Affiche la page mobile de mise à jour pour le technicien.
     * GET /pose/{token}
     */
    public function show(string $token)
    {
        $task = $this->resolveTask($token);

        $task->load([
            'panel:id,reference,name,commune_id,format_id,latitude,longitude,adresse,quartier',
            'panel.commune:id,name',
            'panel.format:id,name',
            'campaign:id,name,client_id',
            'campaign.client:id,name',
            'technicien:id,name,whatsapp_number',
        ]);

        return view('public.pose-task', [
            'task'    => $task,
            'isDone'  => $task->status === PoseTaskStatus::COMPLETED->value,
            'isCancelled' => $task->status === PoseTaskStatus::CANCELLED->value,
        ]);
    }

    /**
     * Met à jour le pourcentage d'avancement (et déclenche les transitions
     * de statut + horodatage si nécessaire).
     * POST /pose/{token}/update
     */
    public function update(Request $request, string $token)
    {
        $task = $this->resolveTask($token);

        if ($task->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Cette tâche est déjà clôturée. Aucune modification possible.',
            ], 422);
        }

        $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'note'     => 'nullable|string|max:500',
        ]);

        $oldPercent = (int) $task->progress_percent;
        $newPercent = (int) $request->integer('progress');

        // Empêche les régressions involontaires (sauf retour à 0 explicite)
        if ($newPercent > 0 && $newPercent < $oldPercent) {
            return response()->json([
                'ok'      => false,
                'message' => "La progression ne peut pas régresser ({$oldPercent} % → {$newPercent} %).",
            ], 422);
        }

        // Concatène la note technicien si fournie
        if ($request->filled('note')) {
            $stamp = now()->format('d/m H:i') . ' ['. $newPercent .'%]';
            $task->notes = trim(($task->notes ?? '') . "\n[{$stamp}] " . $request->input('note'));
        }

        $task->updateProgress($newPercent);

        Log::info('pose_task.public.progress_updated', [
            'task_id' => $task->id,
            'token'   => substr($token, 0, 8) . '…', // partial pour log non-sensible
            'old'     => $oldPercent,
            'new'     => $newPercent,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'ok'             => true,
            'progress'       => $task->progress_percent,
            'status'         => $task->status,
            'status_label'   => PoseTaskStatus::tryFrom($task->status)?->label() ?? '—',
            'is_done'        => $task->status === PoseTaskStatus::COMPLETED->value,
            'started_at'     => $task->started_at?->toIso8601String(),
            'done_at'        => $task->done_at?->toIso8601String(),
            'real_minutes'   => $task->real_minutes,
            'message'        => $newPercent === 100
                ? '✓ Tâche marquée comme terminée. Merci !'
                : "Progression mise à jour à {$newPercent} %.",
        ]);
    }

    /**
     * Résout le token vers une tâche valide ou abort 404.
     * Centralise la sécurité — toute route publique passe par ici.
     */
    private function resolveTask(string $token): PoseTask
    {
        // Validation stricte du format avant requête (anti-injection / DoS)
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            abort(404, 'Lien invalide.');
        }

        $task = PoseTask::where('public_token', $token)->first();
        if (!$task) {
            abort(404, 'Lien invalide ou tâche introuvable.');
        }

        return $task;
    }
}
