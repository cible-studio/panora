<?php
// app/Http/Controllers/Admin/PoseController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Campaign;
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
        $query = PoseTask::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign:id,name,status',
            'technicien:id,name',
        ])->withCount([
            'piges as pige_count',
            'piges as pige_verifie_count' => fn($q) => $q->where('status', 'verifie'),
        ]);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sq) => 
                $sq->whereHas('panel', fn($p) => $p->where('reference', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
                ->orWhereHas('campaign', fn($c) => $c->where('name', 'like', "%{$q}%"))
                ->orWhereHas('technicien', fn($u) => $u->where('name', 'like', "%{$q}%"))
            );
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('technicien_id')) {
            $query->where('assigned_user_id', $request->technicien_id);
        }
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->date_to);
        }

        $poseTasks = $query->latest('scheduled_at')->paginate(20)->withQueryString();
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);
        $campaigns = Campaign::whereIn('status', [CampaignStatus::ACTIF->value, CampaignStatus::POSE->value])->orderBy('name')->get(['id', 'name', 'status']);
        $stats = $this->poseService->getStats();
        $overdueTasks = $this->poseService->getOverdueTasks();
        $posesSansPige = PoseTask::where('status', PoseTaskStatus::COMPLETED->value)->whereNotNull('campaign_id')->whereDoesntHave('piges', fn($q) => $q->where('status', '!=', 'rejete'))->count();

        // ✅ AJAX response
        if ($request->ajax() || $request->input('ajax')) {
            $html = view('admin.poses.partials.table-rows', compact('poseTasks'))->render();
            $paginationHtml = $poseTasks->hasPages() ? $poseTasks->links()->render() : '';
            return response()->json([
                'html' => $html,
                'pagination' => $paginationHtml,
                'total' => number_format($poseTasks->total()),
            ]);
        }

        return view('admin.poses.index', compact('poseTasks', 'techniciens', 'campaigns', 'stats', 'overdueTasks', 'posesSansPige'));
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════════
    public function create(Request $request)
    {
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);

        $preselectedCampaign = null;
        if ($request->filled('campaign_id')) {
            $preselectedCampaign = Campaign::with([
                'panels:id,reference,name,commune_id',
                'panels.commune:id,name',
            ])->find($request->campaign_id);
        }

        return view('admin.poses.create', compact('techniciens', 'preselectedCampaign'));
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
 
        $isLate = $poseTask->status === PoseTaskStatus::PLANNED->value
            && $poseTask->scheduled_at?->isPast();
 
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

        $poseTask->load(['panel.commune', 'campaign', 'technicien']);
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);

        return view('admin.poses.edit', compact('poseTask', 'techniciens'));
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
 
        $result = $this->poseService->update($poseTask, $validated, auth()->user());
 
        if (!$result['ok']) {
            return back()->withInput()->with('error', $result['error']);
        }
 
        return redirect()->route('admin.pose-tasks.show', $poseTask)
            ->with('success', 'Tâche mise à jour avec succès.');
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════
    public function destroy(PoseTask $poseTask)
    {
        if ($poseTask->status === PoseTaskStatus::COMPLETED->value) {
            return back()->with('error', 'Impossible de supprimer une tâche déjà réalisée.');
        }
        $poseTask->delete();
        return redirect()->route('admin.pose-tasks.index')->with('success', 'Tâche de pose supprimée.');
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
                WHEN status = 'pose'  THEN 1
                WHEN status = 'termine' THEN 4
                WHEN status = 'annule'  THEN 5
                ELSE 2 END")
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