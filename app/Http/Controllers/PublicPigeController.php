<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Page publique de prise de piges pour une campagne.
 *
 * Le commercial génère un token unique sur la fiche campagne, le partage
 * au technicien terrain (lien WhatsApp / SMS / QR papier) ; le technicien
 * ouvre l'URL sur son téléphone, voit la liste des panneaux de la
 * campagne et upload une photo (+ GPS auto si autorisé) pour chacun.
 *
 * Garde-fous :
 *   - throttle middleware appliqué côté routes pour éviter le scrape
 *   - status terminal (terminé / annulé) → page bloquée en lecture
 *   - photo size + mime types validés
 *   - aucun login requis (token = secret partagé)
 */
class PublicPigeController extends Controller
{
    public function show(string $token, \App\Services\TechUrlResolverService $resolver)
    {
        // ─── SM2a Lot 2.1B — REDIRECT UNIFIÉ vers tech.space ─────
        // Si le token résout vers une PoseTask avec tech assigné + token
        // public actif → on redirige 301 vers le nouveau dashboard avec
        // ?focus=task_X qui auto-ouvre le drawer T2. Les anciens liens
        // WhatsApp partagés en masse continuent donc de marcher mais
        // aterrissent désormais sur l'UI moderne.
        //
        // Cas où le resolver retourne null (→ fall-through legacy) :
        //   - Token != 32 chars (campagne 48 chars : legacy conservé)
        //   - PoseTask sans assigned_user_id (tech_name_self : legacy)
        //   - tech_public_token vide ou is_active=false
        $newUrl = $resolver->resolve($token);
        if ($newUrl !== null) {
            return redirect($newUrl, 301);
        }

        // ─── DISPATCH UNIFIÉ ────────────────────────────────────────
        // Un seul format d'URL côté technicien : /pige/{token}. Le token
        // peut représenter soit une intervention unique (PoseTask, 32
        // chars), soit une campagne complète multi-panneaux (Campaign
        // pige_token, 48 chars). On essaie pose_task d'abord (cas le
        // plus courant), fallback campagne.
        if (strlen($token) === 32 && preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            $task = \App\Models\PoseTask::where('public_token', $token)->first();
            if ($task) {
                return app(PoseTaskPublicController::class)->show($token);
            }
        }

        // Cas multi-panneaux campagne (legacy / lien commercial→tech général).
        $campaign = Campaign::where('pige_token', $token)
            ->with([
                'client:id,name',
                'panels.commune:id,name',
                'panels.format:id,name',
                'panels.photos',
            ])
            ->first();

        if (!$campaign) {
            abort(404, 'Lien invalide ou révoqué.');
        }

        $isClosed = in_array($campaign->status->value, ['termine', 'annule']);

        // Charge les piges existantes pour afficher l'état panneau par panneau.
        $existingPiges = Pige::where('campaign_id', $campaign->id)
            ->latest('taken_at')
            ->get(['id', 'panel_id', 'status', 'taken_at', 'photo_path', 'gps_lat', 'gps_lng', 'notes', 'rejection_reason'])
            ->groupBy('panel_id');

        // Lot 9.3 — Statut pose par panneau : index PoseTask par panel_id
        // pour afficher "À poser" / "Posé" et l'action "Pose effectuée".
        $poseTasks = \App\Models\PoseTask::where('campaign_id', $campaign->id)
            ->get(['id', 'panel_id', 'status', 'done_at'])
            ->keyBy('panel_id');

        return view('public.pige-collect', [
            'campaign'      => $campaign,
            'token'         => $token,
            'isClosed'      => $isClosed,
            'existingPiges' => $existingPiges,
            'poseTasks'     => $poseTasks,
        ]);
    }

