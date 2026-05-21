<?php

namespace App\Http\Controllers;

use App\Enums\PoseTaskStatus;
use App\Models\PoseTask;
use App\Models\Pige;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Espace personnel d'un technicien terrain — vue publique sans login.
 *
 * Le tech ouvre /tech/{token}/poses depuis son WhatsApp / SMS. Il y voit
 * EXCLUSIVEMENT les poses qui lui sont assignées (assigned_user_id ===
 * user_id résolu par le token), TOUTES campagnes confondues, regroupées
 * par date d'échéance.
 *
 * Remplace l'ancien lien /pige/{campaign.pige_token} qui montrait tous
 * les panneaux de la campagne — y compris ceux d'autres techs.
 *
 * Sécurité : pas d'auth, le token est le secret partagé. Throttle au
 * niveau route (60 req/min) pour éviter le scrape.
 */
class TechSpaceController extends Controller
{
    /**
     * GET /tech/{token}/poses
     * Page principale — liste des poses du tech, groupées par date.
     */
    public function show(string $token)
    {
        $tech = User::where('tech_public_token', $token)->first();
        if (!$tech) {
            abort(404, 'Lien invalide ou expiré.');
        }

        // Charge toutes les poses non-terminales du tech (planifiées,
        // en_route, en_cours). Les réalisées et annulées sont masquées
        // par défaut — on peut les afficher en historique sur demande.
        $activeTasks = PoseTask::with([
                'panel:id,reference,name,commune_id,format_id',
                'panel.commune:id,name',
                'panel.format:id,name',
                'campaign:id,name,start_date,end_date,client_id',
                'campaign.client:id,name',
            ])
            ->where('assigned_user_id', $tech->id)
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->orderBy('scheduled_at')
            ->get();

        // Stats rapides pour le bandeau d'en-tête
        $totalActive  = $activeTasks->count();
        $totalDone    = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->count();

        // Groupage par jour calendaire (scheduled_at ou created_at en fallback)
        $today = Carbon::today();
        $groupedByDay = $activeTasks->groupBy(function ($task) use ($today) {
            $date = $task->scheduled_at ?? $task->created_at;
            $day = Carbon::parse($date)->startOfDay();
            if ($day->lt($today))   return 'overdue';
            if ($day->isToday())    return 'today';
            if ($day->isTomorrow()) return 'tomorrow';
            if ($day->lte($today->copy()->addDays(7))) return 'week';
            return 'later';
        });

        return view('public.tech-space', [
            'tech'         => $tech,
            'totalActive'  => $totalActive,
            'totalDone'    => $totalDone,
            'groupedByDay' => $groupedByDay,
            'token'        => $token,
        ]);
    }

    /**
     * POST /tech/{token}/poses/{task}/status
     * Transition de statut depuis l'interface tech.
     * Accepte : en_route, en_cours, realisee.
     */
    public function updateStatus(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide.'], 404);

        $task = PoseTask::where('id', $taskId)
            ->where('assigned_user_id', $tech->id)
            ->first();
        if (!$task) return response()->json(['ok' => false, 'error' => 'Pose introuvable.'], 404);

        $data = $request->validate([
            'status' => 'required|in:en_route,en_cours,realisee',
        ]);

        $newStatus = PoseTaskStatus::from($data['status']);
        $current   = $task->status;

        // Vérifie la transition est autorisée (cf. PoseTaskStatus::allowedTransitions)
        if (!in_array($newStatus, $current->allowedTransitions(), true)) {
            return response()->json([
                'ok' => false,
                'error' => "Transition interdite : {$current->label()} → {$newStatus->label()}.",
            ], 422);
        }

        $update = ['status' => $newStatus->value];
        if ($newStatus === PoseTaskStatus::COMPLETED) {
            $update['done_at'] = now();
        }

        $task->update($update);

        Log::info('tech.space.status_changed', [
            'task_id'    => $task->id,
            'tech_id'    => $tech->id,
            'old_status' => $current->value,
            'new_status' => $newStatus->value,
            'ip'         => $request->ip(),
        ]);

        return response()->json([
            'ok'            => true,
            'status'        => $newStatus->value,
            'status_label'  => $newStatus->label(),
            'status_icon'   => $newStatus->icon(),
            'status_color'  => $newStatus->color(),
            'is_terminal'   => $newStatus->isTerminal(),
            'message'       => "Statut mis à jour : {$newStatus->label()}.",
        ]);
    }

