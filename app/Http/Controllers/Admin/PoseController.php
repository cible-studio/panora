<?php
// app/Http/Controllers/Admin/PoseController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\User;
use App\Models\Alert;

use App\Services\AlertService;
use App\Services\PoseService;

use App\Enums\CampaignStatus;
use App\Enums\PoseTaskStatus;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Requests\PoseTaskRequest;


class PoseController extends Controller
{
    public function __construct(
        protected PoseService  $poseService,
        protected AlertService $alertService,
    ) {}

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        // On charge withTrashed sur la campagne pour pouvoir afficher
        // "campagne supprimée" sur les poses orphelines (la campagne
        // est en soft-delete mais la pose, elle, est toujours là).
        $query = PoseTask::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign' => fn($q) => $q->withTrashed()->select('id', 'name', 'status', 'deleted_at'),
            // ⚠ whatsapp_number indispensable pour l'avertissement "Pas
            // de WhatsApp" affiché dans la colonne technicien — sans cette
            // colonne, le badge rouge s'affichait pour TOUS les techs même
            // ceux qui ont un numéro configuré.
            'technicien:id,name,whatsapp_number',
        ])->withCount([
            'piges as pige_count',
            'piges as pige_verifie_count' => fn($q) => $q->where('status', 'verifie'),
        ]);

        // Filtre "Masquer poses orphelines" (par défaut activé) : cache
        // celles dont la campagne est supprimée, annulée ou terminée.
        $hideOrphan = !$request->has('show_orphan') || !$request->boolean('show_orphan');
        if ($hideOrphan) {
            $query->whereHas('campaign', fn($q) =>
                $q->whereNotIn('status', [
                    \App\Enums\CampaignStatus::ANNULE->value,
                    \App\Enums\CampaignStatus::TERMINE->value,
                ])
                ->whereNull('deleted_at')
            );
        }

        // ⚠ Toutes les colonnes ambiguës (status, campaign_id…) sont
        // préfixées 'pose_tasks.' car le join campaigns + panels (plus bas)
        // introduit des homonymes dans le SELECT — sinon SQLSTATE 23000.
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sq) =>
                $sq->whereHas('panel', fn($p) => $p->where('reference', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
                ->orWhereHas('campaign', fn($c) => $c->where('name', 'like', "%{$q}%"))
                ->orWhereHas('technicien', fn($u) => $u->where('name', 'like', "%{$q}%"))
            );
        }
        if ($request->filled('technicien_id')) $query->where('pose_tasks.assigned_user_id', $request->technicien_id);
        if ($request->filled('campaign_id'))   $query->where('pose_tasks.campaign_id',      $request->campaign_id);
        // FIX 2026-07-01 (feedback patronne) — Le filtre date_from/date_to
        // doit s'adapter à la colonne pertinente selon le statut demandé,
        // sinon la KPI "Réalisées d'aujourd'hui" du pilotage renvoie 0
        // alors que 2 poses ont été faites (leur scheduled_at reste au
        // jour prévu, pas au jour de réalisation).
        //   status=realisee → filtrer sur done_at
        //   status=en_cours → filtrer sur started_at (fallback scheduled_at si NULL)
        //   status=planifiee/annulee/tous → filtrer sur scheduled_at (comportement historique)
        $dateColumn = match ($request->input('status')) {
            'realisee' => 'pose_tasks.done_at',
            'en_cours' => 'pose_tasks.started_at',
            default    => 'pose_tasks.scheduled_at',
        };
        if ($request->filled('date_from'))     $query->whereDate($dateColumn, '>=', $request->date_from);
        if ($request->filled('date_to'))       $query->whereDate($dateColumn, '<=', $request->date_to);
        // 2026-06-18 : filtre Équipe (team_name VARCHAR — pose_team_id ne vit
        // que sur users, pas sur pose_tasks ; on requête donc par nom).
        if ($request->filled('team_name'))     $query->where('pose_tasks.team_name', $request->team_name);

        // ─── COMPTEURS KPI sur le périmètre AVANT filtre status ───
        // (chaque carte garde sa vraie valeur quand on en clique une).
        // 'pose_tasks.status' explicite + reorder() pour vider les ORDER BY.
        $countsRaw = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->select('pose_tasks.status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
            ->groupBy('pose_tasks.status')
            ->pluck('total', 'status');

        $stats = [
            'planifiee' => (int) ($countsRaw['planifiee'] ?? 0),
            'en_cours'  => (int) ($countsRaw['en_cours']  ?? 0),
            'realisee'  => (int) ($countsRaw['realisee']  ?? 0),
            'annulee'   => (int) ($countsRaw['annulee']   ?? 0),
        ];

        // Filtre status appliqué APRÈS le calcul des compteurs.
        // Préfixé 'pose_tasks.' pour éviter l'ambiguïté avec campaigns.status.
        if ($request->filled('status')) {
            $query->where('pose_tasks.status', $request->status);
        }

        // Tri optimisé pour le groupage par campagne dans la vue :
        //  1) Campagnes les plus récemment créées en HAUT (join campaigns)
        //  2) À l'intérieur d'une campagne, panneaux par référence
        //  3) scheduled_at desc en secondaire (orphelines sans campagne)
        $poseTasks = $query
            ->leftJoin('campaigns', 'campaigns.id', '=', 'pose_tasks.campaign_id')
            ->leftJoin('panels',    'panels.id',    '=', 'pose_tasks.panel_id')
            ->select('pose_tasks.*')
            ->orderByRaw('campaigns.created_at IS NULL')      // nulls last
            ->orderByDesc('campaigns.created_at')
            ->orderBy('panels.reference')
            ->orderByDesc('pose_tasks.scheduled_at')
            ->paginate(20)->withQueryString();
        $stats['total'] = $poseTasks->total();

        $techniciens   = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);
        $campaigns     = Campaign::where('status', CampaignStatus::ACTIF->value)->orderBy('name')->get(['id', 'name', 'status']);
        // 2026-06-18 : alimente le filtre Équipe du formulaire.
        $teams         = \App\Models\PoseTeam::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $overdueTasks  = $this->poseService->getOverdueTasks();
        $posesSansPige = PoseTask::where('status', PoseTaskStatus::COMPLETED->value)->whereNotNull('campaign_id')->whereDoesntHave('piges', fn($q) => $q->where('status', '!=', 'rejete'))->count();

        if ($request->ajax() || $request->input('ajax')) {
            $html = view('admin.poses.partials.table-rows', compact('poseTasks'))->render();
            $paginationHtml = $poseTasks->hasPages() ? $poseTasks->links()->render() : '';
            return response()->json([
                'html'       => $html,
                'pagination' => $paginationHtml,
                'total'      => $poseTasks->total(),
                'stats'      => $stats, // pour rafraîchir les KPI cards en AJAX
            ]);
        }

        return view('admin.poses.index', compact('poseTasks', 'techniciens', 'campaigns', 'teams', 'stats', 'overdueTasks', 'posesSansPige'));
    }

    // ══════════════════════════════════════════════════════════════
    // SUGGEST-TECH — Suggestion intelligente d'un technicien
    //
    // Retourne le tech le plus pertinent pour une (ou plusieurs) pose(s)
    // en se basant sur :
    //   1. ZONE : qui a fait des poses dans cette commune récemment
    //      (connaissance du terrain, économie déplacements)
    //   2. CHARGE : qui a le moins de poses en cours / planifiées
    //      (équilibrage et éviter la surcharge)
    //   3. PERFORMANCE : qui a le meilleur taux de réalisation (30j)
    //
    // Le score combine les 3 axes (zone +0.5 / charge +0.3 / perf +0.2).
    // Le MP reste libre de choisir un autre tech — c'est une suggestion.
    //
    // Endpoint AJAX : GET /admin/pose-tasks/suggest-tech?task_ids[]=…
    // Réponse : { suggestion: {id, name, score, reason}, all: [...] }
    // ══════════════════════════════════════════════════════════════
    public function suggestTech(Request $request)
    {
        $data = $request->validate([
            'task_ids'   => 'required|array|min:1|max:50',
            'task_ids.*' => 'integer|exists:pose_tasks,id',
        ]);

        $tasks = PoseTask::with('panel:id,commune_id')
            ->whereIn('id', $data['task_ids'])
            ->get();

        // Communes des panneaux concernés
        $communeIds = $tasks->pluck('panel.commune_id')->filter()->unique()->values();

        $techs = User::where('role', 'technique')
            ->where('is_active', true)
            ->whereNotNull('whatsapp_number')
            ->get(['id', 'name', 'whatsapp_number']);

        if ($techs->isEmpty()) {
            return response()->json([
                'suggestion' => null,
                'all'        => [],
                'message'    => 'Aucun technicien actif avec numéro WhatsApp.',
            ]);
        }

        // Charge actuelle (poses en cours ou planifiées non finies) par tech
        $loadByTech = \DB::table('pose_tasks')
            ->whereIn('status', ['planifiee', 'en_cours'])
            ->whereNotNull('assigned_user_id')
            ->select('assigned_user_id', \DB::raw('COUNT(*) as load_count'))
            ->groupBy('assigned_user_id')
            ->pluck('load_count', 'assigned_user_id');

        // Connaissance zone (poses réalisées sur les communes ciblées, 90j)
        $zoneByTech = $communeIds->isEmpty()
            ? collect()
            : \DB::table('pose_tasks')
                ->join('panels', 'panels.id', '=', 'pose_tasks.panel_id')
                ->where('pose_tasks.status', 'realisee')
                ->where('pose_tasks.done_at', '>=', now()->subDays(90))
                ->whereIn('panels.commune_id', $communeIds)
                ->whereNotNull('pose_tasks.assigned_user_id')
                ->select('pose_tasks.assigned_user_id', \DB::raw('COUNT(*) as zone_count'))
                ->groupBy('pose_tasks.assigned_user_id')
                ->pluck('zone_count', 'assigned_user_id');

        // Performance : taux réalisation 30j
        $perfByTech = \DB::table('pose_tasks')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('assigned_user_id')
            ->select(
                'assigned_user_id',
                \DB::raw('SUM(CASE WHEN status = "realisee" THEN 1 ELSE 0 END) as done'),
                \DB::raw('COUNT(*) as total')
            )
            ->groupBy('assigned_user_id')
            ->get()
            ->mapWithKeys(fn($r) => [$r->assigned_user_id => $r->total > 0 ? $r->done / $r->total : 0]);

        // Normalisation et score
        $maxZone = max($zoneByTech->max() ?: 1, 1);
        $maxLoad = max($loadByTech->max() ?: 1, 1);

        $scored = $techs->map(function ($tech) use ($zoneByTech, $loadByTech, $perfByTech, $maxZone, $maxLoad) {
            $zone = ($zoneByTech[$tech->id] ?? 0) / $maxZone;          // 0..1
            $load = 1 - (($loadByTech[$tech->id] ?? 0) / $maxLoad);    // 1 = tech libre, 0 = surchargé
            $perf = $perfByTech[$tech->id] ?? 0.5;                     // 0..1, défaut neutre

            $score = ($zone * 0.5) + ($load * 0.3) + ($perf * 0.2);

            // Raison humaine (la plus marquante)
            $reasons = [];
            if (($zoneByTech[$tech->id] ?? 0) > 0)  $reasons[] = "{$zoneByTech[$tech->id]} pose(s) faite(s) dans cette zone";
            if (($loadByTech[$tech->id] ?? 0) <= 3) $reasons[] = "tech disponible (charge faible)";
            elseif (($loadByTech[$tech->id] ?? 0) <= 7) $reasons[] = "charge moyenne (" . ($loadByTech[$tech->id] ?? 0) . " poses en cours)";
            if ($perf > 0.8) $reasons[] = "excellent taux de réalisation";

            return [
                'id'        => $tech->id,
                'name'      => $tech->name,
                'score'     => round($score * 100),
                'load'      => (int) ($loadByTech[$tech->id] ?? 0),
                'zone_done' => (int) ($zoneByTech[$tech->id] ?? 0),
                'perf_pct'  => round($perf * 100),
                'reason'    => implode(' · ', $reasons) ?: 'Tech disponible',
            ];
        })->sortByDesc('score')->values();

        return response()->json([
            'suggestion' => $scored->first(),
            'all'        => $scored,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // CALENDAR — Vue planning hebdomadaire par technicien
    //
    // Grille 7 jours × N techniciens. Chaque case = liste des poses
    // assignées au tech à cette date. Navigation semaine précédente/
    // suivante. Le MP voit immédiatement la charge des techs.
    // ══════════════════════════════════════════════════════════════
    public function calendar(Request $request)
    {
        // Semaine sélectionnée (lundi par défaut sur la semaine courante)
        $weekStart = $request->filled('week')
            ? \Carbon\Carbon::parse($request->week)->startOfWeek()
            : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Tous les techniciens actifs + un slot "Non assigné"
        $techniciens = User::where('role', 'technique')
            ->orderBy('name')
            ->get(['id', 'name', 'whatsapp_number']);

        // Poses de la semaine (toutes statuses, on filtre côté grille)
        $tasks = PoseTask::with(['panel:id,reference,name,commune_id', 'panel.commune:id,name', 'campaign:id,name'])
            ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
            ->orderBy('scheduled_at')
            ->get();

        // Index : [tech_id][YYYY-MM-DD] = collection de PoseTasks
        $grid = [];
        foreach ($tasks as $t) {
            $techKey = $t->assigned_user_id ?? 'none';
            $day     = $t->scheduled_at?->format('Y-m-d');
            if (!$day) continue;
            $grid[$techKey][$day][] = $t;
        }

        $days = collect();
        for ($i = 0; $i < 7; $i++) {
            $days->push($weekStart->copy()->addDays($i));
        }

        return view('admin.poses.calendar', compact('weekStart', 'weekEnd', 'days', 'techniciens', 'grid'));
    }


    // ══════════════════════════════════════════════════════════════
    // MAP — Vue carte GPS des poses (markers colorés par statut)
    // ══════════════════════════════════════════════════════════════
    public function map(Request $request)
    {
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);
        $campaigns   = Campaign::whereIn('status', ['actif', 'planifie'])
            ->orderBy('name')->get(['id', 'name', 'status']);

        return view('admin.poses.map', compact('techniciens', 'campaigns'));
    }

    /**
     * JSON : pose-tasks avec GPS pour l'affichage carte.
     * Filtres optionnels : status, technicien_id, campaign_id.
     */
    public function mapData(Request $request)
    {
        $query = PoseTask::query()
            ->join('panels', 'panels.id', '=', 'pose_tasks.panel_id')
            ->whereNotNull('panels.latitude')
            ->whereNotNull('panels.longitude')
            ->with([
                'panel:id,reference,name,latitude,longitude,commune_id,gps_source,gps_dispersion_flag',
                'panel.commune:id,name',
                'campaign:id,name,status',
                'technicien:id,name,whatsapp_number',
            ]);

        // Cache 60s pour limiter la charge (markers peuvent être nombreux)
        if ($request->filled('status'))         $query->where('pose_tasks.status', $request->status);
        if ($request->filled('technicien_id'))  $query->where('pose_tasks.assigned_user_id', $request->technicien_id);
        if ($request->filled('campaign_id'))    $query->where('pose_tasks.campaign_id', $request->campaign_id);

        $tasks = $query->select('pose_tasks.*')->limit(500)->get();

        $markers = $tasks->map(function ($t) {
            $statusColor = match ($t->status) {
                'planifiee' => '#e8a020',
                'en_cours'  => '#3b82f6',
                'realisee'  => '#22c55e',
                'annulee'   => '#ef4444',
                default     => '#6b7280',
            };
            $isLate = $t->status === 'planifiee' && $t->scheduled_at?->lt(PoseTask::lateThreshold());
            return [
                'id'         => $t->id,
                'lat'        => (float) $t->panel->latitude,
                'lng'        => (float) $t->panel->longitude,
                'reference'  => $t->panel->reference,
                'name'       => $t->panel->name,
                'commune'    => $t->panel->commune?->name,
                'campaign'   => $t->campaign?->name,
                'tech'       => $t->technicien?->name,
                'tech_id'    => $t->assigned_user_id,
                'status'     => $t->status,
                'color'      => $isLate ? '#dc2626' : $statusColor, // rouge foncé si retard
                'is_late'    => $isLate,
                'gps_source' => $t->panel->gps_source,            // manual|pige_provisional|pige_confirmed|null
                'dispersion' => (bool) $t->panel->gps_dispersion_flag, // positions piges divergentes
                'scheduled'  => $t->scheduled_at?->format('d/m/Y H:i'),
                'done_at'    => $t->done_at?->format('d/m/Y H:i'),
                'show_url'   => route('admin.pose-tasks.show', $t),
            ];
        });

        return response()->json([
            'markers' => $markers,
            'total'   => $markers->count(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════════
    public function create(Request $request)
    {
        // 2026-06-18 (feedback patronne) : on charge également la liste des
        // PoseTeam actives pour proposer un VRAI select équipe (au lieu de
        // l'input texte libre historique). On expose aussi une map
        // user_id → team_name pour l'auto-remplissage côté JS lorsque
        // l'admin choisit un technicien déjà rattaché à une équipe.
        // 2026-06-19 — Multi-équipe : $teamByUser passe d'un id → 1 nom
        // à un id → liste de noms ['Équipe A', 'Équipe B']. La vue + le JS
        // d'auto-fill sont adaptés en conséquence.
        $techniciens = User::where('role', 'technique')
            ->with('poseTeams:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);
        $teams = \App\Models\PoseTeam::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $teamByUser = $techniciens
            ->mapWithKeys(fn ($u) => [$u->id => $u->poseTeams->pluck('name')->all()])
            ->filter(fn ($names) => !empty($names))
            ->toArray();

        $preselectedCampaign = null;
        if ($request->filled('campaign_id')) {
            $preselectedCampaign = Campaign::with([
                'panels:id,reference,name,commune_id',
                'panels.commune:id,name',
            ])->find($request->campaign_id);
        }

        return view('admin.poses.create', compact(
            'techniciens', 'teams', 'teamByUser', 'preselectedCampaign'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // STORE — utilise PoseTaskRequest (messages FR)
    // ══════════════════════════════════════════════════════════════
    public function store(Request $request)
    {   
        $request->merge([
            'panel_ids' => array_values(array_filter(
                (array) $request->input('panel_ids', []),
                fn($v) => $v !== null && $v !== '' && $v !== '0'
            ))
        ]);

        $validated = $request->validate([
            'campaign_id'      => 'nullable|exists:campaigns,id',
            'panel_ids'        => 'required|array|min:1|max:100',
            'panel_ids.*'      => 'integer|exists:panels,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'team_name'        => 'nullable|string|max:100',
            'scheduled_at'     => 'required|date',
            'status'           => 'required|in:planifiee,en_cours',
            'notes'            => 'nullable|string|max:1000',
        ], [
            'panel_ids.required'      => 'Veuillez sélectionner au moins un panneau.',
            'panel_ids.array'         => 'La sélection des panneaux est invalide.',
            'panel_ids.min'           => 'Veuillez sélectionner au moins un panneau.',
            'panel_ids.max'           => 'Vous ne pouvez pas sélectionner plus de 100 panneaux à la fois.',
            'panel_ids.*.exists'      => 'Un ou plusieurs panneaux sélectionnés sont introuvables.',
            'campaign_id.exists'      => 'La campagne sélectionnée est introuvable.',
            'assigned_user_id.exists' => 'Le technicien sélectionné est introuvable.',
            'team_name.max'           => "Le nom d'équipe ne doit pas dépasser 100 caractères.",
            'scheduled_at.required'   => 'La date et heure de pose sont obligatoires.',
            'scheduled_at.date'       => 'La date et heure de pose sont invalides.',
            'status.required'         => 'Le statut est obligatoire.',
            'status.in'               => 'Statut invalide. Valeurs acceptées : Planifiée, En cours.',
            'notes.max'               => 'Les notes ne doivent pas dépasser 1000 caractères.',
        ]);
 
        $result = $this->poseService->createBatch($validated, auth()->user());
 
        if (!$result['ok']) {
            return back()->withInput()->with('error', $result['error']);
        }
 
        $msg = $result['count'] . ' tâche(s) de pose créée(s) avec succès.';
        if (!empty($result['warnings'])) {
            $msg .= ' ⚠️ ' . implode(' ', $result['warnings']);
        }

        // Alerte création pose
        AlertService::create(
            'pose',
            'info',
            '🔧 Nouvelles tâches de pose — ' . $result['count'] . ' tâche(s)',
            auth()->user()->name . ' a créé ' . $result['count'] . ' tâche(s) de pose',
            null
        );

        return redirect()->route('admin.pose-tasks.index')->with('success', $msg);
 
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════
    public function show(PoseTask $poseTask)
    {
        $poseTask->load([
            'panel.commune', 'panel.format',
            'campaign.client',
            'technicien',
        ]);
 
        $pigeStats = null;
        if ($poseTask->campaign_id) {
            $pigeStats = [
                'total'      => Pige::where('panel_id', $poseTask->panel_id)->where('campaign_id', $poseTask->campaign_id)->count(),
                'verifie'    => Pige::where('panel_id', $poseTask->panel_id)->where('campaign_id', $poseTask->campaign_id)->where('status', 'verifie')->count(),
                'en_attente' => Pige::where('panel_id', $poseTask->panel_id)->where('campaign_id', $poseTask->campaign_id)->where('status', 'en_attente')->count(),
                'rejete'     => Pige::where('panel_id', $poseTask->panel_id)->where('campaign_id', $poseTask->campaign_id)->where('status', 'rejete')->count(),
            ];
        }
 
        $isLate = $poseTask->isLate();
 
        // Alertes liées à cette tâche — via AlertService (maintenant cohérent)
        $taskAlerts = $this->alertService->getForModel(PoseTask::class, $poseTask->id);
 
        return view('admin.poses.show', compact('poseTask', 'pigeStats', 'isLate', 'taskAlerts'));
    }

    // ══════════════════════════════════════════════════════════════
    // EDIT
    // ══════════════════════════════════════════════════════════════
    public function edit(PoseTask $poseTask)
    {
        if ($poseTask->status === PoseTaskStatus::COMPLETED->value ||
            $poseTask->status === PoseTaskStatus::CANCELLED->value) {
            return redirect()->route('admin.pose-tasks.show', $poseTask)
                ->with('error', 'Cette tâche ne peut plus être modifiée.');
        }

        // 2026-06-19 — Multi-équipe (cf. create()) : id → liste de noms.
        $poseTask->load(['panel.commune', 'campaign', 'technicien']);
        $techniciens = User::where('role', 'technique')
            ->with('poseTeams:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);
        $teams = \App\Models\PoseTeam::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $teamByUser = $techniciens
            ->mapWithKeys(fn ($u) => [$u->id => $u->poseTeams->pluck('name')->all()])
            ->filter(fn ($names) => !empty($names))
            ->toArray();

        return view('admin.poses.edit', compact('poseTask', 'techniciens', 'teams', 'teamByUser'));
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE — utilise PoseTaskRequest (messages FR)
    // ══════════════════════════════════════════════════════════════
    public function update(Request $request, PoseTask $poseTask)
    {
        if ($poseTask->status === PoseTaskStatus::COMPLETED->value ||
            $poseTask->status === PoseTaskStatus::CANCELLED->value) {
            return back()->with('error', 'Cette tâche ne peut plus être modifiée.');
        }
 
        $validated = $request->validate([
            'campaign_id'      => 'nullable|exists:campaigns,id',
            'panel_id'         => 'required|exists:panels,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'team_name'        => 'nullable|string|max:100',
            'scheduled_at'     => 'required|date',
            'status'           => 'required|in:planifiee,en_cours,annulee',
            'notes'            => 'nullable|string|max:1000',
        ], [
            'panel_id.required'       => 'Le panneau est obligatoire.',
            'panel_id.exists'         => 'Le panneau sélectionné est introuvable.',
            'campaign_id.exists'      => 'La campagne sélectionnée est introuvable.',
            'assigned_user_id.exists' => 'Le technicien sélectionné est introuvable.',
            'team_name.max'           => "Le nom d'équipe ne doit pas dépasser 100 caractères.",
            'scheduled_at.required'   => 'La date et heure de pose sont obligatoires.',
            'scheduled_at.date'       => 'La date et heure de pose sont invalides.',
            'status.required'         => 'Le statut est obligatoire.',
            'status.in'               => 'Statut invalide. Valeurs acceptées : Planifiée, En cours, Annulée.',
            'notes.max'               => 'Les notes ne doivent pas dépasser 1000 caractères.',
        ]);
 
        $oldTechId = $poseTask->assigned_user_id;
        $result    = $this->poseService->update($poseTask, $validated, auth()->user());

        if (!$result['ok']) {
            return back()->withInput()->with('error', $result['error']);
        }

        // Alerte modification pose (uniquement si changements importants)
        AlertService::create(
            'pose',
            'info',
            '✏️ Tâche de pose modifiée — ' . ($poseTask->panel?->reference ?? ''),
            auth()->user()->name . ' a modifié la tâche de pose du panneau ' . ($poseTask->panel?->reference ?? ''),
            $poseTask
        );

        // Si le technicien a changé → renvoyer le lien WhatsApp au nouveau
        $newTechId = (int) ($validated['assigned_user_id'] ?? 0);
        if ($newTechId && $newTechId !== (int) $oldTechId) {
            $this->poseService->notifyTechnicianOnWhatsApp($poseTask->fresh()->load('panel.commune', 'technicien'));
        }

        return redirect()->route('admin.pose-tasks.show', $poseTask)
            ->with('success', 'Tâche mise à jour avec succès.');
    }

    // ══════════════════════════════════════════════════════════════
    // PROGRESS — JSON polling pour la vue admin (toutes les 30 s)
    // ══════════════════════════════════════════════════════════════
    public function progress(Request $request): JsonResponse
    {
        $ids = array_map('intval', array_filter((array) $request->input('ids', [])));
        $query = PoseTask::query()->select([
            'id', 'panel_id', 'status', 'progress_percent',
            'started_at', 'done_at', 'real_minutes', 'whatsapp_sent_at',
        ]);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            // Par défaut on retourne uniquement les tâches non-terminées des 90 derniers jours
            $query->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
                  ->where('updated_at', '>=', now()->subDays(90));
        }

        $tasks = $query->limit(500)->get()->map(fn($t) => [
            'id'              => $t->id,
            'status'          => $t->status,
            'status_label'    => PoseTaskStatus::tryFrom($t->status)?->label() ?? '—',
            'percent'         => (int) ($t->progress_percent ?? 0),
            'color'           => $t->progressColor(),
            'is_running'      => $t->isInProgress(),
            'is_done'         => $t->status === PoseTaskStatus::COMPLETED->value,
            'started_at'      => $t->started_at?->toIso8601String(),
            'done_at'         => $t->done_at?->toIso8601String(),
            'real_minutes'    => $t->real_minutes,
            'whatsapp_sent'   => $t->whatsapp_sent_at !== null,
        ]);

        return response()->json([
            'tasks'      => $tasks,
            'server_time'=> now()->toIso8601String(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════
    public function destroy(PoseTask $poseTask)
    {
        if ($poseTask->status === PoseTaskStatus::COMPLETED->value) {
            return back()->with('error', 'Impossible de supprimer une tâche déjà réalisée.');
        }
        
        $panelRef = $poseTask->panel?->reference ?? '';
        $poseTask->delete();
        
        // Alerte suppression pose
        AlertService::create(
            'pose',
            'danger',
            '🗑 Tâche de pose supprimée — ' . $panelRef,
            auth()->user()->name . ' a supprimé la tâche de pose du panneau ' . $panelRef,
            null
        );
        
        return redirect()->route('admin.pose-tasks.index')->with('success', 'Tâche de pose supprimée.');
    }

    // ══════════════════════════════════════════════════════════════
    // NOTIFY WHATSAPP — déclenchement manuel par l'admin
    // ══════════════════════════════════════════════════════════════
    public function notifyWhatsApp(PoseTask $poseTask)
    {
        if (in_array($poseTask->status, ['realisee', 'annulee'])) {
            return back()->with('error', 'Notification non pertinente sur une tâche déjà clôturée.');
        }

        $poseTask->load('panel.commune', 'technicien', 'campaign');
        $tech = $poseTask->technicien;

        if (!$tech || empty($tech->whatsapp_number)) {
            return back()->with('error', 'Aucun numéro WhatsApp pour le technicien — configure-le d\'abord.');
        }

        $sent = $this->poseService->notifyTechnicianOnWhatsApp($poseTask);

        if ($sent) {
            return back()->with('success', "✅ Notification WhatsApp envoyée à {$tech->name}.");
        }

        // Échec : message actionnable + lien fallback. On propose le lien
        // d'espace tech (multi-poses) — c'est le bon lien à partager
        // manuellement au technicien (multi-campagnes, stable).
        // Ancien message ("vérifiez les logs serveur (whatsapp.failed)")
        // était technique et inquiétant côté admin. En sandbox Twilio ou
        // avec n° non vérifié, l'échec est attendu — on n'alarme plus,
        // on action : copier le lien et le coller dans WhatsApp.
        $fallbackUrl = $tech->techPublicUrl();
        return back()->with(
            'warning',
            "📱 WhatsApp automatique indisponible (sandbox Twilio ou n° non vérifié). " .
            "Copie ce lien et envoie-le directement à {$tech->name} : {$fallbackUrl}"
        );
    }

    // ══════════════════════════════════════════════════════════════
    // BULK UPDATE — actions groupées (assigner tech / équipe / statut / date)
    //
    // Endpoint AJAX POST → renvoie JSON {ok, updated, skipped, error?}.
    // Le front affiche un toast et recharge la table.
    // ══════════════════════════════════════════════════════════════
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'task_ids'   => 'required|array|min:1|max:200',
            'task_ids.*' => 'integer|exists:pose_tasks,id',
            'action'     => 'required|in:assign_tech,rename_team,change_status,reschedule',
            'value'      => 'nullable',
        ]);

        $result = $this->poseService->bulkUpdate(
            $data['task_ids'],
            $data['action'],
            $data['value'] ?? null,
            auth()->user()
        );

        // ── Alerte d'audit pour l'action bulk ───────────────────────
        // Équivalent de l'alerte créée pour les actions individuelles
        // (assignTech, complete, etc.) — sans elle, le bulk n'apparait
        // pas dans le journal d'audit côté admin.
        if (!empty($result['ok']) && ($result['updated'] ?? 0) > 0) {
            $verbMap = [
                'assign_tech'   => 'a réassigné le technicien sur',
                'rename_team'   => 'a renommé l\'équipe sur',
                'change_status' => 'a changé le statut de',
                'reschedule'    => 'a replanifié',
            ];
            $verb     = $verbMap[$data['action']] ?? 'a mis à jour';
            $count    = $result['updated'];
            $notified = $result['notified'] ?? 0;

            \App\Services\AlertService::create(
                'pose',
                'info',
                '🔧 Action groupée poses — ' . $count . ' tâche(s)',
                auth()->user()?->name . ' ' . $verb . ' ' . $count . ' tâche(s) de pose'
                    . ($notified > 0 ? ' (' . $notified . ' tech notifiés WhatsApp)' : '')
                    . (($result['skipped'] ?? 0) > 0 ? ' · ' . $result['skipped'] . ' ignorée(s)' : ''),
                null
            );
        }

        // AJAX (le front utilise toujours fetch sur cet endpoint).
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        // Fallback non-JS : flash session.
        if (!$result['ok']) {
            return back()->with('error', $result['error'] ?? 'Action impossible.');
        }
        return back()->with('success', $result['updated'] . ' tâche(s) mise(s) à jour.'
            . ($result['skipped'] ? ' ' . $result['skipped'] . ' ignorée(s).' : ''));
    }

    // ══════════════════════════════════════════════════════════════
    // MARK COMPLETE — lock optimiste + alerte instantanée
    // ══════════════════════════════════════════════════════════════
    public function markComplete(Request $request, PoseTask $poseTask)
    {
        $result = $this->poseService->complete($poseTask, auth()->user());
 
        if (!$result['ok']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->with('error', $result['error']);
        }
 
        // ← Fix : $result['warning'] est null si pige présente, string si absente
        $hasPige = empty($result['warning']);
 
        // Alerte instantanée si pas de pige
        $this->alertService->notifyPoseComplete($poseTask->fresh(), $hasPige);
 
        $msg = 'Pose marquée comme réalisée. ✅';
        if (!empty($result['warning'])) {
            $msg .= ' ' . $result['warning'];
        }
 
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'warning' => $result['warning'] ?? null,
            ]);
        }
 
        return back()->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // POSES OUBLIÉES (2026-06-26)
    // Liste les poses non finalisées dont la date prévue est dépassée —
    // typiquement les poses faites sur le terrain mais que le MP a oublié
    // de marquer "réalisées" sur la plateforme. Permet le rattrapage en
    // lot avec date personnalisée (date réelle de réalisation).
    // ══════════════════════════════════════════════════════════════
    public function oubliees(Request $request)
    {
        $notDone = [
            PoseTaskStatus::PLANNED->value,
            PoseTaskStatus::EN_ROUTE->value,
            PoseTaskStatus::IN_PROGRESS->value,
        ];

        $q = PoseTask::query()
            ->with([
                'panel:id,reference,commune_id',
                'panel.commune:id,name',
                'campaign:id,name,client_id',
                'campaign.client:id,name',
                'technicien:id,name',
            ])
            ->whereIn('status', $notDone)
            ->where('scheduled_at', '<', PoseTask::lateThreshold());

        if ($request->filled('user_id')) {
            $q->where('assigned_user_id', $request->user_id);
        }
        if ($request->filled('client_id')) {
            $q->whereHas('campaign', fn($qq) => $qq->where('client_id', $request->client_id));
        }
        if ($request->filled('month')) {
            try {
                $m = \Carbon\Carbon::parse($request->month . '-01');
                $q->whereYear('scheduled_at', $m->year)->whereMonth('scheduled_at', $m->month);
            } catch (\Throwable $e) { /* filtre invalide → ignoré */ }
        }

        $tasks = $q->orderBy('scheduled_at', 'asc')->paginate(50)->withQueryString();

        // Compteur global (avant filtres) pour le KPI du bandeau d'alerte.
        $totalOubliees = PoseTask::whereIn('status', $notDone)
            ->where('scheduled_at', '<', PoseTask::lateThreshold())
            ->count();

        // Techniciens présents dans la sélection — pour le filtre déroulant.
        $userIds = PoseTask::whereIn('status', $notDone)
            ->where('scheduled_at', '<', PoseTask::lateThreshold())
            ->whereNotNull('assigned_user_id')
            ->distinct()->pluck('assigned_user_id');
        $users = User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);

        $clients = Client::orderBy('name')->get(['id', 'name']);

        return view('admin.poses.oubliees', compact('tasks', 'users', 'clients', 'totalOubliees'));
    }

    /**
     * Rattrapage en lot : marque N poses "Réalisée" avec une date personnalisée
     * (date à laquelle la pose a été effectivement faite sur le terrain — donc
     * potentiellement dans le passé). On contourne PoseService::complete() qui
     * forcerait done_at = now() et enverrait des alertes — non souhaitable pour
     * une régularisation rétroactive.
     */
    public function bulkCompleteOubliees(Request $request)
    {
        $data = $request->validate([
            'task_ids'   => 'required|array|min:1',
            'task_ids.*' => 'integer|exists:pose_tasks,id',
            'done_at'    => 'required|date|before_or_equal:today',
        ]);

        $doneAt = \Carbon\Carbon::parse($data['done_at'])->endOfDay();
        $alreadyDone = [
            PoseTaskStatus::COMPLETED->value,
            PoseTaskStatus::CANCELLED->value,
        ];

        $count = PoseTask::whereIn('id', $data['task_ids'])
            ->whereNotIn('status', $alreadyDone)
            ->update([
                'status'  => PoseTaskStatus::COMPLETED->value,
                'done_at' => $doneAt,
            ]);

        return redirect()
            ->route('admin.pose-tasks.oubliees', $request->only(['user_id', 'client_id', 'month']))
            ->with('success', "✅ $count pose(s) marquée(s) réalisée(s) au " . $doneAt->format('d/m/Y') . '.');
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX : Recherche campagnes (autocomplete)
    // GET /admin/pose-tasks/search-campaigns?q=MTN&status=actif,pose
    // ══════════════════════════════════════════════════════════════
    public function searchCampaigns(Request $request): JsonResponse
    {
        $q         = $request->input('q', '');
        $statusStr = $request->input('status', 'actif,pose');
 
        // Si status est une chaîne vide → renvoyer TOUTES les campagnes (pour les piges)
        if ($statusStr === '') {
            $statusArr = [];
        } else {
            $statusArr = array_filter(array_map('trim', explode(',', $statusStr)));
        }
 
        $campaigns = Campaign::query()
            ->when($q, fn($qr) => $qr->where('name', 'like', "%{$q}%"))
            ->when(!empty($statusArr), fn($qr) => $qr->whereIn('status', $statusArr))
            ->orderByRaw("CASE
                WHEN status = 'actif' THEN 0
                WHEN status = 'planifie' THEN 1
                WHEN status = 'pause' THEN 2
                WHEN status = 'termine' THEN 4
                WHEN status = 'annule'  THEN 5
                ELSE 3 END")
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'status', 'start_date', 'end_date', 'total_panels']);
 
        return response()->json($campaigns->map(fn($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'status'       => $c->status->value,
            'status_label' => $c->status->label(),
            'icon'         => $c->status->uiConfig()['icon'],
            'color'        => $c->status->uiConfig()['color'],
            'blocked'      => $c->status->isTerminal(),
            'dates'        => $c->start_date?->format('d/m/Y') . ' → ' . $c->end_date?->format('d/m/Y'),
            'total_panels' => $c->total_panels ?? 0,
        ]));
    }
    // ══════════════════════════════════════════════════════════════
    // AJAX : Panneaux d'une campagne avec statuts pose + pige
    // GET /admin/pose-tasks/campaign-panels?campaign_id=X
    // ══════════════════════════════════════════════════════════════
    public function campaignPanels(Request $request): JsonResponse
    {
        $request->validate(['campaign_id' => 'required|integer|exists:campaigns,id']);

        $campaign = Campaign::with([
            'panels:id,reference,name,commune_id',
            'panels.commune:id,name',
        ])->findOrFail($request->campaign_id);

        $panelIds = $campaign->panels->pluck('id');

        $existingTasks = PoseTask::where('campaign_id', $campaign->id)
            ->whereIn('panel_id', $panelIds)
            ->whereNotIn('status', [PoseTaskStatus::CANCELLED->value])
            ->latest()
            ->get(['id', 'panel_id', 'status', 'scheduled_at'])
            ->keyBy('panel_id');

        $existingPiges = Pige::where('campaign_id', $campaign->id)
            ->whereIn('panel_id', $panelIds)
            ->where('status', '!=', 'rejete')
            ->latest()
            ->get(['id', 'panel_id', 'status'])
            ->keyBy('panel_id');

        $panels = $campaign->panels->map(fn($panel) => [
            'id'          => $panel->id,
            'reference'   => $panel->reference,
            'name'        => $panel->name,
            'commune'     => $panel->commune?->name ?? '—',
            'has_task'    => $existingTasks->has($panel->id),
            'task_status' => $existingTasks->get($panel->id)?->status,
            'task_date'   => $existingTasks->get($panel->id)?->scheduled_at?->format('d/m/Y'),
            'task_id'     => $existingTasks->get($panel->id)?->id,
            'has_pige'    => $existingPiges->has($panel->id),
            'pige_status' => $existingPiges->get($panel->id)?->status,
        ]);

        $stats = [
            'total'     => $panels->count(),
            'avec_pose' => $panels->where('has_task', true)->count(),
            'sans_pose' => $panels->where('has_task', false)->count(),
            'avec_pige' => $panels->where('has_pige', true)->count(),
        ];

        return response()->json([
            'campaign' => [
                'id'      => $campaign->id,
                'name'    => $campaign->name,
                'status'  => $campaign->status->value,
                'label'   => $campaign->status->label(),
                'blocked' => $campaign->status->isTerminal(),
            ],
            'panels' => $panels->values(),
            'stats'  => $stats,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX : Recherche panneaux libres (sans campagne)
    // GET /admin/pose-tasks/search-panels?q=CDY
    // ══════════════════════════════════════════════════════════════
    public function searchPanels(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $panels = Panel::with('commune:id,name')
            ->when($q, fn($qr) => $qr->where(fn($s) =>
                $s->where('reference', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
            ))
            ->whereNull('deleted_at')
            ->orderBy('reference')
            ->limit(30)
            ->get(['id', 'reference', 'name', 'commune_id', 'status']);

        return response()->json($panels->map(fn($p) => [
            'id'        => $p->id,
            'reference' => $p->reference,
            'name'      => $p->name,
            'commune'   => $p->commune?->name ?? '—',
            'status'    => $p->status,
        ]));
    }
}