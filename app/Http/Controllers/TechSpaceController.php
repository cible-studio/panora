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
        // Le tech raisonne "où je vais", pas "quelle campagne" → on charge
        // de quoi reconnaître le lieu (photo du panneau) et y aller (lat/lng).
        $activeTasks = PoseTask::with([
                'panel:id,reference,name,commune_id,format_id,adresse,quartier,latitude,longitude',
                'panel.commune:id,name',
                'panel.format:id,name',
                'panel.photos:id,panel_id,path,ordre',
                'campaign:id,name,start_date,end_date,client_id,status',
                'campaign.client:id,name',
                'lastProblemReport',  // ⚠ pour le badge "déjà signalé"
                'latestRejectedPige:id,pose_task_id,rejection_reason,created_at',
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

        // Stats pour l'en-tête + barre de progression (faites / assignées).
        $totalActive   = $activeTasks->count();
        $totalDone     = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->count();
        $totalAssigned = $totalActive + $totalDone;
        $progressPct   = $totalAssigned > 0 ? (int) round($totalDone / $totalAssigned * 100) : 0;

        // Compteur des poses TERMINÉES regroupées par COMMUNE (pour la barre
        // de progression par zone — le tech voit "ABOBO 2/5" et avance).
        // Une seule requête, eager-load minimal sur panel.commune.
        $doneByCommune = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->with(['panel:id,commune_id', 'panel.commune:id,name'])
            ->get(['id', 'panel_id'])
            ->groupBy(fn($t) => $t->panel?->commune?->name ?? 'Sans commune')
            ->map(fn($g) => $g->count())
            ->all();

        // Marque les poses en retard (échéance passée) — sert au tri et au badge.
        $today = Carbon::today();
        $isOverdue = function ($task) use ($today) {
            $date = $task->scheduled_at ?? $task->created_at;
            return $date && Carbon::parse($date)->startOfDay()->lt($today);
        };

        // Regroupement par COMMUNE/ZONE (le tech fait une zone entière avant
        // de se déplacer), pas par campagne ni par date. À l'intérieur d'une
        // zone : les poses en retard d'abord, puis par échéance.
        $groupedByCommune = $activeTasks
            ->sortBy(fn($t) => [$isOverdue($t) ? 0 : 1, optional($t->scheduled_at)->timestamp ?? PHP_INT_MAX])
            ->groupBy(fn($t) => $t->panel?->commune?->name ?? 'Sans commune')
            // Ordre des zones : celles qui contiennent du retard d'abord,
            // puis les plus grosses (plus de poses) → le tech attaque le
            // plus urgent / le plus rentable en déplacement.
            ->sortBy(function ($tasks) use ($isOverdue) {
                $hasOverdue = $tasks->contains(fn($t) => $isOverdue($t));
                return ($hasOverdue ? '0' : '1') . str_pad((string) (9999 - $tasks->count()), 4, '0', STR_PAD_LEFT);
            });

        return [
            'totalActive'      => $totalActive,
            'totalDone'        => $totalDone,
            'totalAssigned'    => $totalAssigned,
            'progressPct'      => $progressPct,
            'groupedByCommune' => $groupedByCommune,
            'doneByCommune'    => $doneByCommune,
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
            // Plafond aligné sur upload_max_filesize du Dockerfile (35 MB) :
            // iPhone en mode HEIC/PRO produit jusqu'à 25-30 MB en photo brute.
            // La règle `image` de Laravel utilise getimagesize() qui ne supporte
            // PAS HEIC → on s'en passe (rejette les vrais iPhone) et on se fie
            // à `mimes` + `file` pour le whitelist + size. Le contenu est de
            // toute façon revalidé par Intervention\Image au chargement.
            'photo'    => ['required', 'file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:35840'],
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:500',
            'client_uuid' => 'nullable|string|max:64',
        ]);

        // Idempotence : même tentative déjà traitée (double tap / reprise
        // réseau) → on renvoie la pige existante sans dupliquer.
        if (!empty($data['client_uuid'])
            && \Illuminate\Support\Facades\Schema::hasColumn('piges', 'client_uuid')) {
            $existing = Pige::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return response()->json([
                    'ok'        => true,
                    'pige_id'   => $existing->id,
                    'photo_url' => Storage::url($existing->photo_path),
                    'message'   => 'Photo déjà envoyée.',
                    'task_done' => true,
                ]);
            }
        }

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
            'client_uuid' => $data['client_uuid'] ?? null,
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

    /**
     * POST /tech/{token}/poses/{task}/report
     * Signalement terrain en 1 tap (panneau cassé / accès bloqué / mauvaise
     * adresse / autre) → alerte MP/commercial/admin. Ne change pas le statut.
     */
    public function report(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide ou compte désactivé.'], 404);

        $task = PoseTask::with(['campaign:id,name,user_id', 'panel:id,reference'])
            ->where('id', $taskId)
            ->where('assigned_user_id', $tech->id)
            ->first();
        if (!$task) return response()->json(['ok' => false, 'error' => 'Pose introuvable.'], 404);

        $data = $request->validate([
            'type'  => 'required|string|in:panneau_casse,acces_bloque,mauvaise_adresse,autre',
            'note'  => 'nullable|string|max:500',
            // Photo optionnelle (preuve panneau cassé / accès). Compressée
            // côté client donc on plafonne large (35 MB = limite Dockerfile).
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:35840'],
        ]);

        // Anti-spam : refuser un nouveau signalement si le MÊME type est
        // déjà en file (non résolu) sur cette pose. Évite de saturer l'admin
        // et d'embrouiller le tech qui ne savait plus s'il avait déjà cliqué.
        $existing = \App\Models\PoseTaskAction::where('pose_task_id', $task->id)
            ->where('action', 'problem_reported')
            ->whereNull('resolved_at')
            ->whereJsonContains('payload->type', $data['type'])
            ->first();

        if ($existing) {
            return response()->json([
                'ok'          => false,
                'error'       => "Tu as déjà signalé ce problème il y a {$existing->created_at->diffForHumans(null, true)}. Le superviseur l'a en file — pas besoin de re-signaler.",
                'already'     => true,
                'reported_at' => $existing->created_at->toIso8601String(),
            ], 409);
        }

        // Stockage photo (si fournie)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            try {
                $folder   = 'signalements/' . now()->format('Y-m');
                $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.jpg';
                $photoPath = $request->file('photo')->storeAs($folder, $filename, 'public');
            } catch (\Throwable $e) {
                Log::warning('tech.space.report.photo_store_failed', [
                    'task_id' => $task->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $labels = [
            'panneau_casse'    => 'Panneau cassé / abîmé',
            'acces_bloque'     => 'Accès bloqué / impossible',
            'mauvaise_adresse' => 'Mauvaise adresse / introuvable',
            'autre'            => 'Autre problème',
        ];
        $label = $labels[$data['type']] ?? 'Problème signalé';

        \App\Models\PoseTaskAction::log($task->id, 'problem_reported', [
            'type'       => $data['type'],
            'note'       => $data['note'] ?? null,
            'photo_path' => $photoPath,
        ], $tech->name, $request->ip());

        Log::warning('tech.space.problem_reported', [
            'task_id' => $task->id,
            'tech_id' => $tech->id,
            'type'    => $data['type'],
            'ip'      => $request->ip(),
        ]);

        \App\Services\AdminAlertNotifier::notify(
            to: ['commercial_assigned', 'mediaplanner', 'admin'],
            commercialAssigned: $task->campaign?->user,
            severity: 'warning',
            title: 'Problème signalé sur le terrain',
            summary: "Le technicien {$tech->name} signale : « {$label} » — panneau {$task->panel?->reference}.",
            lines: array_filter([
                'Type : ' . $label,
                'Panneau : ' . ($task->panel?->reference ?? '—'),
                'Campagne : ' . ($task->campaign?->name ?? '—'),
                'Technicien : ' . $tech->name,
                !empty($data['note']) ? 'Précisions : ' . $data['note'] : null,
            ]),
            ctaLabel: 'Ouvrir la fiche pose →',
            ctaUrl: url('/admin/pose-tasks/' . $task->id),
            emoji: '⚠️',
            footer: 'Tâche pose #' . $task->id,
            dedupKey: 'pose-problem-' . $task->id . '-' . $data['type'],
        );

        return response()->json([
            'ok'      => true,
            'message' => 'Signalement transmis au superviseur. Merci !',
        ]);
    }
}
