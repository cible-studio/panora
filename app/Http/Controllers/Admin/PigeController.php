<?php
// app/Http/Controllers/Admin/PigeController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\User;
use App\Services\AlertService;
use App\Services\PigeService;
use App\Enums\CampaignStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;


class PigeController extends Controller
{
    public function __construct(
        protected PigeService  $pigeService,
        protected AlertService $alertService,
    ) {}

    // ══════════════════════════════════════════════════════════════
    // INDEX — liste avec filtres
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Pige::with([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'campaign:id,name,status',
            'technicien:id,name',
            'verificateur:id,name',
        ]);

        // ── Filtres ──────────────────────────────────────────────
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }
        if ($request->filled('panel_id')) {
            $query->where('panel_id', $request->panel_id);
        }
        if ($request->filled('technicien_id')) {
            $query->where('user_id', $request->technicien_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('taken_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('taken_at', '<=', $request->date_to);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sq) =>
                $sq->whereHas('panel', fn($p) =>
                    $p->where('reference', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                )
                ->orWhereHas('campaign', fn($c) => $c->where('name', 'like', "%{$q}%"))
                ->orWhereHas('technicien', fn($u) => $u->where('name', 'like', "%{$q}%"))
            );
        }

        $piges       = $query->latest('taken_at')->paginate(24)->withQueryString();
        $stats       = $this->pigeService->getStats();
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);
        $campaigns   = Campaign::whereIn('status', [
            CampaignStatus::ACTIF->value,
            CampaignStatus::POSE->value,
        ])->orderBy('name')->get(['id', 'name', 'status']);

        // Panneau pré-filtré (depuis index poses)
        $filterPanel = $request->filled('panel_id')
            ? Panel::with('commune:id,name')->find($request->panel_id)
            : null;

        // Campagne pré-filtrée
        $filterCampaign = $request->filled('campaign_id')
            ? Campaign::find($request->campaign_id)
            : null;

        return view('admin.piges.index', compact(
            'piges', 'stats', 'techniciens', 'campaigns',
            'filterPanel', 'filterCampaign'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE — formulaire upload
    // ══════════════════════════════════════════════════════════════
    public function create(Request $request)
    {
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name']);

        $preselectedCampaign = null;
        $preselectedPanel    = null;

        if ($request->filled('campaign_id')) {
            $preselectedCampaign = Campaign::with([
                'panels:id,reference,name,commune_id',
                'panels.commune:id,name',
            ])->find($request->campaign_id);
        }
        if ($request->filled('panel_id')) {
            $preselectedPanel = Panel::with('commune:id,name')->find($request->panel_id);
        }

        return view('admin.piges.create', compact(
            'techniciens', 'preselectedCampaign', 'preselectedPanel'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // STORE — upload photo(s)
    // ══════════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $request->validate([
            'panel_id'    => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'photos'      => 'required|array|min:1|max:10',
            'photos.*' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:30720',
            'taken_at'    => 'nullable|date|before_or_equal:now',
            'gps_lat'     => 'nullable|numeric|between:-90,90',
            'gps_lng'     => 'nullable|numeric|between:-180,180',
            'notes'       => 'nullable|string|max:500',
        ], [
            'panel_id.required'   => 'Le panneau est obligatoire.',
            'panel_id.exists'     => 'Panneau introuvable.',
            'campaign_id.exists'  => 'Campagne introuvable.',
            'photos.required'     => 'Veuillez sélectionner au moins une photo.',
            'photos.min'          => 'Veuillez sélectionner au moins une photo.',
            'photos.max'          => 'Maximum 10 photos par upload.',
            'photos.*.image'      => 'Chaque fichier doit être une image.',
            'photos.*.mimes'      => 'Formats acceptés : JPG, PNG, WebP.',
            'photos.*.max' => 'Chaque photo ne doit pas dépasser 30 Mo.',
            'taken_at.before_or_equal' => 'La date de prise de vue ne peut pas être dans le futur.',
            'gps_lat.between'     => 'Latitude invalide (entre -90 et 90).',
            'gps_lng.between'     => 'Longitude invalide (entre -180 et 180).',
            'notes.max'           => 'Les notes ne doivent pas dépasser 500 caractères.',
        ]);

        $result = $this->pigeService->upload(
            photos:     $request->file('photos'),
            panelId:    (int) $request->panel_id,
            campaignId: $request->campaign_id ? (int) $request->campaign_id : null,
            uploader:   auth()->user(),
            meta: [
                'taken_at' => $request->taken_at,
                'gps_lat'  => $request->gps_lat,
                'gps_lng'  => $request->gps_lng,
                'notes'    => $request->notes,
            ]
        );

        if (!$result['ok']) {
            return back()->withInput()->with('error', $result['error']);
        }

        $msg = $result['count'] . ' pige(s) uploadée(s) avec succès — en attente de vérification.';

        // Rediriger vers index filtré sur la campagne/panneau
        $redirectParams = array_filter([
            'campaign_id' => $request->campaign_id,
            'panel_id'    => $request->panel_id,
        ]);

        return redirect()->route('admin.piges.index', $redirectParams)->with('success', $msg);
    }

    public function edit(Pige $pige)
    {
        $pige->load(['panel.commune', 'campaign', 'technicien', 'verificateur']);
        $techniciens = User::orderBy('name')->get(['id', 'name']);

        $prevPige = Pige::where('id', '<', $pige->id)
            ->when($pige->campaign_id, fn($q) => $q->where('campaign_id', $pige->campaign_id))
            ->latest('id')->first();

        $nextPige = Pige::where('id', '>', $pige->id)
            ->when($pige->campaign_id, fn($q) => $q->where('campaign_id', $pige->campaign_id))
            ->oldest('id')->first();

        return view('admin.piges.edit', compact('pige', 'techniciens', 'prevPige', 'nextPige'));
    }

    public function update(Request $request, Pige $pige)
    {
        // Une pige vérifiée ne peut pas être entièrement modifiée
        // mais on autorise les métadonnées (notes, taken_at, gps)
        $isVerified = $pige->isVerifiee();
 
        $rules = [
            'taken_at' => 'nullable|date',
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:1000',
            'user_id'  => 'nullable|exists:users,id',
        ];
 
        if (!$isVerified) {
            $rules['photo']            = 'nullable|image|mimes:jpg,jpeg,png,webp|max:30720';
            $rules['status']           = 'nullable|in:en_attente,verifie,rejete';
            $rules['rejection_reason'] = 'nullable|string|max:500';
        }
 
        $messages = [
            'photo.max'                => 'La photo ne doit pas dépasser 30 Mo.',
            'photo.mimes'              => 'Format accepté : JPG, PNG, WebP.',
            'gps_lat.between'          => 'Latitude invalide (entre -90 et 90).',
            'gps_lng.between'          => 'Longitude invalide (entre -180 et 180).',
            'rejection_reason.required'=> 'Le motif de rejet est obligatoire.',
            'notes.max'                => 'Les notes ne doivent pas dépasser 1000 caractères.',
        ];
 
        // Motif obligatoire si statut = rejete
        if (!$isVerified && $request->input('status') === 'rejete') {
            $rules['rejection_reason'] = 'required|string|max:500';
        }
 
        $validated = $request->validate($rules, $messages);
 
        $data = [];

        if (array_key_exists('taken_at', $validated)) $data['taken_at'] = $validated['taken_at'];
        if (array_key_exists('gps_lat',  $validated)) $data['gps_lat']  = $validated['gps_lat'];
        if (array_key_exists('gps_lng',  $validated)) $data['gps_lng']  = $validated['gps_lng'];
        if (array_key_exists('notes',    $validated)) $data['notes']    = $validated['notes'];
        if (array_key_exists('user_id',  $validated)) $data['user_id']  = $validated['user_id'];
 
        // Statut et motif (si non vérifiée)
        if (!$isVerified) {
            if ($request->filled('status')) {
                $data['status'] = $validated['status'];
                if ($validated['status'] === 'rejete') {
                    $data['rejection_reason'] = $validated['rejection_reason'];
                    if (!$pige->verified_by) {
                        $data['verified_by'] = auth()->id();
                        $data['verified_at'] = now();
                    }
                } elseif ($validated['status'] === 'verifie') {
                    $data['rejection_reason'] = null;
                    $data['verified_by']      = auth()->id();
                    $data['verified_at']      = now();
                } else {
                    $data['rejection_reason'] = null;
                    $data['verified_by']      = null;
                    $data['verified_at']      = null;
                }
            }
 
            // Remplacer la photo si une nouvelle est fournie
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                // Supprimer l'ancienne
                if ($pige->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($pige->photo_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($pige->photo_path);
                }
                if ($pige->photo_thumb) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($pige->photo_thumb);
                }
 
                $file     = $request->file('photo');
                $ext      = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = \Illuminate\Support\Str::uuid() . '.' . $ext;
                $folder   = 'piges/' . ($pige->campaign_id ? "campaigns/{$pige->campaign_id}" : 'libre') . "/panel_{$pige->panel_id}";
                $path     = $file->storeAs($folder, $filename, 'public');
 
                $data['photo_path']  = $path;
                $data['photo_thumb'] = null;
            }
        }
 
        $pige->update($data);
 
        \Illuminate\Support\Facades\Log::info('pige.updated', [
            'pige_id' => $pige->id,
            'by'      => auth()->id(),
            'changes' => array_keys($data),
        ]);
 
        return redirect()
            ->route('admin.piges.show', $pige)
            ->with('success', 'Pige mise à jour avec succès.');
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW — détail d'une pige
    // ══════════════════════════════════════════════════════════════
    public function show(Pige $pige)
    {
        $pige->load([
            'panel.commune', 'panel.format',
            'campaign.client',
            'technicien',
            'verificateur',
        ]);

        return view('admin.piges.show', compact('pige'));
    }

    // ══════════════════════════════════════════════════════════════
    // VERIFY — marquer vérifiée
    // ══════════════════════════════════════════════════════════════
    public function verify(Request $request, Pige $pige)
    {
        $result = $this->pigeService->verify($pige, auth()->user());

        if (!$result['ok']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->with('error', $result['error']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pige vérifiée avec succès.']);
        }

        return back()->with('success', 'Pige marquée comme vérifiée.');
    }

    // ══════════════════════════════════════════════════════════════
    // REJECT — rejeter avec motif
    // ══════════════════════════════════════════════════════════════
    public function reject(Request $request, Pige $pige)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
            'rejection_reason.max'      => 'Le motif ne doit pas dépasser 500 caractères.',
        ]);

        $result = $this->pigeService->reject($pige, auth()->user(), $request->rejection_reason);

        if (!$result['ok']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['error']], 422);
            }
            return back()->with('error', $result['error']);
        }

        // Alerte instantanée
        $this->alertService->notifyPigeRejected($pige->fresh()->load('panel'), $request->rejection_reason);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pige rejetée.']);
        }

        return back()->with('success', 'Pige rejetée — le technicien a été notifié.');
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY — supprimer
    // ══════════════════════════════════════════════════════════════
    public function destroy(Pige $pige)
    {
        $result = $this->pigeService->delete($pige);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Pige supprimée.');
    }

    // ══════════════════════════════════════════════════════════════
    // VERIFY BATCH — vérifier plusieurs piges en 1 clic
    // POST /admin/piges/verify-batch
    // ══════════════════════════════════════════════════════════════
    public function verifyBatch(Request $request): JsonResponse
    {
        $request->validate(['pige_ids' => 'required|array|min:1']);

        $pigeIds   = array_filter((array) $request->pige_ids, fn($v) => is_numeric($v));
        $piges     = Pige::whereIn('id', $pigeIds)->where('status', 'en_attente')->get();
        $count     = 0;

        foreach ($piges as $pige) {
            $result = $this->pigeService->verify($pige, auth()->user());
            if ($result['ok']) $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} pige(s) vérifiée(s) avec succès.",
            'count'   => $count,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — panneaux d'une campagne (pour le formulaire upload)
    // GET /admin/piges/campaign-panels?campaign_id=X
    // ══════════════════════════════════════════════════════════════
    public function campaignPanels(Request $request): JsonResponse
    {
        $request->validate(['campaign_id' => 'required|integer|exists:campaigns,id']);
 
        $campaign = Campaign::with([
            'panels:id,reference,name,commune_id',
            'panels.commune:id,name',
        ])->findOrFail($request->campaign_id);
 
        // Stats piges par panneau (1 requête)
        $pigesStats = Pige::where('campaign_id', $campaign->id)
            ->selectRaw('panel_id,
                COUNT(*) as total,
                SUM(status="verifie") as verifie,
                SUM(status="rejete")  as rejete,
                SUM(status="en_attente") as en_attente
            ')
            ->groupBy('panel_id')
            ->get()
            ->keyBy('panel_id');
 
        // Statuts pose par panneau (1 requête)
        $poseTasks = \App\Models\PoseTask::where('campaign_id', $campaign->id)
            ->whereIn('status', ['realisee', 'en_cours', 'planifiee'])
            ->latest()
            ->get(['panel_id', 'status', 'done_at'])
            ->keyBy('panel_id');
 
        $panels = $campaign->panels->map(fn($p) => [
            'id'            => $p->id,
            'reference'     => $p->reference,
            'name'          => $p->name,
            'commune'       => $p->commune?->name ?? '—',
            // Stats pige
            'pige_total'    => (int) ($pigesStats[$p->id]->total      ?? 0),
            'pige_verifie'  => (int) ($pigesStats[$p->id]->verifie    ?? 0),
            'pige_rejete'   => (int) ($pigesStats[$p->id]->rejete     ?? 0),
            'pige_attente'  => (int) ($pigesStats[$p->id]->en_attente ?? 0),
            // Statut pose (pour afficher "Posé" dans la liste)
            'pose_status'   => $poseTasks[$p->id]?->status ?? null,
            'pose_date'     => $poseTasks[$p->id]?->done_at?->format('d/m/Y'),
        ]);
 
        return response()->json([
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name],
            'panels'   => $panels->values(),
        ]);
    }
}