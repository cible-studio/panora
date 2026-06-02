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
                // ⚠ Pas de projection (select) ici : latestOfMany génère des
                // joins internes, et `id`/`pose_task_id` non préfixés deviennent
                // ambigus → "Column 'pose_task_id' is ambiguous". On laisse
                // Eloquent utiliser `piges.*` (qualifié) par défaut.
                'latestRejectedPige',
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

        // ── Métriques "aujourd'hui" pour le dashboard tech ────────────
        // Distinction importante : totalDone = depuis toujours ; les chiffres
        // ci-dessous ne portent QUE sur la journée en cours, ce qui donne
        // au tech un retour sur son activité du jour (1 poste = 1 sprint).
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay   = $today->copy()->endOfDay();

        $doneToday = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->whereBetween('done_at', [$startOfDay, $endOfDay])
            ->count();

        // Poses prévues aujourd'hui ENCORE actives — c'est exactement ce
        // que le filtre KPI "Aujourd'hui" rend visible. Cohérence visuelle
        // entre la valeur affichée et le contenu après clic.
        $activeToday = $activeTasks->filter(function ($t) use ($startOfDay, $endOfDay) {
            $d = $t->scheduled_at ?? $t->created_at;
            return $d && \Carbon\Carbon::parse($d)->between($startOfDay, $endOfDay);
        })->count();

        // Filtre piges du tech : robuste aux uploads historiques (user_id
        // pointait sur le commercial avant le fix). On accepte user_id direct
        // OU rattachement via une PoseTask assignée au tech.
        $pigesForTech = function ($q) use ($tech) {
            $q->where(function ($qq) use ($tech) {
                $qq->where('user_id', $tech->id)
                   ->orWhereExists(function ($sub) use ($tech) {
                       $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                           ->from('pose_tasks')
                           ->where('pose_tasks.assigned_user_id', $tech->id)
                           ->whereColumn('pose_tasks.panel_id',    'piges.panel_id')
                           ->whereColumn('pose_tasks.campaign_id', 'piges.campaign_id');
                   });
            });
        };

        $pigesSentToday = \App\Models\Pige::query()
            ->tap($pigesForTech)
            ->whereBetween('taken_at', [$startOfDay, $endOfDay])
            ->count();

        // Zones distinctes touchées aujourd'hui (poses faites + poses prévues
        // restantes). Donne au tech un cap géographique pour sa journée.
        $zonesActiveToday = $activeTasks
            ->pluck('panel.commune.name')
            ->filter()
            ->unique()
            ->values();
        $zonesDoneToday = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->whereBetween('done_at', [$startOfDay, $endOfDay])
            ->with('panel:id,commune_id', 'panel.commune:id,name')
            ->get(['id', 'panel_id'])
            ->pluck('panel.commune.name')
            ->filter()
            ->unique()
            ->values();
        $zonesTodayCount = $zonesActiveToday->merge($zonesDoneToday)->unique()->count();

        // Piges du tech, tout temps : permet le compteur du bouton "Mes piges"
        // (peut être 0, on n'affiche le badge que si > 0). Même filtre robuste.
        $pigesTotal = \App\Models\Pige::query()->tap($pigesForTech)->count();
        $pigesRejected = \App\Models\Pige::query()->tap($pigesForTech)
            ->where('status', 'rejete')->count();

        return [
            'totalActive'      => $totalActive,
            'totalDone'        => $totalDone,
            'totalAssigned'    => $totalAssigned,
            'progressPct'      => $progressPct,
            'groupedByCommune' => $groupedByCommune,
            'doneByCommune'    => $doneByCommune,
            // Métriques journée
            'doneToday'        => $doneToday,
            'activeToday'      => $activeToday,
            'pigesSentToday'   => $pigesSentToday,
            'zonesTodayCount'  => $zonesTodayCount,
            'zonesTodayList'   => $zonesActiveToday->merge($zonesDoneToday)->unique()->values()->all(),
            // Piges global tech (pour le bouton historique)
            'pigesTotal'       => $pigesTotal,
            'pigesRejected'    => $pigesRejected,
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

        // Auto-sync progress_percent depuis le nouveau statut : le widget
        // admin "Progression rapportée" sur la fiche pose suit ainsi en
        // quasi temps réel l'avancée du tech sans intervention manuelle.
        $progressMap = [
            PoseTaskStatus::PLANNED->value     => 0,
            PoseTaskStatus::EN_ROUTE->value    => 25,
            PoseTaskStatus::IN_PROGRESS->value => 60,
            PoseTaskStatus::COMPLETED->value   => 100,
        ];

        $update = ['status' => $newStatus->value];
        if (array_key_exists($newStatus->value, $progressMap)) {
            $update['progress_percent'] = $progressMap[$newStatus->value];
        }
        // Premier mouvement (>=25%) → started_at si pas déjà défini
        if (($update['progress_percent'] ?? 0) > 0 && !$task->started_at) {
            $update['started_at'] = now();
        }
        if ($newStatus === PoseTaskStatus::COMPLETED) {
            $update['done_at'] = now();
            // real_minutes calculable seulement si started_at était posé
            if ($task->started_at) {
                $update['real_minutes'] = max(1, (int) round(
                    $task->started_at->diffInMinutes(now())
                ));
            }
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
    /**
     * GET /tech/{token}/heartbeat
     *
     * Endpoint JSON ultra-léger appelé en polling par le dashboard tech
     * (tech-space + tech-piges) pour mettre à jour les KPI sans reload :
     *   - À faire / Aujourd'hui / Piges / Zones (compteurs jour)
     *   - Progression globale (% + ratio)
     *   - Status piges (pending / verified / rejected) pour la page piges
     *   - latest_task_id pour détecter une nouvelle assignation
     */
    public function heartbeat(string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false], 404);

        $today      = Carbon::today();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay   = $today->copy()->endOfDay();

        $totalActive = PoseTask::where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
            ->count();
        $totalDone = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)->count();
        $totalAssigned = $totalActive + $totalDone;
        $progressPct = $totalAssigned > 0 ? (int) round($totalDone / $totalAssigned * 100) : 0;

        $doneToday = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->whereBetween('done_at', [$startOfDay, $endOfDay])->count();

        // Poses prévues aujourd'hui ENCORE actives (= ce que le filtre KPI
        // "Aujourd'hui" affiche en cliquant) — cohérence compteur ↔ liste.
        $activeToday = PoseTask::where('assigned_user_id', $tech->id)
            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay])
            ->count();

        $pigesForTech = function ($q) use ($tech) {
            $q->where(function ($qq) use ($tech) {
                $qq->where('user_id', $tech->id)
                   ->orWhereExists(function ($sub) use ($tech) {
                       $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                           ->from('pose_tasks')
                           ->where('pose_tasks.assigned_user_id', $tech->id)
                           ->whereColumn('pose_tasks.panel_id',    'piges.panel_id')
                           ->whereColumn('pose_tasks.campaign_id', 'piges.campaign_id');
                   });
            });
        };
        $pigesSentToday = \App\Models\Pige::query()->tap($pigesForTech)
            ->whereBetween('taken_at', [$startOfDay, $endOfDay])->count();
        $pigesTotal     = \App\Models\Pige::query()->tap($pigesForTech)->count();
        $pigesPending   = \App\Models\Pige::query()->tap($pigesForTech)->where('status', 'en_attente')->count();
        $pigesVerified  = \App\Models\Pige::query()->tap($pigesForTech)->where('status', 'verifie')->count();
        $pigesRejected  = \App\Models\Pige::query()->tap($pigesForTech)->where('status', 'rejete')->count();

        // Zones couvertes aujourd'hui (poses faites + poses du jour restantes)
        $zonesTodayCount = PoseTask::where('assigned_user_id', $tech->id)
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('done_at', [$startOfDay, $endOfDay])
                  ->orWhereBetween('scheduled_at', [$startOfDay, $endOfDay]);
            })
            ->with('panel:id,commune_id')
            ->get(['id', 'panel_id'])
            ->pluck('panel.commune_id')->filter()->unique()->count();

        // Dernière PoseTask assignée — sert à détecter une nouvelle pose
        // arrivée pendant que le tech est sur la page (toast + son).
        $latestTask = PoseTask::where('assigned_user_id', $tech->id)
            ->latest('id')->first(['id', 'created_at']);

        return response()->json([
            'ok'              => true,
            'totalActive'     => $totalActive,
            'totalDone'       => $totalDone,
            'totalAssigned'   => $totalAssigned,
            'progressPct'     => $progressPct,
            'doneToday'       => $doneToday,
            'activeToday'     => $activeToday,
            'pigesSentToday'  => $pigesSentToday,
            'pigesTotal'      => $pigesTotal,
            'pigesPending'    => $pigesPending,
            'pigesVerified'   => $pigesVerified,
            'pigesRejected'   => $pigesRejected,
            'zonesTodayCount' => $zonesTodayCount,
            'latestTaskId'    => $latestTask?->id ?? 0,
            'serverNow'       => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /tech/{token}/piges
     *
     * Historique des piges du tech : tout statut confondu (en_attente,
     * verifie, rejete) avec photo, campagne, panneau, date, motif de rejet
     * si applicable. Pagination simple (30 par page). Le tech voit ce
     * qu'il a envoyé, ce qui est validé, ce qu'il doit reprendre.
     */
    public function piges(Request $request, string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) abort(404, 'Lien invalide ou compte désactivé.');

        // Filtre ?status= depuis les KPI tiles cliquables (en_attente /
        // verifie / rejete / all). 'all' ou absence = pas de filtre.
        $statusFilter = $request->input('status');
        $validStatus  = ['en_attente', 'verifie', 'rejete'];
        if (!in_array($statusFilter, $validStatus, true)) {
            $statusFilter = null;
        }

        // Filtre robuste : on récupère les piges du tech soit directement
        // via user_id (nouveau pattern), soit via la PoseTask correspondante
        // (panel + campagne assignés à ce tech) pour ne pas perdre les
        // piges historiques où user_id pointait sur le commercial créateur
        // de la campagne (bug corrigé).
        $piges = \App\Models\Pige::query()
            ->where(function ($q) use ($tech) {
                $q->where('user_id', $tech->id)
                  ->orWhereExists(function ($sub) use ($tech) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('pose_tasks')
                          ->where('pose_tasks.assigned_user_id', $tech->id)
                          ->whereColumn('pose_tasks.panel_id',    'piges.panel_id')
                          ->whereColumn('pose_tasks.campaign_id', 'piges.campaign_id');
                  });
            })
            ->when($statusFilter, fn($q, $s) => $q->where('status', $s))
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name',
            ])
            ->orderByDesc('taken_at')
            ->paginate(30)
            ->withQueryString();

        // Compteurs globaux (même filtre tech robuste) pour les KPI.
        $base = \App\Models\Pige::query()
            ->where(function ($q) use ($tech) {
                $q->where('user_id', $tech->id)
                  ->orWhereExists(function ($sub) use ($tech) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('pose_tasks')
                          ->where('pose_tasks.assigned_user_id', $tech->id)
                          ->whereColumn('pose_tasks.panel_id',    'piges.panel_id')
                          ->whereColumn('pose_tasks.campaign_id', 'piges.campaign_id');
                  });
            });
        $kpi = [
            'total'    => (clone $base)->count(),
            'pending'  => (clone $base)->where('status', 'en_attente')->count(),
            'verified' => (clone $base)->where('status', 'verifie')->count(),
            'rejected' => (clone $base)->where('status', 'rejete')->count(),
        ];

        return view('public.tech-piges', compact('tech', 'token', 'piges', 'kpi', 'statusFilter'));
    }

    public function uploadPhoto(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide ou compte désactivé.'], 404);

        $task = PoseTask::with([
                'campaign:id,name,status,user_id',
                'panel:id,reference',
                // Pour détecter le cas "tech a signalé un problème non résolu
                // ET tente quand même d'envoyer une pige" → on force la
                // justification de la contradiction (voir bloc plus bas).
                //
                // ⚠ Pas de projection (select) ici : latestOfMany génère des
                // joins internes, et `id`/`pose_task_id`/`created_at` non
                // préfixés deviennent ambigus → SQLSTATE[23000] 1052. Même
                // pattern que latestRejectedPige (cf. fix précédent).
                'lastProblemReport',
            ])
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
            // Justification obligatoire quand le tech a un signalement ouvert
            // sur cette pose. Vérifié plus bas (côté serveur — l'erreur 422
            // sert de fallback si le JS frontend a été contourné).
            'contradicts_signalement_reason' => 'nullable|string|min:10|max:1000',
        ]);

        // ── Garde-fou contradiction signalement ↔ pige ────────────────
        // Si le tech a déjà signalé un problème NON RÉSOLU sur cette pose
        // (panneau cassé / accès / adresse / autre), une pige envoyée
        // est contradictoire. On exige une justification écrite — sinon
        // 422 structuré pour que le frontend ouvre la modale dédiée.
        $blockingSignal = $task->lastProblemReport;
        if ($blockingSignal && empty(trim($data['contradicts_signalement_reason'] ?? ''))) {
            $signalType  = $blockingSignal->payload['type'] ?? 'autre';
            $labels = [
                'panneau_casse'    => 'Panneau cassé / abîmé',
                'acces_bloque'     => 'Accès bloqué / impossible',
                'mauvaise_adresse' => 'Mauvaise adresse / introuvable',
                'autre'            => 'Autre problème',
            ];
            $signalLabel = $labels[$signalType] ?? 'Problème';

            return response()->json([
                'ok'                            => false,
                'requires_contradiction_reason' => true,
                'signalement_type'              => $signalType,
                'signalement_label'             => $signalLabel,
                'error'                         => "Tu as signalé ce panneau comme « {$signalLabel} ». "
                    . "Justifie pourquoi tu envoies quand même une pige (10 caractères min).",
            ], 422);
        }

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

        // Trace de contradiction signalement → pige : visible côté MP/admin
        // au moment de la validation pige. Le préfixe ⚠ permet un grep et
        // un filtre rapide ("piges contradictoires").
        if ($blockingSignal && !empty($data['contradicts_signalement_reason'])) {
            $signalType  = $blockingSignal->payload['type'] ?? 'autre';
            $noteParts[] = "⚠ CONTRADICTION SIGNALEMENT type=\"{$signalType}\" — justif: "
                         . trim($data['contradicts_signalement_reason']);
        }

        // ⚠ Historique : avant ce commit, user_id était forcé à
        //   $task->campaign->user_id (le commercial créateur de la campagne)
        // → la vue tech "Mes piges" filtrait par user_id et ne retrouvait
        //   pas les piges envoyées par le tech. Correction : c'est BIEN le
        //   tech qui upload, donc user_id = $tech->id. Et on lie la pige
        //   à la PoseTask (pose_task_id) pour le filtrage robuste côté
        //   tech-space (et les jointures admin futures).
        $pige = Pige::create([
            'panel_id'     => $task->panel_id,
            'campaign_id'  => $task->campaign_id,
            'pose_task_id' => $task->id,
            'user_id'      => $tech->id,
            'photo_path'   => $path,
            'taken_at'     => now(),
            'gps_lat'      => $data['gps_lat'] ?? null,
            'gps_lng'      => $data['gps_lng'] ?? null,
            'notes'        => implode(' · ', $noteParts),
            'status'       => 'en_attente',
            'client_uuid'  => $data['client_uuid'] ?? null,
        ]);

        // Marque la tâche comme réalisée si pas déjà fait.
        // ⚠️ PoseTask::$status n'est PAS casté en enum (décision conservée
        // pour ne pas casser les comparaisons string existantes), donc on
        // passe par tryFrom() pour utiliser ->isTerminal() proprement.
        // Avant : $task->status->isTerminal() → "Call to a member function
        // isTerminal() on string" en prod, le tech voyait l'erreur PHP
        // alors que la pige était bel et bien créée.
        $currentStatus = PoseTaskStatus::tryFrom((string) $task->status);
        if ($currentStatus && !$currentStatus->isTerminal()) {
            // Bump progression à 100% + started_at si pas déjà posé +
            // real_minutes si on peut calculer. Cohérent avec updateStatus().
            $taskUpdate = [
                'status'           => PoseTaskStatus::COMPLETED->value,
                'done_at'          => now(),
                'progress_percent' => 100,
            ];
            if (!$task->started_at) {
                $taskUpdate['started_at'] = now();
            } else {
                $taskUpdate['real_minutes'] = max(1, (int) round(
                    $task->started_at->diffInMinutes(now())
                ));
            }
            $task->update($taskUpdate);
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

        // Anti-spam : UN SEUL signalement actif par pose (peu importe le type).
        // Si le tech veut signaler une autre chose, il doit attendre que le
        // précédent soit traité côté admin — sinon on accumule des doublons
        // (vu en prod : 1 même panneau avec 2 signalements de types différents
        // créés par un test, ouvre la porte à des dizaines).
        $existing = \App\Models\PoseTaskAction::where('pose_task_id', $task->id)
            ->where('action', 'problem_reported')
            ->whereNull('resolved_at')
            ->first();

        if ($existing) {
            $existingType = $existing->payload['type'] ?? 'autre';
            $existingLabel = [
                'panneau_casse'    => 'Panneau cassé / abîmé',
                'acces_bloque'     => 'Accès bloqué / impossible',
                'mauvaise_adresse' => 'Mauvaise adresse / introuvable',
                'autre'            => 'Autre problème',
            ][$existingType] ?? 'Problème signalé';

            return response()->json([
                'ok'          => false,
                'error'       => "Tu as déjà un signalement actif sur ce panneau : « {$existingLabel} » (il y a {$existing->created_at->diffForHumans(null, true)}). Le superviseur le traite — attends qu'il soit clôturé avant d'en envoyer un autre.",
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

        // Invalide le cache du badge sidebar "Signalements" pour que l'admin
        // voie tout de suite le nouveau compte (sans attendre le TTL 60s).
        \Illuminate\Support\Facades\Cache::forget('admin.signalements.pending_count');

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