    /**
     * POST /tech/{token}/poses/{task}/photo
     * Upload de la photo de pige (preuve d'affichage).
     * Marque AUSSI la pose comme réalisée si elle ne l'était pas déjà.
     */
    public function uploadPhoto(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide.'], 404);

        $task = PoseTask::with(['campaign', 'panel'])
            ->where('id', $taskId)
            ->where('assigned_user_id', $tech->id)
            ->first();
        if (!$task) return response()->json(['ok' => false, 'error' => 'Pose introuvable.'], 404);

        $data = $request->validate([
            'photo'    => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:51200'],
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:500',
        ]);

        if (!$task->campaign_id || !$task->panel_id) {
            return response()->json(['ok' => false, 'error' => 'Pose mal configurée.'], 422);
        }

        // Stockage photo
        $folder   = "piges/{$task->campaign_id}/{$task->panel_id}";
        $ext      = $request->file('photo')->getClientOriginalExtension();
        $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $ext;
        $path     = $request->file('photo')->storeAs($folder, $filename, 'public');

        // Création pige (preuve de pose)
        $noteParts = ['[via espace tech]'];
        $noteParts[] = 'Tech: ' . $tech->name;
        if (!empty($data['notes'])) $noteParts[] = $data['notes'];

        $pige = Pige::create([
            'panel_id'    => $task->panel_id,
            'campaign_id' => $task->campaign_id,
            'user_id'     => $task->campaign->user_id ?? $tech->id,
            'photo_path'  => $path,
            'taken_at'    => now(),
            'gps_lat'     => $data['gps_lat'] ?? null,
            'gps_lng'     => $data['gps_lng'] ?? null,
            'notes'       => implode(' · ', $noteParts),
            'status'      => 'en_attente',
        ]);

        // Marque la tâche comme réalisée si pas déjà fait
        if (!$task->status->isTerminal()) {
            $task->update([
                'status'  => PoseTaskStatus::COMPLETED->value,
                'done_at' => now(),
            ]);
        }

        Log::info('tech.space.photo_uploaded', [
            'task_id'     => $task->id,
            'pige_id'     => $pige->id,
            'tech_id'     => $tech->id,
            'campaign_id' => $task->campaign_id,
            'panel_id'    => $task->panel_id,
            'has_gps'     => !empty($data['gps_lat']),
            'ip'          => $request->ip(),
        ]);

        // Alerte interne : nouvelle pige à valider
        \App\Services\AdminAlertNotifier::notify(
            to: ['mediaplanner', 'admin'],
            severity: 'info',
            title: 'Nouvelle pige à valider',
            summary: "Pige uploadée par {$tech->name} sur la campagne « {$task->campaign->name} ».",
            lines: array_filter([
                'Panneau : ' . ($task->panel?->reference ?? '—'),
                'Technicien : ' . $tech->name,
                'Date : ' . $pige->taken_at->format('d/m/Y H:i'),
                $data['gps_lat'] ? "GPS : {$data['gps_lat']}, {$data['gps_lng']}" : null,
            ]),
            ctaLabel: 'Valider la pige →',
            ctaUrl: url('/admin/piges/' . $pige->id),
            footer: 'Pige #' . $pige->id,
            emoji: '📸',
            dedupKey: 'pige-uploaded-' . $pige->id,
        );

        return response()->json([
            'ok'        => true,
            'pige_id'   => $pige->id,
            'photo_url' => Storage::url($path),
            'message'   => '✅ Photo envoyée. Pose validée — en attente vérification.',
            'task_done' => true,
        ]);
    }
}
