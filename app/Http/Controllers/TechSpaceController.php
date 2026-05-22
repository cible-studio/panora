<?php

namespace App\Http\Controllers;

use App\Enums\PoseTaskStatus;
use App\Models\PoseTask;
use App\Models\Pige;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

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
        // Récupère le tech via son token + verrouille les comptes désactivés.
        // Un tech congédié (is_active=false) ne doit plus accéder à ses
        // poses même s'il a encore le lien dans son WhatsApp.
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) {
            abort(404, 'Lien invalide, expiré, ou compte désactivé.');
        }

        // Pas de cache sur le payload : la sérialisation Laravel des
        // collections Eloquent perd les casts d'enum (PoseTaskStatus
        // devient string à la déserialisation → `->color()` plante avec
        // "Call to a member function color() on string"). Le coût d'une
        // requête fraîche reste très raisonnable (< 50ms typique).
        $payload = $this->buildPayload($tech);

        return view('public.tech-space', array_merge($payload, [
            'tech'  => $tech,
            'token' => $token,
        ]));
    }

    /**
     * Construit le payload data de la page tech (tasks + groupage par
     * date + stats). Isolé pour réutilisation par le cache.
     */
    protected function buildPayload(User $tech): array
    {
        // Charge les poses non-terminales du tech (planifiées, en_route,
        // en_cours). On filtre AUSSI les poses orphelines (panel_id ou
        // campaign_id NULL — état dégradé qui ne devrait pas exister mais
        // qu'on protège quand même).
        //
        // Projection panel : on inclut `adresse` + `quartier` parce que la
        // vue mobile en a besoin pour le lien Maps GPS et l'affichage de
        // contexte terrain. Sans ces colonnes, Eloquent ferait un N+1
        // silencieux à chaque accès via panel->adresse.
        $activeTasks = PoseTask::with([
                'panel:id,reference,name,commune_id,format_id,adresse,quartier',
                'panel.commune:id,name',
                'panel.format:id,name',
                'campaign:id,name,start_date,end_date,client_id,status',
                'campaign.client:id,name',
            ])
            ->where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')
            ->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->orderBy('scheduled_at')
            ->get();

        // Stats rapides pour le bandeau d'en-tête
        $totalActive = $activeTasks->count();
        $totalDone   = PoseTask::where('assigned_user_id', $tech->id)
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

        return [
            'totalActive'  => $totalActive,
            'totalDone'    => $totalDone,
            'groupedByDay' => $groupedByDay,
        ];
    }

    /**
     * Invalide le cache de l'espace tech après une modification (status,
     * upload). Appelé par updateStatus() + uploadPhoto() + côté service
     * quand un admin assigne une nouvelle pose.
     */
    public static function invalidateCache(int $userId): void
    {
        Cache::forget("tech.space.{$userId}.payload");
    }

    /**
     * POST /tech/{token}/poses/{task}/status
     * Transition de statut depuis l'interface tech.
     * Accepte : en_route, en_cours, realisee.
     */
    public function updateStatus(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide ou compte désactivé.'], 404);

        $task = PoseTask::with('campaign:id,status')
            ->where('id', $taskId)
            ->where('assigned_user_id', $tech->id)
            ->first();
        if (!$task) return response()->json(['ok' => false, 'error' => 'Pose introuvable.'], 404);

        // Vérifie que la campagne est dans un état qui autorise les
        // interventions terrain. Si elle a été mise en pause / terminée /
        // annulée entre l'assignation et l'action du tech, on refuse.
        $campStatus = $task->campaign?->status?->value;
        if (!in_array($campStatus, ['planifie', 'actif'], true)) {
            $label = $task->campaign?->status?->label() ?? 'inconnue';
            return response()->json([
                'ok'    => false,
                'error' => "La campagne est « {$label} » — modifications terrain bloquées. Contactez votre superviseur.",
            ], 423); // 423 Locked
        }

        // Liste les valeurs de transition autorisées DIRECTEMENT depuis
        // l'enum (évite de devoir mettre à jour 2 endroits si l'enum
        // évolue : ajout d'un statut SUR_PLACE, PROBLEME, etc.).
        $allowedValues = collect(PoseTaskStatus::cases())
            ->map(fn($c) => $c->value)
            ->reject(fn($v) => in_array($v, ['planifiee', 'annulee']))
            ->values()
            ->all();

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', $allowedValues)],
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
        self::invalidateCache($tech->id);

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
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide ou compte désactivé.'], 404);

        $task = PoseTask::with(['campaign:id,name,status,user_id', 'panel:id,reference'])
            ->where('id', $taskId)
            ->where('assigned_user_id', $tech->id)
            ->first();
        if (!$task) return response()->json(['ok' => false, 'error' => 'Pose introuvable.'], 404);

        if (!$task->campaign_id || !$task->panel_id) {
            return response()->json(['ok' => false, 'error' => 'Pose mal configurée.'], 422);
        }

        // Refuse l'upload si la campagne n'est plus active (pause/terminé/
        // annulé). Le tech doit contacter son superviseur si la situation
        // a évolué pendant qu'il était sur le terrain.
        $campStatus = $task->campaign?->status?->value;
        if (!in_array($campStatus, ['planifie', 'actif'], true)) {
            $label = $task->campaign?->status?->label() ?? 'inconnue';
            return response()->json([
                'ok'    => false,
                'error' => "La campagne est « {$label} » — upload bloqué. Contactez votre superviseur.",
            ], 423);
        }

        $data = $request->validate([
            // Plafond : 15 MB suffit pour une pige photo en qualité confort.
            // La plupart des smartphones produisent ~3-5 MB en JPEG. On garde
            // de la marge pour les modes HEIC/PRO sans saturer le stockage.
            'photo'    => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:15360'],
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:500',
        ]);

        // Stockage photo — compression côté serveur AVANT persistance.
        // Les photos brutes smartphone font typiquement 3-10 MB en JPEG
        // (ou jusqu'à 30+ MB en HEIC/PRO). On les réduit à 1920px de
        // large + JPEG q=82 → typiquement 200-500 KB par photo. Économie
        // x10 à x40 sur le stockage et un upload visualisation rapide
        // côté admin (validation pige) + côté client.
        $folder   = "piges/{$task->campaign_id}/{$task->panel_id}";
        $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.jpg';
        $path     = $folder . '/' . $filename;

        try {
            $manager = new ImageManager(new GdDriver());
            $image   = $manager->read($request->file('photo')->getPathname());
            // scaleDown ne fait rien si l'image est déjà < width — pas
            // d'upscale, on garde la qualité native si la photo est petite.
            $image->scaleDown(width: 1920);
            Storage::disk('public')->put($path, $image->toJpeg(82));
        } catch (\Throwable $e) {
            Log::warning('tech.space.compress_failed', [
                'error' => $e->getMessage(),
                'task_id' => $task->id,
            ]);
            // Fallback : stockage tel quel si Intervention échoue
            // (HEIC non géré par GD, etc.)
            $ext  = $request->file('photo')->getClientOriginalExtension();
            $path = $folder . '/' . time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $ext;
            $request->file('photo')->storeAs($folder, basename($path), 'public');
        }

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

        self::invalidateCache($tech->id);

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
