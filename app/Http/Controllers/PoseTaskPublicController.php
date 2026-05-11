<?php
namespace App\Http\Controllers;

use App\Enums\PoseTaskStatus;
use App\Models\Pige;
use App\Models\PoseTask;
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
     * GET /pose/{token}
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

        // Piges existantes pour ce panneau + campagne — affichées comme
        // mini-galerie pour permettre au tech de voir ce qui a déjà été
        // transmis (et éviter de re-photographier).
        $piges = Pige::where('panel_id', $task->panel_id)
            ->when($task->campaign_id, fn($q) => $q->where('campaign_id', $task->campaign_id))
            ->orderByDesc('taken_at')
            ->take(20)
            ->get(['id', 'photo_path', 'status', 'taken_at', 'notes']);

        return view('public.pose-task', [
            'task'        => $task,
            'isDone'      => $task->status === PoseTaskStatus::COMPLETED->value,
            'isCancelled' => $task->status === PoseTaskStatus::CANCELLED->value,
            'piges'       => $piges,
        ]);
    }

    /**
     * Met à jour le pourcentage d'avancement.
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

        if ($request->filled('note')) {
            $stamp = now()->format('d/m H:i') . ' ['. $newPercent .'%]';
            $task->notes = trim(($task->notes ?? '') . "\n[{$stamp}] " . $request->input('note'));
        }

        $task->updateProgress($newPercent);

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
     * Bouton "Pose effectuée" — marque la tâche comme réalisée sans passer
     * par le slider (cas typique : tech qui a fini, n'a pas envie de
     * cliquer 25/50/75/100). Idempotent.
     * POST /pose/{token}/done
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

        $task->updateProgress(100);
        $task->refresh();

        Log::info('pose_task.public.marked_done', [
            'task_id' => $task->id,
            'token'   => substr($token, 0, 8) . '…',
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => '✓ Pose marquée comme effectuée. Merci !',
            'status'  => $task->status,
            'done_at' => $task->done_at?->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Upload d'une photo (pige) directement depuis la page pose — évite
     * au technicien de switcher vers /pige/{token}.
     * POST /pose/{token}/photo
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
            // 50 MB plafond serveur, le client compresse à ~1.5 MB avant
            // envoi via canvas (cf. JS de pose-task.blade.php).
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
            'user_id'     => $task->campaign?->user_id, // commercial créateur
            'photo_path'  => $path,
            'taken_at'    => now(),
            'gps_lat'     => $data['gps_lat'] ?? null,
            'gps_lng'     => $data['gps_lng'] ?? null,
            'notes'       => implode(' · ', $noteParts),
            'status'      => 'en_attente',
        ]);

        Log::info('pige.public.uploaded_from_pose', [
            'pige_id'  => $pige->id,
            'task_id'  => $task->id,
            'panel_id' => $task->panel_id,
            'ip'       => $request->ip(),
        ]);

        return response()->json([
            'ok'        => true,
            'message'   => 'Photo envoyée pour vérification.',
            'pige'      => [
                'id'        => $pige->id,
                'photo_url' => Storage::url($path),
                'status'    => $pige->status,
                'taken_at'  => $pige->taken_at->format('d/m/Y H:i'),
            ],
        ]);
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
