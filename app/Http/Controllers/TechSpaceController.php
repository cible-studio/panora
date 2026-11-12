<?php

namespace App\Http\Controllers;

use App\Enums\PoseTaskStatus;
use App\Enums\UserRole;
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
        // ═══ NETTOYAGE PRÉLIMINAIRE — Exclusivité stricte (2026-07-07 v3)
        // Avant de charger la collection Eloquent, on force en BDD la
        // règle : maximum UNE seule pose active (en_route OU en_cours)
        // par tech. Priorité "sur place" (arrived_at posé) > "en route",
        // puis timestamp le plus récent. Fait en SQL direct AVANT
        // Eloquent pour que $activeTasks soit cohérent d'entrée — sinon
        // Blade rendait des poses "sur place" fantômes malgré l'update.
        $activeIdsOrdered = PoseTask::where('assigned_user_id', $tech->id)
            ->whereIn('status', [PoseTaskStatus::EN_ROUTE->value, PoseTaskStatus::IN_PROGRESS->value])
            ->onOperableCampaign()
            ->orderByRaw('CASE WHEN arrived_at IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('arrived_at')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->pluck('id');
        if ($activeIdsOrdered->count() > 1) {
            $keepId = $activeIdsOrdered->first();
            // Axe (2026-11-12) : les pieds sœurs de la pose gardée sont le
            // même lieu — on les garde avec elle.
            $freres = PoseTask::find($keepId)?->idsFreresDAxe() ?? [];
            $revertIds = $activeIdsOrdered->skip(1)->reject(fn ($id) => in_array((int) $id, $freres, true))->values()->all();
        }
        if (!empty($revertIds ?? [])) {
            PoseTask::whereIn('id', $revertIds)->update([
                'status'           => PoseTaskStatus::PLANNED->value,
                'progress_percent' => 0,
                'started_at'       => null,
                'arrived_at'       => null,
            ]);
            Log::info('tech.space.exclusivity_cleanup', [
                'tech_id'  => $tech->id,
                'kept_id'  => $keepId,
                'reverted' => $revertIds,
                'count'    => count($revertIds),
            ]);
        }

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
        //
        // ⚠ Scalabilité : à 500+ poses, on évite de tout rendre en SSR
        // (DOM 5k+ nœuds = mobile en souffrance). On SSR-rend les SSR_CAP
        // les plus urgentes (retard d'abord, puis aujourd'hui, puis le
        // reste). Au-delà, la recherche Select2 (endpoint searchPoses())
        // sert de point d'entrée — elle requête la BDD complète et injecte
        // dynamiquement la carte sélectionnée si elle n'est pas en SSR.
        // Axes (2026-11-12) : de quoi regrouper les pieds sous leur axe, et
        // prendre la photo et la position de l'axe quand le pied n'en a pas.
        $colonnesAxe = app(\App\Services\Axes\AxeService::class)->colonnesPresentes();
        $activeTasks = PoseTask::with(array_merge([
                'panel:id,reference,name,commune_id,format_id,adresse,quartier,latitude,longitude'
                    . ($colonnesAxe ? ',axe_id,pied_numero,est_axe' : ''),
                'panel.commune:id,name',
                'panel.format:id,name',
                'panel.photos:id,panel_id,path,ordre',
            ], $colonnesAxe ? [
                'panel.axe:id,reference,name,latitude,longitude',
                'panel.axe.photos:id,panel_id,path,ordre',
            ] : [], [
                'campaign:id,name,start_date,end_date,client_id,status',
                'campaign.client:id,name',
                'lastProblemReport',  // ⚠ pour le badge "déjà signalé"
                // ⚠ Pas de projection (select) ici : latestOfMany génère des
                // joins internes, et `id`/`pose_task_id` non préfixés deviennent
                // ambigus → "Column 'pose_task_id' is ambiguous". On laisse
                // Eloquent utiliser `piges.*` (qualifié) par défaut.
                'latestRejectedPige',
                // 2026-08-10 : badge visuel équipe créditée sur les cartes
                // pose du tech — distingue solo (crédit perso) vs équipe
                // (crédit collectif). Cf. _pose_card.blade.php.
                'poseTeam:id,name,color_slug',
            ]))
            ->where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')
            ->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            // Défense en profondeur 2026-08-04 : si le cleanup campagne
            // rate une pose, elle ne remonte plus ici. Le tech ne voit
            // que les poses des campagnes réellement actives.
            ->onOperableCampaign()
            ->orderBy('scheduled_at')
            ->get();

        // Cf. NETTOYAGE PRÉLIMINAIRE en début de buildPayload — déjà fait
        // en SQL direct avant le chargement Eloquent, donc $activeTasks
        // est déjà cohérent ici (max 1 pose active).

        $totalActiveAll = $activeTasks->count();

        // Cap du SSR : 200 cartes max. Au-delà, on garde les plus urgentes
        // dans le rendu initial (en retard → aujourd'hui → reste) et la
        // recherche Select2 / la feuille de route donnent accès au reste.
        // 200 ≈ 3000 nœuds DOM, encore confortable sur mobile bas de gamme.
        $ssrCap = (int) config('tech_space.ssr_cap', 200);
        $today  = Carbon::today();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay   = $today->copy()->endOfDay();
        $lateThreshold = PoseTask::lateThreshold();
        $isLate = function ($t) use ($lateThreshold) {
            $d = $t->scheduled_at ?? $t->created_at;
            return $d && Carbon::parse($d)->lt($lateThreshold);
        };
        $isTodayTask = function ($t) use ($startOfDay, $endOfDay) {
            $d = $t->scheduled_at ?? $t->created_at;
            return $d && Carbon::parse($d)->between($startOfDay, $endOfDay);
        };
        if ($totalActiveAll > $ssrCap) {
            // Priorité : retard d'abord (timestamp asc), puis aujourd'hui,
            // puis le reste. Ce tri remplace l'orderBy DB pour le SSR.
            $activeTasks = $activeTasks->sortBy(function ($t) use ($isLate, $isTodayTask) {
                $bucket = $isLate($t) ? 0 : ($isTodayTask($t) ? 1 : 2);
                $ts     = optional($t->scheduled_at)->timestamp ?? PHP_INT_MAX;
                return sprintf('%d-%020d', $bucket, $ts);
            })->take($ssrCap)->values();
        }

        // Stats pour l'en-tête + barre de progression (faites / assignées).
        // ⚠ totalActive = total réel (non capé), pour ne pas mentir à l'admin
        // sur les KPI. Le SSR n'en rend qu'une partie ($activeTasks→count()),
        // mais le tech voit "X poses au total" exact (= $totalActiveAll).
        $totalActive   = $totalActiveAll;
        $totalRendered = $activeTasks->count();
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

        // SM2a Lot 1.4 — Liste des 30 poses récentes TERMINÉES, pour la
        // section "🟢 Déjà faites" (pliée par défaut) du carnet T1 §3.5.
        // Cap à 30 pour éviter de saturer le DOM Android Go ; au-delà, le
        // tech peut tout voir dans /tech/{token}/piges.
        $donePosesRecent = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                // 2026-09-21 : « Fait par … » dans la section « Déjà faites ».
                // Eager-load obligatoire, sinon N+1 sur 30 lignes.
                'completedBy:id,name',
                'technicien:id,name',
            ])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        // Regroupement par COMMUNE/ZONE (le tech fait une zone entière avant
        // de se déplacer), pas par campagne ni par date. À l'intérieur d'une
        // zone : les poses en retard d'abord, puis par échéance.
        // ⚠ $isLate, $today, $startOfDay, $endOfDay sont déjà définis plus
        // haut (avant le cap SSR) — on les réutilise sans les redéclarer.
        $groupedByCommune = $activeTasks
            ->sortBy(fn($t) => [$isLate($t) ? 0 : 1, optional($t->scheduled_at)->timestamp ?? PHP_INT_MAX])
            ->groupBy(fn($t) => $t->panel?->commune?->name ?? 'Sans commune')
            // Ordre des zones (feedback patronne 2026-07-06) :
            //   1. Commune avec au moins UNE pose démarrée (en_route ou en_cours)
            //      → le tech doit finir ce qu'il a commencé avant de zapper ailleurs
            //   2. Commune avec du retard
            //   3. Plus grosses (plus de poses) — rentabilité de déplacement
            ->sortBy(function ($tasks) use ($isLate) {
                $hasStarted = $tasks->contains(fn($t) => in_array(
                    (string) $t->status,
                    [\App\Enums\PoseTaskStatus::EN_ROUTE->value, \App\Enums\PoseTaskStatus::IN_PROGRESS->value],
                    true
                ));
                $hasOverdue = $tasks->contains(fn($t) => $isLate($t));
                // Format : [démarré=0/1][retard=0/1][taille inversée 4 digits]
                // 000-0128 (démarré + retard + 128 poses)  < 001-0128 (juste démarré)
                //   < 100-9999 (juste retard) < 110-0128 (rien de spécial)
                return ($hasStarted ? '0' : '1')
                    . ($hasOverdue ? '0' : '1')
                    . str_pad((string) (9999 - $tasks->count()), 4, '0', STR_PAD_LEFT);
            });

        // ── Métriques "aujourd'hui" pour le dashboard tech ────────────
        // Distinction importante : totalDone = depuis toujours ; les chiffres
        // ci-dessous ne portent QUE sur la journée en cours, ce qui donne
        // au tech un retour sur son activité du jour (1 poste = 1 sprint).
        $doneToday = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->whereBetween('done_at', [$startOfDay, $endOfDay])
            ->count();

        // Poses prévues aujourd'hui ENCORE actives — requête DB dédiée pour
        // rester correcte même quand le SSR a capé (sinon on sous-compterait
        // à grande échelle puisque $activeTasks aurait été tronqué).
        $activeToday = PoseTask::where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay])
            ->count();

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
        // pigesTotal n'est PAS filtré sur le statut campagne — c'est un
        // historique global consultable même sur campagnes terminées.
        $pigesTotal = \App\Models\Pige::query()->tap($pigesForTech)->count();

        // Réutilisable : sous-requête "campagne opérable" pour les piges
        // → même règle que PoseTask::scopeOnOperableCampaign, appliquée
        // via une jointure sur campaigns car Pige n'a pas de relation
        // directe (juste campaign_id nullable).
        $onOperableCampaign = function ($q) {
            $q->whereExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('campaigns')
                    ->whereColumn('campaigns.id', 'piges.campaign_id')
                    ->whereNotIn('campaigns.status', [
                        \App\Enums\CampaignStatus::TERMINE->value,
                        \App\Enums\CampaignStatus::ANNULE->value,
                    ])
                    ->whereNull('campaigns.deleted_at');
            });
        };

        // pigesRejected (compteur badge "à refaire") : uniquement les
        // piges rejetées ACTIONNABLES. Une pige rejetée dont la campagne
        // est terminée ne peut plus être refaite → on l'ignore du badge
        // pour éviter le message trompeur "Va dans Mes photos".
        //
        // Fix 2026-08-04bis : on applique AUSSI le whereNotExists "pas
        // de successeure plus récente" pour rester cohérent avec la
        // liste $rejectedPiges affichée dans le drawer T9. Sinon le
        // count restait à 2 pendant que la liste était vide (le tech
        // a re-uploadé, la 2e pige remplace l'ancienne rejetée).
        $pigesRejected = \App\Models\Pige::query()
            ->tap($pigesForTech)
            ->tap($onOperableCampaign)
            ->where('piges.status', 'rejete')
            // Pas encore refaite (règle unique, Pige::scopeDerniereParPanneau).
            ->derniereParPanneau()
            ->count();

        // SM2a Lot 5.2 — Liste des piges refusées en cours (pas encore
        // refaites). Source unique pour le drawer T9 "À refaire". Filtre
        // identique à la vue "current" du tableau historique des piges :
        // on exclut les piges qui ont une successeure plus récente sur le
        // même (panel, campagne). Cap 20 pour éviter le bourrage DOM si
        // un tech a un parc historique chargé.
        // Ajout 2026-08-04 : exclut les piges des campagnes terminées —
        // sinon le bouton "Refaire la photo" pointe vers une pose qui
        // n'est plus dans le carnet (elle a été filtrée par onOperableCampaign).
        $rejectedPiges = \App\Models\Pige::query()
            ->tap($pigesForTech)
            ->tap($onOperableCampaign)
            ->where('piges.status', 'rejete')
            // Pas encore refaite (règle unique, Pige::scopeDerniereParPanneau).
            ->derniereParPanneau()
            ->with([
                'panel:id,reference,name,commune_id,latitude,longitude',
                'panel.commune:id,name',
                'verificateur:id,name',
            ])
            ->orderByDesc('piges.taken_at')
            ->limit(20)
            ->get();

        // Liste TOC zones : agrégat global (toutes poses actives, pas seulement
        // SSR) → chip cliquable sticky pour navigation directe. Une requête
        // groupée sur la BDD pour rester rapide même à 5k+ poses.
        // ⚠ Le JOIN panels apporte une colonne `status` homonyme → toutes
        // les colonnes ambiguës DOIVENT être qualifiées (`pose_tasks.status`,
        // `pose_tasks.assigned_user_id`, `pose_tasks.panel_id`, etc.). Sans
        // ça : "Column 'status' in where clause is ambiguous" → 500 sur la
        // page tech-space en prod.
        $allZones = PoseTask::query()
            ->join('panels', 'panels.id', '=', 'pose_tasks.panel_id')
            ->join('communes', 'communes.id', '=', 'panels.commune_id')
            ->where('pose_tasks.assigned_user_id', $tech->id)
            ->whereNotNull('pose_tasks.panel_id')
            ->whereNotNull('pose_tasks.campaign_id')
            ->whereNotIn('pose_tasks.status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->groupBy('communes.id', 'communes.name')
            ->orderBy('communes.name')
            ->get([
                'communes.name as commune_name',
                \Illuminate\Support\Facades\DB::raw('COUNT(pose_tasks.id) as total_active'),
            ])
            ->map(function ($row) use ($doneByCommune) {
                $done  = (int) ($doneByCommune[$row->commune_name] ?? 0);
                $active = (int) $row->total_active;
                $total  = $active + $done;
                return [
                    'name'   => $row->commune_name,
                    'active' => $active,
                    'done'   => $done,
                    'total'  => $total,
                    'pct'    => $total > 0 ? (int) round($done / $total * 100) : 0,
                ];
            })
            ->all();

        // "Prochaine pose" = celle sur laquelle on doit se concentrer MAINTENANT.
        // Feedback patronne 2026-07-06 : "MAINTENANT" doit d'abord pointer sur
        // la pose EN COURS DE RÉALISATION (le tech a déjà démarré) — sinon
        // il voit "MAINTENANT" ailleurs alors qu'il est en route pour une
        // autre commune → confusion.
        // Ordre de priorité :
        //   1. Pose en_route ou en_cours (le tech a démarré, il doit finir ça)
        //   2. Pose en retard (dépassée depuis > 2 jours)
        //   3. Pose du jour
        //   4. Fallback : première pose active
        $inProgressStatuses = [
            PoseTaskStatus::EN_ROUTE->value,
            PoseTaskStatus::IN_PROGRESS->value,
        ];
        $nextTask = $activeTasks->first(fn($t) => in_array((string) $t->status, $inProgressStatuses, true))
                  ?? $activeTasks->first(fn($t) => $isLate($t))
                  ?? $activeTasks->first(fn($t) => $isTodayTask($t))
                  ?? $activeTasks->first();

        // ── Refonte 2026-11-12 ─────────────────────────────────────────
        // Progression du JOUR, pour de vrai (TechCarte::journee, la même
        // source que les réponses d'une photo et de « Terminé »).
        $journee = \App\Support\TechCarte::journee($tech);

        // Une entrée par panneau, ou par AXE pour ses pieds (même campagne),
        // avec ses pieds déjà faits pour dessiner toute la route.
        $itemsByCommune = $groupedByCommune->map(fn ($tasks) => self::completerAxes(self::entreesParAxe($tasks), $tech));

        return [
            'jourFaites'       => $journee['faites'],
            'jourTotal'        => $journee['total'],
            'itemsByCommune'   => $itemsByCommune,
            'totalActive'      => $totalActive,
            'totalRendered'    => $totalRendered,
            'totalDone'        => $totalDone,
            'totalAssigned'    => $totalAssigned,
            'progressPct'      => $progressPct,
            'groupedByCommune' => $groupedByCommune,
            'doneByCommune'    => $doneByCommune,
            'donePosesRecent'  => $donePosesRecent, // SM2a Lot 1.4
            'allZones'         => $allZones,
            'nextTask'         => $nextTask,
            // Métriques journée
            'doneToday'        => $doneToday,
            'activeToday'      => $activeToday,
            'pigesSentToday'   => $pigesSentToday,
            'zonesTodayCount'  => $zonesTodayCount,
            'zonesTodayList'   => $zonesActiveToday->merge($zonesDoneToday)->unique()->values()->all(),
            // Piges global tech (pour le bouton historique)
            'pigesTotal'       => $pigesTotal,
            'pigesRejected'    => $pigesRejected,
            'rejectedPiges'    => $rejectedPiges, // SM2a Lot 5.2 (drawer T9)
        ];
    }

    /**
     * GET /tech/{token}/poses/search
     *
     * Source AJAX pour Select2 — recherche full-text sur référence, nom,
     * commune, campagne, adresse, quartier, client. Renvoie un payload
     * paginé (per_page=20 par défaut) avec metadata par pose pour rendre
     * directement la card si elle n'est pas en SSR.
     *
     * Paramètres :
     *   - q          : texte de recherche (str)
     *   - commune    : filtre commune exacte (str)
     *   - status     : filtre statut (planifiee|en_route|en_cours)
     *   - late       : 1 = uniquement en retard
     *   - today      : 1 = uniquement échéance aujourd'hui
     *   - problem    : 1 = uniquement avec signalement non résolu
     *   - reject     : 1 = uniquement avec dernière pige refusée
     *   - sort       : 'urgency' (défaut) | 'distance' (avec ?lat & ?lng)
     *   - page       : pagination Select2
     *   - per_page   : taille de page (max 50)
     */
    public function searchPoses(Request $request, string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['results' => [], 'pagination' => ['more' => false]], 404);

        $q        = trim((string) $request->input('q', ''));
        $commune  = trim((string) $request->input('commune', ''));
        $status   = trim((string) $request->input('status', ''));
        $late     = (int) $request->input('late', 0) === 1;
        $today    = (int) $request->input('today', 0) === 1;
        $problem  = (int) $request->input('problem', 0) === 1;
        $reject   = (int) $request->input('reject', 0) === 1;
        $sort     = $request->input('sort', 'urgency');
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = min(50, max(5, (int) $request->input('per_page', 20)));

        $validStatus = ['planifiee', 'en_route', 'en_cours'];
        if (!in_array($status, $validStatus, true)) $status = '';

        $todayDate = Carbon::today();
        $startOfDay = $todayDate->copy()->startOfDay();
        $endOfDay   = $todayDate->copy()->endOfDay();

        $base = PoseTask::with([
                // Axes (2026-11-12) : position de l'axe pour ses pieds (Panel::positionTerrain).
                'panel:id,reference,name,commune_id,format_id,adresse,quartier,latitude,longitude' . self::colonnesAxe(),
                ...self::relationsAxe(),
                'panel.commune:id,name',
                'panel.format:id,name',
                'panel.photos:id,panel_id,path,ordre',
                'campaign:id,name,client_id,status',
                'campaign.client:id,name',
                'lastProblemReport',
                'latestRejectedPige',
            ])
            ->where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->onOperableCampaign();

        if ($status !== '') {
            $base->where('status', $status);
        }
        if ($late) {
            $base->where('scheduled_at', '<', $startOfDay);
        }
        if ($today) {
            $base->whereBetween('scheduled_at', [$startOfDay, $endOfDay]);
        }
        if ($commune !== '') {
            $base->whereHas('panel.commune', fn($qq) => $qq->where('name', $commune));
        }
        if ($problem) {
            $base->whereHas('lastProblemReport');
        }
        if ($reject) {
            $base->whereHas('latestRejectedPige');
        }
        if ($q !== '') {
            // Recherche texte sur les champs visibles côté tech. Pas de
            // FTS (MySQL natif suffit pour ces volumes), juste des LIKE
            // qualifiés avec eager-load via whereHas.
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $base->where(function ($qq) use ($like) {
                $qq->whereHas('panel', function ($pp) use ($like) {
                    $pp->where('reference', 'like', $like)
                       ->orWhere('name', 'like', $like)
                       ->orWhere('adresse', 'like', $like)
                       ->orWhere('quartier', 'like', $like);
                })->orWhereHas('panel.commune', fn($cc) => $cc->where('name', 'like', $like))
                  ->orWhereHas('campaign', fn($cc) => $cc->where('name', 'like', $like))
                  ->orWhereHas('campaign.client', fn($cc) => $cc->where('name', 'like', $like));
            });
        }

        // Tri par défaut : urgence (retard d'abord, puis échéance)
        $base->orderByRaw('(scheduled_at IS NULL) ASC')->orderBy('scheduled_at');

        $totalMatches = (clone $base)->count();
        $tasks = $base->skip(($page - 1) * $perPage)->take($perPage + 1)->get();
        $hasMore = $tasks->count() > $perPage;
        if ($hasMore) $tasks = $tasks->take($perPage);

        // Tri par distance optionnel — recalcul côté PHP si on a lat/lng tech
        if ($sort === 'distance' && $request->filled(['lat', 'lng'])) {
            $tLat = (float) $request->input('lat');
            $tLng = (float) $request->input('lng');
            $tasks = $tasks->sortBy(function ($t) use ($tLat, $tLng) {
                // Un pied sans position prend celle de son axe (2026-11-12).
                $pos = $t->panel?->positionTerrain();
                if (!$pos) return PHP_FLOAT_MAX;
                return self::haversine($tLat, $tLng, $pos['lat'], $pos['lng']);
            })->values();
        }

        $results = $tasks->map(function ($t) {
            $status = $t->status instanceof PoseTaskStatus
                ? $t->status
                : PoseTaskStatus::tryFrom((string) $t->status);
            $firstPhoto = $t->panel?->photos?->sortBy('ordre')->first();
            $thumb = $firstPhoto ? asset('storage/' . $firstPhoto->path) : null;
            $isLateLocal = $t->scheduled_at && Carbon::parse($t->scheduled_at)->lt(PoseTask::lateThreshold());

            // Texte affiché dans Select2 : ref + name + commune (court)
            $ref     = $t->panel?->reference ?? '#' . $t->id;
            $name    = $t->panel?->name ?? '';
            $communeName = $t->panel?->commune?->name ?? '—';
            $text    = trim($ref . ' · ' . $communeName . ($name ? ' — ' . \Illuminate\Support\Str::limit($name, 50) : ''));

            return [
                'id'           => $t->id,
                'text'         => $text,
                'ref'          => $ref,
                'name'         => $name,
                'commune'      => $communeName,
                'adresse'      => $t->panel?->adresse,
                'quartier'     => $t->panel?->quartier,
                'campaign'     => $t->campaign?->name,
                'client'       => $t->campaign?->client?->name,
                'status'       => $status?->value,
                'status_label' => $status?->label(),
                'status_color' => $status?->color(),
                'is_late'      => (bool) $isLateLocal,
                'has_problem'  => $t->lastProblemReport !== null,
                'has_reject'   => $t->latestRejectedPige !== null,
                'reject_reason'=> $t->latestRejectedPige?->rejection_reason,
                'lat'          => $t->panel?->positionTerrain()['lat'] ?? null,
                'lng'          => $t->panel?->positionTerrain()['lng'] ?? null,
                'thumb_url'    => $thumb,
                'scheduled_at' => optional($t->scheduled_at)->toIso8601String(),
                'format'       => $t->panel?->format?->name,
            ];
        })->values();

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => $hasMore],
            'total'      => $totalMatches,
        ]);
    }

    /**
     * GET /tech/{token}/poses/route-sheet
     *
     * Feuille de route imprimable / PDF — toutes les poses actives du
     * tech sur une page A4 portrait, groupées par commune, avec
     * référence, nom, adresse, GPS, format, échéance, statut, campagne.
     *
     * Permet au tech d'imprimer sa journée le matin et de bosser même
     * sans réseau (filet de sécurité offline).
     */
    public function routeSheet(string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) abort(404, 'Lien invalide ou compte désactivé.');

        // Sur la feuille de route on charge TOUT, pas le cap SSR. Le tech
        // imprime / sauvegarde en PDF et utilise ça comme référence terrain.
        $activeTasks = PoseTask::with([
                // Axes (2026-11-12) : position de l'axe pour ses pieds (Panel::positionTerrain).
                'panel:id,reference,name,commune_id,format_id,adresse,quartier,latitude,longitude' . self::colonnesAxe(),
                ...self::relationsAxe(),
                'panel.commune:id,name',
                'panel.format:id,name',
                'campaign:id,name,client_id',
                'campaign.client:id,name',
            ])
            ->where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->onOperableCampaign()
            ->orderBy('scheduled_at')
            ->get();

        $lateThreshold = PoseTask::lateThreshold();
        $isLate = fn($t) => $t->scheduled_at && Carbon::parse($t->scheduled_at)->lt($lateThreshold);

        $groupedByCommune = $activeTasks
            ->sortBy(fn($t) => [$isLate($t) ? 0 : 1, optional($t->scheduled_at)->timestamp ?? PHP_INT_MAX])
            ->groupBy(fn($t) => $t->panel?->commune?->name ?? 'Sans commune')
            ->sortBy(fn($g, $k) => $k);

        return view('public.tech-route-sheet', [
            'tech'             => $tech,
            'token'            => $token,
            'total'            => $activeTasks->count(),
            'groupedByCommune' => $groupedByCommune,
            'isLate'           => $isLate,
        ]);
    }

    /**
     * Distance haversine (mètres) entre 2 points GPS.
     * Utilisé pour le tri par distance dans searchPoses() — précision
     * suffisante pour ordonner une tournée intra-ville.
     */
    protected static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0; // rayon terrestre en mètres
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * GET /tech/{token}/poses/map
     *
     * Vue Carte (Leaflet) — affiche toutes les poses actives du tech
     * sur une carte avec clustering, markers colorés par statut, popup
     * d'actions par pose (Y aller, Voir la pose, Photo). Indispensable
     * au-delà de 30 poses pour visualiser la densité géographique et
     * planifier la tournée.
     */
    public function map(string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) abort(404, 'Lien invalide ou compte désactivé.');

        // On charge TOUT (pas le cap SSR) avec uniquement les données
        // nécessaires au rendu carte : ref, name, commune, lat/lng,
        // status, échéance. Les markers sans GPS sont exclus côté front.
        $tasks = PoseTask::with([
                // Axes (2026-11-12) : position de l'axe pour ses pieds (Panel::positionTerrain).
                'panel:id,reference,name,commune_id,format_id,adresse,quartier,latitude,longitude' . self::colonnesAxe(),
                ...self::relationsAxe(),
                'panel.commune:id,name',
                'campaign:id,name',
            ])
            ->where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->onOperableCampaign()
            ->orderBy('scheduled_at')
            ->get();

        $lateThreshold = PoseTask::lateThreshold();

        // Refonte, étape 3 (2026-11-12) : UN arrêt par axe (ses pieds, même
        // campagne — même regroupement que « Ma journée », entreesParAxe), à
        // la position terrain (Panel::positionTerrain : un pied sans position
        // prend celle de son axe). Avant : seule la position propre du
        // panneau comptait, et les pieds découpés disparaissaient de la carte.
        $points = collect();
        $sansPosition = [];
        foreach (self::entreesParAxe($tasks) as $e) {
            $t = $e['tasks']->first();
            $pos = $e['tasks']->map(fn ($x) => $x->panel?->positionTerrain())->filter()->first();
            $axe = $e['type'] === 'axe' ? $e['axe'] : null;
            $ref = $axe?->reference ?? $t->panel?->reference ?? '#' . $t->id;
            if (!$pos) {
                $sansPosition[] = $ref;
                continue;
            }
            $status = PoseTaskStatus::tryFrom(self::statutEntree($e['tasks']));
            $sched = $e['tasks']->pluck('scheduled_at')->filter()->min();
            $points->push([
                'id'       => $t->id,
                'ids'      => $e['tasks']->pluck('id')->values()->all(),
                'axe'      => $axe ? $axe->id . '-' . $t->campaign_id : null,
                'pieds'    => $axe ? $e['tasks']->count() : null,
                'lat'      => $pos['lat'],
                'lng'      => $pos['lng'],
                'ref'      => $ref,
                'name'     => $axe ? app(\App\Services\Axes\AxeService::class)->nomCourt($axe->name) : ($t->panel?->name ?? ''),
                'commune'  => $t->panel?->commune?->name ?? '',
                'adresse'  => $t->panel?->adresse,
                'campaign' => $t->campaign?->name,
                'status'   => $status?->value,
                'status_label' => $status?->label(),
                'status_color' => $status?->color(),
                'is_late'  => $e['tasks']->contains(fn ($x) => $x->scheduled_at && Carbon::parse($x->scheduled_at)->lt($lateThreshold)),
                'sched'    => $sched ? Carbon::parse($sched)->format('d/m H:i') : null,
            ]);
        }

        return view('public.tech-map', [
            'tech'         => $tech,
            'token'        => $token,
            'points'       => $points->values(),
            'totalTasks'   => $tasks->count(),
            'withoutGps'   => count($sansPosition),
            'sansPosition' => $sansPosition,
        ]);
    }

    /**
     * GET /tech/{token}/poses/optimize?lat=&lng=
     *
     * Ordre de tournée optimisé via heuristique nearest-neighbor :
     * partant de la position du tech, on choisit à chaque étape la
     * pose la plus proche encore non visitée. Pas optimal au sens
     * strict (TSP NP-difficile), mais suffisamment proche pour
     * l'usage terrain et instantané même à 200+ poses.
     *
     * Renvoie l'ordre des task_id + distances cumulées en mètres.
     * L'UI re-ordonne les cards localement avec ces task_id.
     */
    public function optimizeTour(Request $request, string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false], 404);

        $request->validate([
            'lat'   => 'required|numeric',
            'lng'   => 'required|numeric',
            'scope' => 'nullable|in:rendered,all', // 'rendered' = limit aux ids fournis
            'ids'   => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $startLat = (float) $request->input('lat');
        $startLng = (float) $request->input('lng');
        $scope    = $request->input('scope', 'all');
        $renderedIds = collect((array) $request->input('ids', []))->map(fn($x) => (int) $x)->filter()->all();

        $q = PoseTask::with([
                'panel:id,reference,latitude,longitude' . self::colonnesAxe(),
                ...self::relationsAxe(),
            ])
            ->where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            // 2026-11-12 : les mêmes poses que la carte et « Ma journée »
            // (sans ce filtre, le chemin passait par des poses de campagnes
            // en pause, absentes de la carte, et ses kilomètres mentaient).
            ->onOperableCampaign();
        if ($scope === 'rendered' && !empty($renderedIds)) {
            $q->whereIn('id', $renderedIds);
        }
        $tasks = $q->get(['id', 'panel_id', 'campaign_id']);

        // Garde uniquement les arrêts géolocalisés (sans GPS = ignorés du
        // calcul mais retournés en fin de liste pour info). Refonte, étape 3 :
        // un arrêt par axe (ses pieds sont un seul lieu, entreesParAxe), à la
        // position terrain (un pied sans position prend celle de son axe).
        $geo  = [];
        $noGps = [];
        foreach (self::entreesParAxe($tasks) as $e) {
            $t = $e['tasks']->first();
            $pos = $e['tasks']->map(fn ($x) => $x->panel?->positionTerrain())->filter()->first();
            $ref = $e['type'] === 'axe' ? $e['axe']->reference : $t->panel?->reference;
            $ids = $e['tasks']->pluck('id')->values()->all();
            if ($pos) {
                $geo[] = ['id' => $t->id, 'ids' => $ids, 'lat' => $pos['lat'], 'lng' => $pos['lng'], 'ref' => $ref];
            } else {
                $noGps[] = ['id' => $t->id, 'ids' => $ids, 'ref' => $ref];
            }
        }

        // Heuristique nearest-neighbor : O(N²) — acceptable jusqu'à
        // quelques milliers de points (≈ 200 ms pour 1000 points).
        $order = [];
        $cumulMeters = 0.0;
        $curLat = $startLat; $curLng = $startLng;
        $remaining = $geo;
        while (!empty($remaining)) {
            $bestIdx = null;
            $bestD   = INF;
            foreach ($remaining as $i => $p) {
                $d = self::haversine($curLat, $curLng, $p['lat'], $p['lng']);
                if ($d < $bestD) { $bestD = $d; $bestIdx = $i; }
            }
            $chosen = $remaining[$bestIdx];
            $cumulMeters += $bestD;
            $order[] = [
                'id'           => $chosen['id'],
                'ids'          => $chosen['ids'],   // les pieds d'un axe (ou la seule pose)
                'ref'          => $chosen['ref'],
                'leg_meters'   => (int) round($bestD),
                'cumul_meters' => (int) round($cumulMeters),
            ];
            $curLat = $chosen['lat']; $curLng = $chosen['lng'];
            array_splice($remaining, $bestIdx, 1);
        }

        return response()->json([
            'ok'           => true,
            'count'        => count($order),
            'total_meters' => (int) round($cumulMeters),
            'order'        => $order,
            'no_gps'       => $noGps,
        ]);
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

        // ⚠ PoseTask::$status n'est PAS casté en enum (décision conservée
        // pour ne pas casser les comparaisons string existantes), donc on
        // doit le résoudre manuellement avant d'appeler ->allowedTransitions().
        // Sans ça : "Call to a member function allowedTransitions() on string"
        // au clic « Sur place » → 500 silencieux côté tech.
        $current = PoseTaskStatus::tryFrom((string) $task->status);
        if (!$current) {
            return response()->json([
                'ok'    => false,
                'error' => "Statut actuel inconnu (« {$task->status} ») — contacte ton superviseur.",
            ], 422);
        }

        // Transition same-status = no-op idempotent (200 ok silencieux).
        // Utile pour le clic répété "Y aller" quand la pose est DÉJÀ en
        // en_route : le tech clique X fois, le serveur ne râle pas, le
        // started_at reste posé à la première fois. Sans ça, le 2e clic
        // renvoyait 422 → log serveur pollué, expérience tech opaque.
        // Feedback patronne 2026-07-01 : "sur les autres poses ça a l'air
        // différent" → cette différence perçue vient du 422 silencieux.
        if ($newStatus === $current) {
            return response()->json(['ok' => true, 'noop' => true]);
        }

        // Vérifie la transition est autorisée (cf. PoseTaskStatus::allowedTransitions)
        if (!in_array($newStatus, $current->allowedTransitions(), true)) {
            return response()->json([
                'ok' => false,
                'error' => "Transition interdite : {$current->label()} → {$newStatus->label()}.",
            ], 422);
        }

        // EXCLUSIVITÉ "EN ROUTE" (feedback patronne 2026-07-07)
        // Un tech ne peut être en route que vers UN endroit à la fois.
        // Si la nouvelle transition est EN_ROUTE (ou IN_PROGRESS via saut
        // direct), on repose ses AUTRES poses "en route / sur place" à
        // planifiée. started_at + arrived_at effacés → l'ancienne
        // navigation est propre côté BDD.
        // Le client peut faire un pré-check via GET /active-poses avant
        // POST — mais on ne renvoie PAS 409 ici pour garder l'API idem-
        // potente. La confirmation UX est gérée côté modal T7.
        $revertedIds = [];
        // Axe (2026-11-12) : ses pieds sont UN lieu — ils ne se repoussent
        // pas entre eux, ils suivent (cf. PoseTask::idsFreresDAxe).
        $freres = $task->idsFreresDAxe();
        if (in_array($newStatus, [PoseTaskStatus::EN_ROUTE, PoseTaskStatus::IN_PROGRESS], true)) {
            $revertedIds = PoseTask::where('assigned_user_id', $tech->id)
                ->where('id', '!=', $task->id)
                ->whereNotIn('id', $freres)
                ->whereIn('status', [PoseTaskStatus::EN_ROUTE->value, PoseTaskStatus::IN_PROGRESS->value])
                ->pluck('id')
                ->all();
            if (!empty($revertedIds)) {
                PoseTask::whereIn('id', $revertedIds)->update([
                    'status'           => PoseTaskStatus::PLANNED->value,
                    'progress_percent' => 0,
                    'started_at'       => null,
                    'arrived_at'       => null,
                ]);
            }
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
            // real_minutes 2026-07-06 : sémantique changée = temps réel
            // SUR SITE (arrived_at → done_at), plus le temps total sprint
            // incluant trajet. Le trajet reste mesurable via
            // arrived_at - started_at côté service KPI.
            // Fallback sur started_at si arrived_at pas posé (rétrocompat).
            $anchor = $task->arrived_at ?? $task->started_at;
            if ($anchor) {
                $update['real_minutes'] = max(1, (int) round($anchor->diffInMinutes(now())));
            }
        }

        $task->update($update);

        // Axe : « Y aller » / « Je suis arrivé » valent pour tous ses pieds
        // (sans jamais en faire reculer un, ni en clore un sans sa photo).
        if ($freres && in_array($newStatus, [PoseTaskStatus::EN_ROUTE, PoseTaskStatus::IN_PROGRESS], true)) {
            $this->avancerFreresDAxe($freres, $newStatus);
        }
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
            'reverted_ids'  => $revertedIds,  // Poses "en route" reposées à planifiée (exclusivité)
            // Refonte 2026-11-12 : « Terminé » (axe entier) met l'en-tête à jour.
            'jour'          => $newStatus === PoseTaskStatus::COMPLETED ? \App\Support\TechCarte::journee($tech) : null,
        ] + ($newStatus === PoseTaskStatus::COMPLETED
            // … et propose la pose suivante, comme après une photo.
            ? $this->avecPoseSuivante($tech)
            : []));
    }

    /**
     * Colonnes d'axe à ajouter à une projection `panel:…` — de quoi appeler
     * Panel::positionTerrain() (un pied prend la position de son axe) et
     * regrouper les pieds (entreesParAxe). Vide tant que la migration des
     * axes n'est pas passée.
     */
    private static function colonnesAxe(): string
    {
        return app(\App\Services\Axes\AxeService::class)->colonnesPanneau();
    }

    /**
     * Statut affiché d'une entrée (une pose, ou les pieds d'un axe) : le plus
     * avancé de ses tâches — sur place, sinon en route, sinon planifiée.
     * Une seule règle pour la carte d'axe (_tx_axe) et l'arrêt sur la carte.
     *
     * @param \Illuminate\Support\Collection<int, PoseTask> $tasks
     */
    public static function statutEntree($tasks): string
    {
        $statuts = collect($tasks)->map(fn ($t) => (string) $t->status);

        return match (true) {
            $statuts->contains(PoseTaskStatus::IN_PROGRESS->value) => PoseTaskStatus::IN_PROGRESS->value,
            $statuts->contains(PoseTaskStatus::EN_ROUTE->value)    => PoseTaskStatus::EN_ROUTE->value,
            default                                                => PoseTaskStatus::PLANNED->value,
        };
    }

    /** L'axe d'un pied (position, nom, photo) — à charger avec colonnesAxe(). */
    private static function relationsAxe(): array
    {
        return app(\App\Services\Axes\AxeService::class)->relationsAxe();
    }

    /**
     * Refonte de l'espace technicien (2026-11-12) : les pieds d'un même axe
     * (même campagne) deviennent UNE entrée, à la place du premier ; les
     * autres poses restent une entrée chacune. L'ordre (retard, échéance)
     * est conservé.
     *
     * @param  \Illuminate\Support\Collection<int, PoseTask> $tasks
     * @return list<array{type: string, task: PoseTask, tasks: \Illuminate\Support\Collection, axe: ?\App\Models\Panel}>
     */
    public static function entreesParAxe($tasks): array
    {
        $entrees = [];
        $position = [];
        foreach ($tasks as $t) {
            $axeId = $t->panel?->axe_id;
            if (!$axeId || !$t->panel?->axe) {
                $entrees[] = ['type' => 'pose', 'task' => $t, 'tasks' => collect([$t]), 'axe' => null];
                continue;
            }
            $cle = $axeId . '-' . $t->campaign_id;
            if (!isset($position[$cle])) {
                $position[$cle] = count($entrees);
                $entrees[] = ['type' => 'axe', 'task' => $t, 'tasks' => collect(), 'axe' => $t->panel->axe];
            }
            $entrees[$position[$cle]]['tasks']->push($t);
        }
        foreach ($entrees as &$e) {
            if ($e['type'] === 'axe') {
                $e['tasks'] = $e['tasks']->sortBy(fn ($t) => $t->panel?->pied_numero ?? 0)->values();
            }
        }
        unset($e);

        return $entrees;
    }

    /**
     * Ajoute à chaque entrée d'axe ses pieds DÉJÀ FAITS par ce technicien sur
     * cette campagne, avec l'état de leur dernière photo (envoyée / validée) :
     * la carte dessine ainsi toute la route, pas seulement ce qui reste.
     *
     * @return list<array>  les entrées, avec 'faites' => list<array{task: PoseTask, pige: ?\App\Models\Pige}>
     */
    public static function completerAxes(array $entrees, User $tech): array
    {
        foreach ($entrees as &$e) {
            $e['faites'] = [];
            if ($e['type'] !== 'axe') {
                continue;
            }
            $faites = PoseTask::where('assigned_user_id', $tech->id)
                ->where('campaign_id', $e['task']->campaign_id)
                ->where('status', PoseTaskStatus::COMPLETED->value)
                ->whereHas('panel', fn ($q) => $q->where('axe_id', $e['axe']->id))
                ->with('panel:id,reference,name,pied_numero,axe_id')
                ->get();
            $piges = \App\Models\Pige::whereIn('pose_task_id', $faites->pluck('id'))
                ->orderByDesc('taken_at')->get()->groupBy('pose_task_id');
            $e['faites'] = $faites
                ->sortBy(fn ($t) => $t->panel?->pied_numero ?? 0)
                ->map(fn ($t) => ['task' => $t, 'pige' => $piges->get($t->id)?->first()])
                ->values()->all();
        }
        unset($e);

        return $entrees;
    }

    /**
     * ['suivante' => …] à ajouter à la réponse — ou RIEN si le calcul échoue :
     * la photo (ou « Terminé ») est déjà enregistrée, une erreur ici ne doit
     * ni la faire paraître ratée (le technicien la renverrait), ni vider
     * « Maintenant » (sans la clé, la page garde sa liste).
     */
    private function avecPoseSuivante(User $tech, ?float $lat = null, ?float $lng = null): array
    {
        try {
            return ['suivante' => $this->poseSuivante($tech, $lat, $lng)];
        } catch (\Throwable $e) {
            Log::warning('tech.space.pose_suivante_failed', ['tech_id' => $tech->id, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * La pose suivante après une photo ou « Terminé » (refonte, étape 2) : la nouvelle
     * « Maintenant », choisie par la MÊME règle que la page (buildPayload →
     * $nextTask) et rendue par le MÊME gabarit (_tx_maintenant_section). Le
     * téléphone n'a ni règle ni gabarit à recopier : il remplace la section
     * et affiche la feuille « Photo envoyée ».
     *
     * @return array{task_id: int, cle: ?string, reference: ?string, nom: ?string, commune: ?string, distance_m: ?int, photo_url: ?string, html: ?string}|null
     */
    private function poseSuivante(User $tech, ?float $lat, ?float $lng): ?array
    {
        $p = $this->buildPayload($tech);
        $next = $p['nextTask'] ?? null;
        if (!$next) {
            return null;
        }

        $entree = null;
        foreach ($p['itemsByCommune'] ?? [] as $entrees) {
            foreach ($entrees as $e) {
                if ($e['tasks']->contains('id', $next->id)) {
                    $entree = $e;
                    break 2;
                }
            }
        }
        $axe = ($entree && $entree['type'] === 'axe') ? $entree['axe'] : null;

        // « à 3,4 km » : depuis l'endroit de la photo, à vol d'oiseau.
        $pos = $next->panel?->positionTerrain();
        $distance = ($lat !== null && $lng !== null && $pos)
            ? (int) round(app(\App\Services\GeoService::class)->haversine($lat, $lng, $pos['lat'], $pos['lng']))
            : null;

        return [
            'task_id'    => $next->id,
            'cle'        => $axe ? $axe->id . '-' . $next->campaign_id : null,
            'reference'  => $axe?->reference ?? $next->panel?->reference,
            'nom'        => $axe ? app(\App\Services\Axes\AxeService::class)->nomCourt($axe->name) : $next->panel?->name,
            'commune'    => $next->panel?->commune?->name,
            'distance_m' => $distance,
            'photo_url'  => \App\Support\TechCarte::donnees($next)['photoUrl'],
            // Au-delà du plafond d'affichage, l'entrée n'est pas rendue :
            // la feuille l'annonce quand même, la section reste telle quelle.
            'html'       => $entree
                ? view('public.tech.partials._tx_maintenant_section', ['entree' => $entree])->render()
                : null,
        ];
    }

    /**
     * Fait avancer les pieds sœurs d'un axe jusqu'au statut donné (2026-11-12) :
     * en route ou sur place. Ne fait jamais reculer un pied, ne le clôt jamais
     * (chaque pied se clôt par SA photo).
     *
     * @param int[] $ids  PoseTask::idsFreresDAxe()
     */
    private function avancerFreresDAxe(array $ids, PoseTaskStatus $cible): void
    {
        $rang = [
            PoseTaskStatus::PLANNED->value     => 0,
            PoseTaskStatus::EN_ROUTE->value    => 1,
            PoseTaskStatus::IN_PROGRESS->value => 2,
        ];
        foreach (PoseTask::whereIn('id', $ids)->get() as $frere) {
            $actuel = (string) $frere->status;
            if (!isset($rang[$actuel]) || $rang[$actuel] >= $rang[$cible->value]) {
                continue;
            }
            $maj = [
                'status'           => $cible->value,
                'started_at'       => $frere->started_at ?? now(),
                'progress_percent' => max((int) $frere->progress_percent, $cible === PoseTaskStatus::IN_PROGRESS ? 50 : 25),
            ];
            if ($cible === PoseTaskStatus::IN_PROGRESS && !$frere->arrived_at) {
                $maj['arrived_at'] = now();
            }
            $frere->update($maj);
        }
    }

    /**
     * POST /tech/{token}/poses/{task}/progress
     *
     * Le tech renseigne sa progression manuelle (paliers 0/25/50/75/100)
     * pendant qu'il pose. Feature demandée par la patronne le 2026-07-06
     * pour que l'admin voie temps réel où en est le tech sur chaque pose.
     *
     * Règles métier :
     *   - N'accepte que 0/25/50/75/100 (paliers validés)
     *   - Interdit sur pose terminée (realisee/annulee) — no-op
     *   - Interdit de RECULER : si progression actuelle >= nouvelle, no-op
     *   - Auto-sync statut discret : si tech saute au-delà d'un palier
     *     naturel du statut, on rattrape (mais on ne recule jamais)
     *       25% → statut passe à en_route (si encore planifiee)
     *       50%+ → statut passe à en_cours (si encore planifiee/en_route)
     *     Le 100% n'est PAS forcé ici — reste au flux photo (uploadPhoto)
     *     qui gère done_at + real_minutes proprement.
     */
    public function updateProgress(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('role', UserRole::TECHNIQUE->value)
            ->firstOrFail();

        $task = PoseTask::where('id', $taskId)
            ->where('assigned_user_id', $tech->id)
            ->firstOrFail();

        $data = $request->validate([
            'percent' => ['required', 'integer', 'in:0,25,50,75,100'],
        ]);
        $newPct = (int) $data['percent'];

        // Pose terminée = read-only.
        $currentStatus = PoseTaskStatus::tryFrom((string) $task->status);
        if ($currentStatus && $currentStatus->isTerminal()) {
            return response()->json([
                'ok' => false,
                'error' => 'Cette pose est déjà clôturée.',
            ], 422);
        }

        // 2026-07-06 (feedback patronne) : régression AUTORISÉE — le tech
        // doit pouvoir corriger une saisie erronée. No-op si valeur identique.
        $currentPct = (int) ($task->progress_percent ?? 0);
        if ($newPct === $currentPct) {
            return response()->json(['ok' => true, 'noop' => true, 'percent' => $currentPct]);
        }

        $update = ['progress_percent' => $newPct];

        // Auto-sync statut à la MONTÉE : le tech qui saute des paliers
        // (ex : tape direct 50% sans cliquer Y aller) fait basculer le
        // statut automatiquement.
        if ($newPct >= 25 && $currentStatus === PoseTaskStatus::PLANNED) {
            $update['status']     = PoseTaskStatus::EN_ROUTE->value;
            $update['started_at'] = $task->started_at ?? now();
        }
        if ($newPct >= 50 && in_array($currentStatus, [PoseTaskStatus::PLANNED, PoseTaskStatus::EN_ROUTE], true)) {
            $update['status']     = PoseTaskStatus::IN_PROGRESS->value;
            $update['started_at'] = $task->started_at ?? now();
        }

        // arrived_at : posé au 50%+, ANNULÉ si le tech redescend sous 50%
        // (correction de saisie). Le SLA restera juste car aucune donnée
        // vraie ne sera perdue tant que la pose n'est pas terminée.
        if ($newPct >= 50 && !$task->arrived_at) {
            $update['arrived_at'] = now();
        }
        if ($newPct < 50 && $task->arrived_at) {
            $update['arrived_at'] = null;
        }
        // Si le tech redescend à 0%, on remet en planifiee et on efface
        // started_at (sinon KPI de réactivité fausse ces poses "flottantes").
        if ($newPct === 0) {
            $update['status']     = PoseTaskStatus::PLANNED->value;
            $update['started_at'] = null;
            $update['arrived_at'] = null;
        }

        // EXCLUSIVITÉ STRICTE (feedback patronne 2026-07-07 v2)
        // Un tech ne peut avoir qu'UNE SEULE pose active (en_route ou
        // en_cours) à la fois. Quand il passe une nouvelle pose à 25%+
        // (donc devient active), TOUTES les autres poses actives sont
        // reposées à PLANIFIÉE (pas en_route) : started_at + arrived_at
        // effacés. Sinon on se retrouvait avec plusieurs "en route" et
        // "sur place" en parallèle (bug capture patronne).
        $revertedIds = [];
        $freres = $task->idsFreresDAxe(); // axe : ses pieds sont UN lieu (2026-11-12)
        if ($newPct >= 25) {
            $revertedIds = PoseTask::where('assigned_user_id', $tech->id)
                ->where('id', '!=', $task->id)
                ->whereNotIn('id', $freres)
                ->whereIn('status', [PoseTaskStatus::EN_ROUTE->value, PoseTaskStatus::IN_PROGRESS->value])
                ->pluck('id')
                ->all();
            if (!empty($revertedIds)) {
                PoseTask::whereIn('id', $revertedIds)->update([
                    'status'           => PoseTaskStatus::PLANNED->value,
                    'progress_percent' => 0,
                    'started_at'       => null,
                    'arrived_at'       => null,
                ]);
            }
        }

        $task->update($update);

        // Axe : « Je suis arrivé » (50 %) démarre tous ses pieds — validé
        // le 2026-11-12. On ne fait que monter, jamais reculer.
        if ($freres && $newPct >= 25) {
            $this->avancerFreresDAxe($freres, $newPct >= 50 ? PoseTaskStatus::IN_PROGRESS : PoseTaskStatus::EN_ROUTE);
        }
        self::invalidateCache($tech->id);

        Log::info('tech.space.progress_updated', [
            'task_id' => $task->id,
            'tech_id' => $tech->id,
            'percent' => $newPct,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'ok'           => true,
            'percent'      => $newPct,
            'status'       => $update['status'] ?? $task->status,
            'reverted_ids' => $revertedIds,  // IDs des poses "sur place" reposées à en_route (exclusivité)
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

        // SM2b Lot 1.1 — stamp last_seen_at à chaque heartbeat (20s).
        // Permet au AdminLiveDashboardService de déterminer les techs
        // en ligne (last_seen_at < 10 min). updateQuietly() pour ne pas
        // déclencher d'events (audit, notif) sur un simple ping.
        $tech->forceFill(['last_seen_at' => now()])->saveQuietly();

        $today      = Carbon::today();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay   = $today->copy()->endOfDay();

        $totalActive = PoseTask::where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
            ->onOperableCampaign()
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
            ->onOperableCampaign()
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
        // Heartbeat pige : même règle que buildPayload — pigesRejected
        // ne compte QUE les piges actionnables (campagne opérable). Sinon
        // le badge "à refaire" restait allumé pour des piges non-refaisables.
        $onOperableCampaignHb = function ($q) {
            $q->whereExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('campaigns')
                    ->whereColumn('campaigns.id', 'piges.campaign_id')
                    ->whereNotIn('campaigns.status', [
                        \App\Enums\CampaignStatus::TERMINE->value,
                        \App\Enums\CampaignStatus::ANNULE->value,
                    ])
                    ->whereNull('campaigns.deleted_at');
            });
        };
        // Fix 2026-08-10 — cohérence heartbeat ↔ SSR piges() : ces 4
        // compteurs alimentent la page tech-piges qui applique par
        // défaut le mode "view=current" (dédup par panel+campaign,
        // seule la dernière pige compte). Sans cette même dédup ici,
        // le heartbeat écrase les valeurs SSR toutes les 20s avec des
        // chiffres "bruts" plus élevés → l'utilisateur voit les
        // compteurs sauter. Applique whereNotExists sur les 4.
        // Même règle que la page « Mes photos » (Pige::scopeDerniereParPanneau).
        $applyCurrentViewHb = fn ($q) => $q->derniereParPanneau();

        $pigesSentToday = \App\Models\Pige::query()->tap($pigesForTech)
            ->whereBetween('taken_at', [$startOfDay, $endOfDay])->count();
        $pigesTotal     = \App\Models\Pige::query()->tap($pigesForTech)->tap($applyCurrentViewHb)->count();
        $pigesPending   = \App\Models\Pige::query()->tap($pigesForTech)->tap($applyCurrentViewHb)
            ->where('piges.status', 'en_attente')->count();
        $pigesVerified  = \App\Models\Pige::query()->tap($pigesForTech)->tap($applyCurrentViewHb)
            ->where('piges.status', 'verifie')->count();
        // pigesRejected garde AUSSI le filtre onOperableCampaign (campagne
        // active) — mais utilise la même dédup pour cohérence entre les
        // 4 compteurs.
        $pigesRejected  = \App\Models\Pige::query()->tap($pigesForTech)->tap($onOperableCampaignHb)
            ->tap($applyCurrentViewHb)
            ->where('piges.status', 'rejete')
            ->count();

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
    /**
     * SM2c B3 — GET /tech/{token}/notifications
     * Liste des notifications du tech (30 dernières, JSON).
     */
    public function notifications(string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['items' => []], 404);

        $items = \App\Models\TechNotification::where('user_id', $tech->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'type', 'title', 'detail', 'payload', 'read_at', 'created_at'])
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'detail'     => $n->detail,
                'payload'    => $n->payload,
                'read_at'    => optional($n->read_at)->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all();

        return response()->json([
            'as_of' => now()->toIso8601String(),
            'items' => $items,
        ]);
    }

    /**
     * SM2c B3 — POST /tech/{token}/notifications/mark-read
     * Marque toutes les notifs du tech comme lues (ou une seule via ?id=).
     */
    public function notificationsMarkRead(Request $request, string $token)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false], 404);

        $q = \App\Models\TechNotification::where('user_id', $tech->id)
            ->whereNull('read_at');
        if ($id = $request->integer('id')) {
            $q->where('id', $id);
        }
        $count = $q->update(['read_at' => now()]);
        return response()->json(['ok' => true, 'updated' => $count]);
    }

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

        // ─── Mode de vue ─────────────────────────────────────────────
        // 'current' (défaut) : 1 ligne par panneau, garde uniquement la
        //   pige la plus récente. C'est le statut COURANT du panneau.
        //   Avant : un panneau dont la 1ère pige était refusée puis
        //   re-piggée apparaissait DEUX fois (refusée + en attente) et
        //   gonflait les KPI ("2 REFUSÉES" alors qu'aucun panneau ne
        //   l'est réellement encore).
        // 'all' : historique complet, toutes les piges (audit + suivi).
        $view = $request->input('view', 'current');
        if (!in_array($view, ['current', 'all'], true)) {
            $view = 'current';
        }

        // Helper : filtre robuste « piges de ce tech » réutilisé partout.
        // Soit user_id direct, soit rattachement via PoseTask assignée
        // (couvre les piges historiques où user_id pointait sur le
        // commercial créateur de la campagne — bug corrigé).
        $pigesForTech = function ($q) use ($tech) {
            $q->where(function ($qq) use ($tech) {
                $qq->where('piges.user_id', $tech->id)
                   ->orWhereExists(function ($sub) use ($tech) {
                       $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                           ->from('pose_tasks')
                           ->where('pose_tasks.assigned_user_id', $tech->id)
                           ->whereColumn('pose_tasks.panel_id',    'piges.panel_id')
                           ->whereColumn('pose_tasks.campaign_id', 'piges.campaign_id');
                   });
            });
        };

        // Helper : applique le filtre "vue courante" = pas de pige plus
        // récente sur le même (panel, campagne). Implémenté en NOT EXISTS
        // sur un alias `p2`. On ne filtre PAS par tech dans la sous-requête :
        // un panneau dans une campagne est de fait assigné à un seul tech
        // (cf. PoseTask unique par (panel, campaign)), donc toutes les
        // piges du même couple sont du même tech. Évite des jointures
        // corrélées coûteuses.
        // Règle unique (2026-11-12) : Pige::scopeDerniereParPanneau — un axe
        // entier garde toutes ses photos (une par pied).
        $applyCurrentView = fn ($q) => $q->derniereParPanneau();

        $piges = \App\Models\Pige::query()
            ->tap($pigesForTech)
            ->when($view === 'current', $applyCurrentView)
            ->when($statusFilter, fn($q, $s) => $q->where('piges.status', $s))
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name',
            ])
            ->orderByDesc('piges.taken_at')
            ->paginate(30)
            ->withQueryString();

        // KPI calculés dans le MÊME mode de vue que la liste — sinon le
        // compteur "REFUSÉES = 2" mentait par rapport à la liste affichée
        // (qui n'en montrait qu'une, ou zéro, après déduplication).
        $base = \App\Models\Pige::query()
            ->tap($pigesForTech)
            ->when($view === 'current', $applyCurrentView);
        $kpi = [
            'total'    => (clone $base)->count(),
            'pending'  => (clone $base)->where('piges.status', 'en_attente')->count(),
            'verified' => (clone $base)->where('piges.status', 'verifie')->count(),
            'rejected' => (clone $base)->where('piges.status', 'rejete')->count(),
        ];

        // Compteur total historique — utile pour suggérer "voir l'historique
        // complet" quand des piges sont masquées en mode courant.
        $historyTotal = $view === 'current'
            ? \App\Models\Pige::query()->tap($pigesForTech)->count()
            : $kpi['total'];

        // En mode 'all', marquer les piges qui ne sont PAS la plus récente
        // sur leur (panel, campagne) → la vue les grise + badge "ancienne".
        // Une seule requête whereIn + whereExists pour la page entière,
        // évite N+1 silencieux.
        if ($view === 'all') {
            $idsOnPage = $piges->pluck('id')->all();
            if (!empty($idsOnPage)) {
                // Remplacées = celles de la page que la règle unique écarte.
                $courantes = \App\Models\Pige::whereIn('id', $idsOnPage)->derniereParPanneau()->pluck('id')->all();
                $supersededIds = array_values(array_diff($idsOnPage, $courantes));
                $supersededSet = array_flip($supersededIds);
                $piges->getCollection()->transform(function ($p) use ($supersededSet) {
                    $p->is_superseded = isset($supersededSet[$p->id]);
                    return $p;
                });
            }
        }

        return view('public.tech-piges', compact('tech', 'token', 'piges', 'kpi', 'statusFilter', 'view', 'historyTotal'));
    }

    public function uploadPhoto(Request $request, string $token, int $taskId)
    {
        $tech = User::where('tech_public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$tech) return response()->json(['ok' => false, 'error' => 'Lien invalide ou compte désactivé.'], 404);

        $task = PoseTask::with([
                'campaign:id,name,status,user_id',
                // est_axe : un axe entier garde sa tâche ouverte (choix 4b) —
                // sans la colonne chargée, estAxe() répondrait toujours non.
                // axe_id : savoir s'il reste des pieds à faire sur l'axe.
                app(\App\Services\Axes\AxeService::class)->colonnesPresentes()
                    ? 'panel:id,reference,est_axe,axe_id'
                    : 'panel:id,reference',
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
                // Axe entier (choix 4b) : la tâche reste ouverte, même ici.
                $dejaAxeEntier = (bool) $task->panel?->estAxe();
                return response()->json([
                    'ok'        => true,
                    'pige_id'   => $existing->id,
                    'photo_url' => Storage::url($existing->photo_path),
                    'message'   => 'Photo déjà envoyée.',
                    'task_done' => !$dejaAxeEntier,
                    'axe_entier'   => $dejaAxeEntier,
                    'photos_count' => $dejaAxeEntier ? Pige::where('pose_task_id', $task->id)->count() : null,
                    'deja_envoyee' => true,
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
        // SM2c B1 — Détection "hors créneau" : la pige est marquée si le
        // tech envoie sa photo > 60 min avant OU > 60 min après l'heure
        // prévue (config tech_space.schedule_tolerance, défaut 60 min).
        // Côté admin, ce flag est affiché dans la timeline tech A2.
        $tolerance = (int) config('tech_space.schedule_tolerance', 60);
        $isOffSchedule = $task->scheduled_at
            && abs(now()->diffInMinutes($task->scheduled_at)) > $tolerance;

        $pige = Pige::create([
            'panel_id'        => $task->panel_id,
            'campaign_id'     => $task->campaign_id,
            'pose_task_id'    => $task->id,
            'user_id'         => $tech->id,
            'photo_path'      => $path,
            'taken_at'        => now(),
            'gps_lat'         => $data['gps_lat'] ?? null,
            'gps_lng'         => $data['gps_lng'] ?? null,
            'notes'           => implode(' · ', $noteParts),
            'status'          => 'en_attente',
            'is_off_schedule' => $isOffSchedule,
            'client_uuid'     => $data['client_uuid'] ?? null,
        ]);

        // Marque la tâche comme réalisée si pas déjà fait.
        // ⚠️ PoseTask::$status n'est PAS casté en enum (décision conservée
        // pour ne pas casser les comparaisons string existantes), donc on
        // passe par tryFrom() pour utiliser ->isTerminal() proprement.
        // Avant : $task->status->isTerminal() → "Call to a member function
        // isTerminal() on string" en prod, le tech voyait l'erreur PHP
        // alors que la pige était bel et bien créée.
        $currentStatus = PoseTaskStatus::tryFrom((string) $task->status);
        // Axe entier d'un ancien contrat (2026-11-12, choix 4b) : la tâche
        // reste ouverte, une photo par pied ; « Terminé » la ferme.
        $axeEntier = (bool) $task->panel?->estAxe();
        if ($axeEntier && in_array($currentStatus, [PoseTaskStatus::PLANNED, PoseTaskStatus::EN_ROUTE], true)) {
            // Une photo prouve qu'il est sur place : « Terminé » (en_cours →
            // réalisée) devient possible.
            $task->update([
                'status'           => PoseTaskStatus::IN_PROGRESS->value,
                'progress_percent' => max(60, (int) $task->progress_percent),
                'started_at'       => $task->started_at ?? now(),
                'arrived_at'       => $task->arrived_at ?? now(),
            ]);
        }
        if ($currentStatus && !$currentStatus->isTerminal() && !$axeEntier) {
            // Bump progression à 100% + done_at.
            // FIX 2026-07-01 — Ne PAS poser started_at=now() en fallback quand
            // il est NULL : ça produisait real_minutes = 0 min (durée bidon,
            // moyenne SLA polluée). Le tracking dépend du clic "Y aller"
            // (bindGoMaps dans status-changes.js qui bump en_route et pose
            // started_at). Si le tech saute cette étape (offline, skip,
            // clic direct photo), on laisse started_at NULL → la pose n'est
            // pas comptée dans la moyenne DURÉE MOY (comportement propre).
            $taskUpdate = [
                'status'           => PoseTaskStatus::COMPLETED->value,
                'done_at'          => now(),
                'progress_percent' => 100,
            ];
            // real_minutes 2026-07-06 : temps réel SUR SITE (arrived_at →
            // done_at). Fallback sur started_at si arrived_at pas posé.
            $anchor = $task->arrived_at ?? $task->started_at;
            if ($anchor) {
                $taskUpdate['real_minutes'] = max(1, (int) round($anchor->diffInMinutes(now())));
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
                // !empty : photo sans position (case « Joindre ma position »
                // décochée, ou GPS muet) → clé absente. L'accès direct levait
                // « Undefined array key » = erreur 500 APRÈS la création de
                // la pige (le tech voyait un échec et renvoyait). 2026-11-04.
                !empty($data['gps_lat']) ? "GPS : {$data['gps_lat']}, {$data['gps_lng']}" : null,
            ]),
            ctaLabel: 'Valider la pige →',
            ctaUrl: url('/admin/piges/' . $pige->id),
            footer: 'Pige #' . $pige->id,
            emoji: '📸',
            dedupKey: 'pige-uploaded-' . $pige->id,
        );

        // SM2c B2 — Compte les poses restantes du jour pour déclencher
        // l'écran "fin de journée" si on vient de finir la dernière.
        $today = \Carbon\Carbon::today();
        $todayTasksRemaining = PoseTask::where('assigned_user_id', $tech->id)
            ->whereBetween('scheduled_at', [$today->startOfDay(), $today->copy()->endOfDay()])
            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
            ->count();

        // Refonte, étape 2 (2026-11-12) : plus de rechargement après une
        // photo — la réponse porte la pose suivante (la nouvelle
        // « Maintenant »). Pas pour un axe entier (sa tâche reste ouverte),
        // ni pour un pied tant qu'il en reste sur l'axe (la carte suit).
        $piedsRestants = (!$axeEntier && $task->panel?->axe_id) ? count($task->idsFreresDAxe()) : 0;
        $suivante = (!$axeEntier && $piedsRestants === 0)
            ? $this->avecPoseSuivante(
                $tech,
                isset($data['gps_lat']) ? (float) $data['gps_lat'] : null,
                isset($data['gps_lng']) ? (float) $data['gps_lng'] : null,
            )
            : [];

        return response()->json($suivante + [
            'pieds_restants' => $piedsRestants,
            'ok'        => true,
            'pige_id'   => $pige->id,
            'photo_url' => Storage::url($path),
            'message'   => $axeEntier
                ? '✅ Photo envoyée. Continue avec les autres pieds, puis touche « Terminé ».'
                : '✅ Photo envoyée. Pose validée — en attente vérification.',
            'task_done' => !$axeEntier,
            // Axe entier (choix 4b) : nombre de photos envoyées sur la tâche.
            'axe_entier'   => $axeEntier,
            'photos_count' => $axeEntier ? \App\Models\Pige::where('pose_task_id', $task->id)->count() : null,
            // SM2c B1 — informatif côté front
            'is_off_schedule'      => $isOffSchedule,
            // SM2c B2 — true si plus aucune pose du jour à faire (jamais sur
            // un axe entier : la photo ne clôt pas sa tâche).
            'is_last_pose_of_day'  => !$axeEntier && $todayTasksRemaining === 0,
            // Refonte 2026-11-12 : compteur du jour à jour (en-tête, « Bravo »).
            'jour'                 => \App\Support\TechCarte::journee($tech),
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

        // 9 motifs centralisés dans l'enum App\Enums\DelayReason (Module 3 SLA).
        $allowedMotifs = collect(\App\Enums\DelayReason::cases())->pluck('value')->implode(',');
        $data = $request->validate([
            'type'  => 'required|string|in:' . $allowedMotifs,
            'note'  => 'nullable|string|max:500',
            // Photo optionnelle (preuve panneau cassé / accès). Compressée
            // côté client donc on plafonne large (35 MB = limite Dockerfile).
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:35840'],
        ]);

        // Anti-spam : UN SEUL signalement actif par pose (peu importe le type).
        // isResolved() étendu (Précision B) → resolved_at OR maintenance_id.
        $existing = \App\Models\PoseTaskAction::where('pose_task_id', $task->id)
            ->where('action', \App\Models\PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->whereNull('resolved_at')
            ->whereNull('maintenance_id')
            ->first();

        if ($existing) {
            $existingMotif = \App\Enums\DelayReason::tryFrom((string)($existing->payload['type'] ?? '')) ?? \App\Enums\DelayReason::AUTRE;
            return response()->json([
                'ok'          => false,
                'error'       => "Tu as déjà un signalement actif sur ce panneau : « {$existingMotif->label()} » (il y a {$existing->created_at->diffForHumans(null, true)}). Le superviseur le traite — attends qu'il soit clôturé avant d'en envoyer un autre.",
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

        $motif = \App\Enums\DelayReason::from($data['type']);
        $label = $motif->label();

        \App\Models\PoseTaskAction::log($task->id, \App\Models\PoseTaskAction::ACTION_PROBLEM_REPORTED, [
            'type'       => $motif->value,
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
