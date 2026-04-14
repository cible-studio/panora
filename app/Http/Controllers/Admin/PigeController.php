<?php
// app/Http/Controllers/Admin/PigeController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Services\PigeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PigeController extends Controller
{
    public function __construct(protected PigeService $pigeService) {}

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Pige::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign:id,name,client_id,status',
            'campaign.client:id,name,deleted_at',
            'takenBy:id,name',
        ])->select([
            'id', 'panel_id', 'campaign_id', 'user_id',
            'photo_path', 'taken_at', 'gps_lat', 'gps_lng',
            'status', 'verified_by', 'verified_at',
            'notes', 'rejection_reason', 'created_at',
        ]);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->campaign_id);
        }
        if ($request->filled('panel_id')) {
            $query->where('panel_id', (int) $request->panel_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->whereHas('campaign', fn($q) => $q->where('client_id', (int) $request->client_id));
        }
        if ($request->filled('date_from')) {
            $query->where('taken_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('taken_at', '<=', $request->date_to . ' 23:59:59');
        }

        $piges     = $query->latest('taken_at')->paginate(24)->withQueryString();
        $campaigns = Campaign::orderBy('name')->get(['id', 'name', 'status']);
        $panels    = Panel::orderBy('reference')->get(['id', 'reference', 'name']);
        $clients   = Client::orderBy('name')->get(['id', 'name']);
        $stats     = $this->pigeService->globalStats();

        return view('admin.piges.index', compact(
            'piges', 'campaigns', 'panels', 'clients', 'stats'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // PANELS BY CAMPAIGN — AJAX pour le formulaire upload
    //
    // Retourne les panneaux d'une campagne avec leur statut pige :
    // - has_pige : true si une pige non-rejetée existe
    // - pige_status : en_attente / verifie / null
    // - pose_done : si la pose est marquée réalisée pour ce panneau
    // ══════════════════════════════════════════════════════════════
    public function panelsByCampaign(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
        ]);

        $campaign = Campaign::with([
            'panels:id,reference,name,commune_id',
            'panels.commune:id,name',
        ])->findOrFail($request->campaign_id);

        $panelIds = $campaign->panels->pluck('id');

        // Piges existantes non-rejetées pour cette campagne
        $existingPiges = Pige::where('campaign_id', $campaign->id)
            ->whereIn('panel_id', $panelIds)
            ->where('status', '!=', Pige::STATUS_REJECTED)
            ->latest('taken_at')
            ->get(['id', 'panel_id', 'status', 'taken_at'])
            ->keyBy('panel_id');

        // Poses réalisées pour cette campagne
        $posesRealisees = PoseTask::where('campaign_id', $campaign->id)
            ->whereIn('panel_id', $panelIds)
            ->where('status', 'realisee')
            ->pluck('panel_id')
            ->flip();

        $panels = $campaign->panels->map(function ($panel) use ($existingPiges, $posesRealisees) {
            $pige = $existingPiges->get($panel->id);
            return [
                'id'          => $panel->id,
                'reference'   => $panel->reference,
                'name'        => $panel->name,
                'commune'     => $panel->commune?->name ?? '—',
                'has_pige'    => $pige !== null,
                'pige_id'     => $pige?->id,
                'pige_status' => $pige?->status,
                'pige_date'   => $pige?->taken_at?->format('d/m/Y'),
                'pose_done'   => isset($posesRealisees[$panel->id]),
            ];
        });

        // Panels sans pige → sélectionnés par défaut
        $sansPige    = $panels->where('has_pige', false)->count();
        $avecPige    = $panels->where('has_pige', true)->count();
        $posesCount  = $panels->where('pose_done', true)->count();

        return response()->json([
            'campaign' => [
                'id'       => $campaign->id,
                'name'     => $campaign->name,
                'status'   => $campaign->status->value,
                'label'    => $campaign->status->label(),
                'blocked'  => $campaign->status->isTerminal(),
                'ui'       => $campaign->status->uiConfig(),
            ],
            'panels' => $panels->values(),
            'stats'  => [
                'total'      => $panels->count(),
                'avec_pige'  => $avecPige,
                'sans_pige'  => $sansPige,
                'poses_done' => $posesCount,
                'complete'   => $sansPige === 0,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // UPLOAD
    // ══════════════════════════════════════════════════════════════
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'panel_id'    => 'required|integer|exists:panels,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'photo'       => 'required|file|max:30720',
            'taken_at'    => 'required|date|before_or_equal:today',
            'gps_lat'     => 'nullable|numeric|between:-90,90',
            'gps_lng'     => 'nullable|numeric|between:-180,180',
            'notes'       => 'nullable|string|max:1000',
        ], [
            'panel_id.required'        => 'Veuillez sélectionner un panneau.',
            'panel_id.exists'          => 'Panneau introuvable.',
            'photo.required'           => 'La photo est obligatoire.',
            'photo.max'                => 'La photo ne doit pas dépasser 30 Mo.',
            'taken_at.required'        => 'La date de prise de vue est obligatoire.',
            'taken_at.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'gps_lat.between'          => 'Latitude invalide (-90 à 90). Ex: 5.3401',
            'gps_lng.between'          => 'Longitude invalide (-180 à 180). Ex: -4.0263',
        ]);

        $result = $this->pigeService->upload(
            $validated,
            $request->file('photo'),
            auth()->user()
        );

        if (!$result['ok']) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->withInput()->with('error', $result['error']);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'pige_id' => $result['pige']->id,
            ], 201);
        }

        return redirect()->route('admin.piges.index')->with('success', $result['message']);
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW — HTML ou JSON (lightbox)
    // ══════════════════════════════════════════════════════════════
    public function show(Pige $pige)
    {
        $pige->load([
            'panel:id,reference,name,commune_id,status',
            'panel.commune:id,name',
            'campaign:id,name,client_id,status,start_date,end_date',
            'campaign.client:id,name,deleted_at',
            'takenBy:id,name',
            'verifiedBy:id,name',
        ]);

        $req = request();
        if ($req->wantsJson() || $req->ajax()) {
            $ctx = $this->pigeService->resolveContext($pige->campaign_id, $pige->panel_id);

            return response()->json([
                'id'                    => $pige->id,
                'panel_ref'             => $pige->panel?->reference,
                'panel_name'            => $pige->panel?->name,
                'panel_status'          => $pige->panel?->status ?? null,
                'commune'               => $pige->panel?->commune?->name,
                'campaign'              => $pige->campaign?->name,
                'campaign_status'       => $pige->campaign?->status?->value,
                'campaign_status_label' => $pige->campaign?->status?->label(),
                'campaign_ui'           => $pige->campaign?->status?->uiConfig(),
                'client'                => $pige->campaign?->client?->name,
                'client_deleted'        => $pige->campaign?->client?->trashed() ?? false,
                'taken_at'              => $pige->taken_at?->format('d/m/Y'),
                'taken_by'              => $pige->takenBy?->name,
                'verified_by'           => $pige->verifiedBy?->name,
                'verified_at'           => $pige->verified_at?->format('d/m/Y à H:i'),
                'gps_lat'               => $pige->gps_lat,
                'gps_lng'               => $pige->gps_lng,
                'has_gps'               => $pige->hasGps(),
                'maps_url'              => $pige->maps_url,
                'notes'                 => $pige->notes,
                'status'                => $pige->status,
                'status_label'          => $pige->status_label,
                'rejection_reason'      => $pige->rejection_reason,
                'photo_url'             => asset('storage/' . $pige->photo_path),
                'can_verify'            => $pige->isPending(),
                'can_reject'            => $pige->isPending(),
                'can_delete'            => !$pige->isVerified(),
                'context'               => $ctx,
                'routes' => [
                    'verify'  => route('admin.piges.verify', $pige),
                    'reject'  => route('admin.piges.reject', $pige),
                    'destroy' => route('admin.piges.destroy', $pige),
                    'show'    => route('admin.piges.show', $pige),
                ],
            ]);
        }

        $siblings = null;
        if ($pige->campaign_id) {
            $siblings = Pige::where('campaign_id', $pige->campaign_id)
                ->orderBy('taken_at')
                ->get(['id', 'panel_id', 'taken_at', 'status', 'photo_path'])
                ->map(fn($p) => [
                    'id'        => $p->id,
                    'panel_id'  => $p->panel_id,
                    'status'    => $p->status,
                    'photo_url' => asset('storage/' . $p->photo_path),
                    'taken_at'  => $p->taken_at?->format('d/m/Y'),
                    'active'    => $p->id === $pige->id,
                ]);
        }

        $ctx = $this->pigeService->resolveContext($pige->campaign_id, $pige->panel_id);
        return view('admin.piges.show', compact('pige', 'siblings', 'ctx'));
    }

    // ══════════════════════════════════════════════════════════════
    // VERIFY
    // ══════════════════════════════════════════════════════════════
    public function verify(Request $request, Pige $pige): JsonResponse
    {
        $result = $this->pigeService->verify($pige, auth()->user());
        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok'] ? $result['message'] : $result['error'],
            'already' => $result['already'] ?? false,
            'status'  => Pige::STATUS_VERIFIED,
        ], $result['ok'] ? 200 : 422);
    }

    // ══════════════════════════════════════════════════════════════
    // REJECT
    // ══════════════════════════════════════════════════════════════
    public function reject(Request $request, Pige $pige): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ], [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
            'rejection_reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        $result = $this->pigeService->reject($pige, auth()->user(), $request->rejection_reason);
        return response()->json([
            'success' => $result['ok'],
            'message' => $result['ok'] ? $result['message'] : $result['error'],
            'already' => $result['already'] ?? false,
            'status'  => Pige::STATUS_REJECTED,
        ], $result['ok'] ? 200 : 422);
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════
    public function destroy(Request $request, Pige $pige)
    {
        $force  = $request->boolean('force', false);
        $result = $this->pigeService->destroy($pige, auth()->user(), $force);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $result['ok'],
                'message' => $result['ok'] ? $result['message'] : $result['error'],
            ], $result['ok'] ? 200 : 422);
        }

        return redirect()->route('admin.piges.index')
            ->with($result['ok'] ? 'success' : 'error',
                   $result['ok'] ? $result['message'] : $result['error']);
    }

    // ══════════════════════════════════════════════════════════════
    // CONTEXT AJAX
    // ══════════════════════════════════════════════════════════════
    public function context(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'panel_id'    => 'nullable|integer|exists:panels,id',
        ]);

        $ctx = $this->pigeService->resolveContext(
            $request->integer('campaign_id') ?: null,
            $request->integer('panel_id') ?: null,
        );

        if ($request->filled('campaign_id')) {
            $ctx['stats'] = $this->pigeService->campaignStats($request->campaign_id);
        }

        return response()->json($ctx);
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT PDF
    // ══════════════════════════════════════════════════════════════
    public function exportPdf(Request $request)
    {
        $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'client_id'   => 'nullable|exists:clients,id',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Pige::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign:id,name,client_id',
            'campaign.client:id,name',
            'takenBy:id,name',
            'verifiedBy:id,name',
        ])->where('status', Pige::STATUS_VERIFIED);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->campaign_id);
        }
        if ($request->filled('client_id')) {
            $query->whereHas('campaign', fn($q) => $q->where('client_id', (int) $request->client_id));
        }
        if ($request->filled('date_from')) {
            $query->where('taken_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('taken_at', '<=', $request->date_to . ' 23:59:59');
        }

        $piges    = $query->latest()->get();
        $campaign = $request->filled('campaign_id') ? Campaign::find($request->campaign_id) : null;
        $client   = $request->filled('client_id')   ? Client::find($request->client_id)     : null;

        if ($piges->isEmpty()) {
            return back()->with('error', 'Aucune pige vérifiée pour ces critères.');
        }

        $pdf = Pdf::loadView('pdf.piges-report', compact('piges', 'campaign', 'client'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
                'dpi'                  => 96,
            ]);

        $slug     = $campaign ? \Str::slug($campaign->name) : ($client ? \Str::slug($client->name) : 'global');
        $filename = 'rapport-piges-' . $slug . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }

    // ══════════════════════════════════════════════════════════════
    // BY CAMPAIGN
    // ══════════════════════════════════════════════════════════════
    public function byCampaign(Campaign $campaign)
    {
        $piges = Pige::with(['panel:id,reference,name', 'takenBy:id,name'])
            ->where('campaign_id', $campaign->id)
            ->latest('taken_at')
            ->paginate(24);

        $stats = $this->pigeService->campaignStats($campaign->id);
        $ctx   = $this->pigeService->resolveContext($campaign->id, null);

        return view('admin.piges.by-campaign', compact('piges', 'campaign', 'stats', 'ctx'));
    }
}