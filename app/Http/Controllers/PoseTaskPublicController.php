<?php
namespace App\Http\Controllers;

use App\Enums\PoseTaskStatus;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\PoseTaskAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Affiche la page mobile du technicien : infos panneau, photos déjà
     * envoyées pour ce panneau+campagne, bouton de progression, bouton
     * "Pose effectuée".
     * GET /pige/{token}
     */
    public function show(string $token)
    {
        $task = $this->resolveTask($token);

        $task->load([
            'panel:id,reference,name,commune_id,format_id,latitude,longitude,adresse,quartier',
            'panel.commune:id,name',
            'panel.format:id,name',
            'campaign:id,name,client_id,start_date,end_date',
            'campaign.client:id,name',
            'technicien:id,name,whatsapp_number',
        ]);

        $piges = Pige::where('panel_id', $task->panel_id)
            ->when($task->campaign_id, fn($q) => $q->where('campaign_id', $task->campaign_id))
            ->orderByDesc('taken_at')
            ->take(20)
            ->get(['id', 'photo_path', 'status', 'taken_at', 'notes', 'rejection_reason']);

        return view('public.pose-task', [
            'task'        => $task,
            'isDone'      => $task->status === PoseTaskStatus::COMPLETED->value,
            'isCancelled' => $task->status === PoseTaskStatus::CANCELLED->value,
            'piges'       => $piges,
        ]);
    }

    /**
     * Met à jour le pourcentage d'avancement.
     * POST /pige/{token}/update
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

        if ($newPercent > 0 && $newPercent < $oldPercent) {
            return response()->json([
                'ok'      => false,
                'message' => "La progression ne peut pas régresser ({$oldPercent} % → {$newPercent} %).",
            ], 422);
        }

        if ($request->filled('note')) {
            $stamp = now()->format('d/m H:i') . ' ['. $newPercent .'%]';
            $task->notes = trim(($task->notes ?? '') . "\n[{$stamp}] " . $request->input('note'));
        }

        $oldStatus = $task->status;
        $task->updateProgress($newPercent);

        PoseTaskAction::log($task->id, 'progress_updated', [
            'old_percent' => $oldPercent,
            'new_percent' => $newPercent,
            'old_status'  => $oldStatus,
            'new_status'  => $task->status,
        ], null, $request->ip());

        Log::info('pose_task.public.progress_updated', [
            'task_id' => $task->id,
            'token'   => substr($token, 0, 8) . '…',
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
     * Transition explicite de statut depuis les boutons de la page mobile.
     * Gère : planifiee→en_route, planifiee/en_route→en_cours, →annulee.
     * POST /pige/{token}/status
     */
    public function setStatus(Request $request, string $token)
    {
        $task = $this->resolveTask($token);

        if ($task->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tâche déjà clôturée.',
                'status'  => $task->status,
            ], 422);
        }

        $request->validate([
            'status'    => 'required|string|in:en_route,en_cours,realisee,annulee',
            'tech_name' => 'nullable|string|max:100',
        ]);

        $newStatusValue = $request->input('status');
        $newStatus = PoseTaskStatus::tryFrom($newStatusValue);

        if (!$newStatus) {
            return response()->json(['ok' => false, 'message' => 'Statut inconnu.'], 422);
        }

        $currentStatus = PoseTaskStatus::tryFrom($task->status);
        $allowed = $currentStatus?->allowedTransitions() ?? [];

        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'ok'      => false,
                'message' => "Transition {$currentStatus?->label()} → {$newStatus->label()} non autorisée.",
            ], 422);
        }

        $oldStatus = $task->status;

        // Transitions spéciales avec effets de bord
        if ($newStatus === PoseTaskStatus::IN_PROGRESS && !$task->started_at) {
            $task->started_at = now();
        }
        if ($newStatus === PoseTaskStatus::COMPLETED) {
            $task->done_at          = now();
            $task->progress_percent = 100;
            if ($task->started_at) {
                $task->real_minutes = max(1, (int) round(
                    $task->started_at->diffInMinutes(now())
                ));
            }
        }

        $task->status = $newStatus->value;
        $task->save();

        PoseTaskAction::log($task->id, 'status_changed', [
            'old_status' => $oldStatus,
            'new_status' => $task->status,
        ], $request->input('tech_name'), $request->ip());

        Log::info('pose_task.public.status_changed', [
            'task_id'    => $task->id,
            'token'      => substr($token, 0, 8) . '…',
            'old_status' => $oldStatus,
            'new_status' => $task->status,
            'tech_name'  => $request->input('tech_name'),
            'ip'         => $request->ip(),
        ]);

        return response()->json([
            'ok'           => true,
            'message'      => $newStatus->icon() . ' ' . $newStatus->label() . '.',
            'status'       => $task->status,
            'status_label' => $newStatus->label(),
            'status_icon'  => $newStatus->icon(),
            'status_color' => $newStatus->color(),
            'is_done'      => $task->status === PoseTaskStatus::COMPLETED->value,
            'started_at'   => $task->started_at?->toIso8601String(),
            'done_at'      => $task->done_at?->toIso8601String(),
        ]);
    }

    /**
     * Bouton "Pose effectuée" — marque la tâche comme réalisée sans passer
     * par le slider. Idempotent.
     * POST /pige/{token}/done
     */
    public function markDone(Request $request, string $token)
    {
        $task = $this->resolveTask($token);

        if ($task->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tâche déjà clôturée.',
                'status'  => $task->status,
            ], 422);
        }

        $oldStatus = $task->status;
        $task->updateProgress(100);
        $task->refresh();

        PoseTaskAction::log($task->id, 'marked_done', [
            'old_status' => $oldStatus,
        ], null, $request->ip());

        Log::info('pose_task.public.marked_done', [
            'task_id' => $task->id,
            'token'   => substr($token, 0, 8) . '…',
            'ip'      => $request->ip(),
        ]);

        // ── Alerte interne : pose réalisée (commercial + admin) ─────────
        $task->loadMissing('panel', 'campaign.client', 'technicien');
        \App\Services\AdminAlertNotifier::notify(
            to: ['commercial_assigned', 'admin'],
            commercialAssigned: $task->campaign?->user,
            severity: 'success',
            title: 'Pose réalisée par le technicien',
            summary: "Le technicien a marqué la pose comme effectuée — campagne « {$task->campaign?->name} ».",
            lines: array_filter([
                'Panneau : ' . ($task->panel?->reference ?? '—'),
                'Client : '  . ($task->campaign?->client?->name ?? '—'),
                'Technicien : ' . ($task->technicien?->name ?? '—'),
                'Date pose : ' . ($task->done_at?->format('d/m/Y H:i') ?? '—'),
            ]),
            ctaLabel: 'Vérifier la fiche pose →',
            ctaUrl: url('/admin/pose-tasks/'.$task->id),
            emoji: '✅',
            footer: 'Tâche pose #' . $task->id,
            dedupKey: 'pose-done-'.$task->id,
        );

        return response()->json([
            'ok'      => true,
            'message' => '✓ Pose marquée comme effectuée. Merci !',
            'status'  => $task->status,
            'done_at' => $task->done_at?->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Upload d'une photo (pige) directement depuis la page pose.
     * POST /pige/{token}/photo
     */
    public function uploadPhoto(Request $request, string $token)
    {
        $task = $this->resolveTask($token);

        if ($task->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tâche clôturée — uploads désactivés.',
            ], 422);
        }

        $data = $request->validate([
            'photo'   => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:51200'],
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'note'    => 'nullable|string|max:500',
        ]);

        $folder   = 'piges/' . ($task->campaign_id ?: 'sans-campagne') . '/' . $task->panel_id;
        $filename = time() . '_' . Str::random(8) . '.' . $request->file('photo')->getClientOriginalExtension();
        $path     = $request->file('photo')->storeAs($folder, $filename, 'public');

        $noteParts = ['[via lien pose]'];
        if ($task->technicien?->name) {
            $noteParts[] = 'Tech: ' . $task->technicien->name;
        }
        if (!empty($data['note'])) {
            $noteParts[] = $data['note'];
        }

        $pige = Pige::create([
            'panel_id'    => $task->panel_id,
            'campaign_id' => $task->campaign_id,
            'user_id'     => $task->campaign?->user_id,
            'photo_path'  => $path,
            'taken_at'    => now(),
            'gps_lat'     => $data['gps_lat'] ?? null,
            'gps_lng'     => $data['gps_lng'] ?? null,
            'notes'       => implode(' · ', $noteParts),
            'status'      => 'en_attente',
        ]);

        PoseTaskAction::log($task->id, 'photo_uploaded', [
            'pige_id' => $pige->id,
            'path'    => $path,
        ], $task->technicien?->name, $request->ip());

        Log::info('pige.public.uploaded_from_pose', [
            'pige_id'  => $pige->id,
            'task_id'  => $task->id,
            'panel_id' => $task->panel_id,
            'ip'       => $request->ip(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Photo envoyée pour vérification.',
            'pige'    => [
                'id'        => $pige->id,
                'photo_url' => Storage::url($path),
                'status'    => $pige->status,
                'taken_at'  => $pige->taken_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Remplace une photo existante par une nouvelle (en un seul POST).
     * Supprime l'ancienne pige, crée la nouvelle. Refuse si la pige
     * est déjà vérifiée par un admin (status='verifie').
     * POST /pige/{token}/photo/{pige}/replace
     */
    public function replacePhoto(Request $request, string $token, int $pigeId)
    {
        $task = $this->resolveTask($token);

        if ($task->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tâche clôturée — remplacement désactivé.',
            ], 422);
        }

        $pige = Pige::where('id', $pigeId)
            ->where('panel_id', $task->panel_id)
            ->when($task->campaign_id, fn($q) => $q->where('campaign_id', $task->campaign_id))
            ->first();

        if (!$pige) {
            return response()->json([
                'ok'      => false,
                'message' => 'Photo introuvable ou non liée à cette intervention.',
            ], 404);
        }

        if ($pige->status === 'verifie') {
            return response()->json([
                'ok'      => false,
                'message' => 'Photo déjà validée — remplacement impossible. Contactez le superviseur.',
            ], 403);
        }

        $data = $request->validate([
            'photo'   => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:51200'],
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'note'    => 'nullable|string|max:500',
        ]);

        // Supprime l'ancienne photo physique
        $oldPath = $pige->photo_path;
        if ($oldPath) {
            try {
                Storage::disk('public')->delete($oldPath);
            } catch (\Throwable $e) {
                Log::warning('pige.replace.old_file_delete_failed', [
                    'pige_id' => $pige->id, 'path' => $oldPath, 'err' => $e->getMessage(),
                ]);
            }
        }

        $folder   = 'piges/' . ($task->campaign_id ?: 'sans-campagne') . '/' . $task->panel_id;
        $filename = time() . '_' . Str::random(8) . '.' . $request->file('photo')->getClientOriginalExtension();
        $newPath  = $request->file('photo')->storeAs($folder, $filename, 'public');

        $noteParts = ['[via lien pose — remplacement]'];
        if ($task->technicien?->name) {
            $noteParts[] = 'Tech: ' . $task->technicien->name;
        }
        if (!empty($data['note'])) {
            $noteParts[] = $data['note'];
        }

        $pige->update([
            'photo_path' => $newPath,
            'taken_at'   => now(),
            'gps_lat'    => $data['gps_lat'] ?? null,
            'gps_lng'    => $data['gps_lng'] ?? null,
            'notes'      => implode(' · ', $noteParts),
            'status'     => 'en_attente', // repasse en attente après remplacement
        ]);

        PoseTaskAction::log($task->id, 'photo_replaced', [
            'pige_id'  => $pige->id,
            'old_path' => $oldPath,
            'new_path' => $newPath,
        ], $task->technicien?->name, $request->ip());

        Log::info('pige.public.replaced_from_pose', [
            'pige_id'  => $pige->id,
            'task_id'  => $task->id,
            'panel_id' => $task->panel_id,
            'ip'       => $request->ip(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Photo remplacée.',
            'pige'    => [
                'id'        => $pige->id,
                'photo_url' => Storage::url($newPath),
                'status'    => $pige->status,
                'taken_at'  => $pige->taken_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Suppression d'une pige depuis la page pose.
     * DELETE /pige/{token}/photo/{pigeId}
     */
    public function deletePhoto(Request $request, string $token, int $pigeId)
    {
        $task = $this->resolveTask($token);

        if ($task->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tâche clôturée — suppression désactivée.',
            ], 422);
        }

        $pige = Pige::where('id', $pigeId)
            ->where('panel_id', $task->panel_id)
            ->when($task->campaign_id, fn($q) => $q->where('campaign_id', $task->campaign_id))
            ->first();

        if (!$pige) {
            return response()->json([
                'ok'      => false,
                'message' => 'Photo introuvable ou non liée à cette intervention.',
            ], 404);
        }

        if ($pige->status === 'verifie') {
            return response()->json([
                'ok'      => false,
                'message' => 'Photo déjà validée — suppression impossible. Contactez le superviseur.',
            ], 403);
        }

        $oldPath = $pige->photo_path;
        if ($oldPath) {
            try {
                Storage::disk('public')->delete($oldPath);
            } catch (\Throwable $e) {
                Log::warning('pige.delete.file_failed', [
                    'pige_id' => $pige->id, 'path' => $oldPath, 'err' => $e->getMessage(),
                ]);
            }
        }

        $pige->delete();

        PoseTaskAction::log($task->id, 'photo_deleted', [
            'pige_id' => $pigeId,
            'path'    => $oldPath,
        ], null, $request->ip());

        Log::info('pige.public.deleted_from_pose', [
            'pige_id'  => $pigeId,
            'task_id'  => $task->id,
            'panel_id' => $task->panel_id,
            'ip'       => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Photo supprimée.']);
    }

    /**
     * Résout le token vers une tâche valide ou abort 404.
     */
    private function resolveTask(string $token): PoseTask
    {
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