    /**
     * Lot 9.3 — Marque une tâche de pose comme effectuée depuis le lien
     * public. Utilisé par le bouton "✓ Pose effectuée" de la page pige
     * terrain. Idempotent : si la tâche est déjà completed, retourne ok
     * sans erreur (le tech peut re-cliquer sans risque).
     */
    public function markPosed(Request $request, string $token)
    {
        $campaign = Campaign::where('pige_token', $token)->first();

        if (!$campaign) {
            return response()->json(['ok' => false, 'message' => 'Lien invalide.'], 404);
        }

        if (in_array($campaign->status->value, ['termine', 'annule'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Cette campagne est ' . $campaign->status->label() . " — pose ne peut plus être validée.",
            ], 403);
        }

        $data = $request->validate([
            'panel_id' => ['required', 'integer', 'exists:panels,id'],
            'tech_name'=> 'nullable|string|max:100',
        ]);

        $task = \App\Models\PoseTask::where('campaign_id', $campaign->id)
            ->where('panel_id', $data['panel_id'])
            ->whereNotIn('status', ['annulee'])
            ->first();

        if (!$task) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucune tâche de pose trouvée pour ce panneau.',
            ], 404);
        }

        // Idempotent : si déjà réalisée, on retourne ok mais on log pour info
        if ($task->status === 'realisee') {
            return response()->json([
                'ok'      => true,
                'message' => 'Pose déjà validée.',
                'task_id' => $task->id,
                'status'  => 'realisee',
                'done_at' => $task->done_at?->format('d/m/Y H:i'),
            ]);
        }

        $oldNotes = $task->notes ?: '';
        $newNotes = trim(
            $oldNotes
            . ($oldNotes ? "\n" : '')
            . '[via lien public] Validation pose'
            . (!empty($data['tech_name']) ? ' par ' . $data['tech_name'] : '')
            . ' le ' . now()->format('d/m/Y H:i')
        );

        $task->update([
            'status'  => 'realisee',
            'done_at' => now(),
            'notes'   => $newNotes,
        ]);

        Log::info('pose_task.public.completed', [
            'task_id'     => $task->id,
            'panel_id'    => $data['panel_id'],
            'campaign_id' => $campaign->id,
            'tech_name'   => $data['tech_name'] ?? null,
            'ip'          => $request->ip(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Pose validée. Le superviseur en est notifié.',
            'task_id' => $task->id,
            'status'  => 'realisee',
            'done_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    public function upload(Request $request, string $token)
    {
        $campaign = Campaign::where('pige_token', $token)->first();

        if (!$campaign) {
            return response()->json(['ok' => false, 'message' => 'Lien invalide.'], 404);
        }

        if (in_array($campaign->status->value, ['termine', 'annule'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Cette campagne est ' . $campaign->status->label() . " — uploads désactivés.",
            ], 403);
        }

        $data = $request->validate([
            'panel_id' => ['required', 'integer', 'exists:panels,id'],
            // Plafond serveur à 50 MB pour couvrir les photos brutes
            // smartphone (jusqu'à 30-40 MB en HEIC/PRO mode). Le client
            // compresse à ~1-2 MB en JPEG avant upload (cf. canvas resize
            // côté JS) → l'upload reste rapide même en 4G faible.
            'photo'    => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:51200'], // 50 MB
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:500',
            'tech_name'=> 'nullable|string|max:100', // nom du technicien (libre)
        ]);

        // Vérifier que le panel appartient bien à la campagne
        $belongsToCampaign = $campaign->panels()->where('panels.id', $data['panel_id'])->exists();
        if (!$belongsToCampaign) {
            return response()->json(['ok' => false, 'message' => 'Ce panneau n\'appartient pas à cette campagne.'], 422);
        }

        $folder = "piges/{$campaign->id}/{$data['panel_id']}";
        $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $request->file('photo')->getClientOriginalExtension();
        $path = $request->file('photo')->storeAs($folder, $filename, 'public');

        // Notes : on préfixe pour identifier la source publique + nom du
        // technicien si fourni, utile pour la traçabilité côté admin.
        $noteParts = [];
        $noteParts[] = '[via lien public]';
        if (!empty($data['tech_name'])) {
            $noteParts[] = 'Tech: ' . $data['tech_name'];
        }
        if (!empty($data['notes'])) {
            $noteParts[] = $data['notes'];
        }

        $pige = Pige::create([
            'panel_id'    => $data['panel_id'],
            'campaign_id' => $campaign->id,
            'user_id'     => $campaign->user_id, // attribution au commercial créateur
            'photo_path'  => $path,
            'taken_at'    => now(),
            'gps_lat'     => $data['gps_lat'] ?? null,
            'gps_lng'     => $data['gps_lng'] ?? null,
            'notes'       => implode(' · ', $noteParts),
            'status'      => 'en_attente',
        ]);

        Log::info('pige.public.uploaded', [
            'pige_id'     => $pige->id,
            'campaign_id' => $campaign->id,
            'panel_id'    => $data['panel_id'],
            'tech_name'   => $data['tech_name'] ?? null,
            'ip'          => $request->ip(),
        ]);

        // ── Alerte interne : nouvelle pige à valider (MP / admin) ──────
        $panel = \App\Models\Panel::find($data['panel_id']);
        \App\Services\AdminAlertNotifier::notify(
            to: ['mediaplanner', 'admin'],
            severity: 'info',
            title: 'Nouvelle pige à valider',
            summary: "Une pige photo a été uploadée pour la campagne « {$campaign->name} ».",
            lines: array_filter([
                'Panneau : ' . ($panel?->reference ?? '—'),
                $data['tech_name'] ? 'Technicien : ' . $data['tech_name'] : null,
                'Date : ' . $pige->taken_at->format('d/m/Y H:i'),
                $data['gps_lat'] ? "GPS : {$data['gps_lat']}, {$data['gps_lng']}" : null,
            ]),
            ctaLabel: 'Valider la pige →',
            ctaUrl: url('/admin/piges/'.$pige->id),
            footer: 'Pige #' . $pige->id,
            emoji: '📸',
            dedupKey: 'pige-uploaded-'.$pige->id,
        );

        return response()->json([
            'ok'         => true,
            'message'    => 'Pige envoyée pour vérification.',
            'pige_id'    => $pige->id,
            'photo_url'  => Storage::url($path),
            'taken_at'   => $pige->taken_at->format('d/m/Y H:i'),
        ]);
    }
}
