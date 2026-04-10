<?php
// app/Http/Controllers/Admin/PigeController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pige;
use App\Models\Panel;
use App\Models\Campaign;
use App\Models\Client;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PigeController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // INDEX — Liste piges (admin)
    // +10 000 données : pagination 24/page, filtres AJAX-ready
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        // ── Base query avec eager loading minimal (perf)
        $query = Pige::with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name,client_id',
                'campaign.client:id,name',
                'takenBy:id,name',
            ])
            ->select([
                'id', 'panel_id', 'campaign_id', 'user_id',
                'photo_path', 'taken_at', 'gps_lat', 'gps_lng',
                'status', 'verified_by', 'verified_at', 'notes', 'created_at',
            ]);

        // ── Filtres
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int)$request->campaign_id);
        }
        if ($request->filled('panel_id')) {
            $query->where('panel_id', (int)$request->panel_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->whereHas('campaign', fn($q) => $q->where('client_id', (int)$request->client_id));
        }
        if ($request->filled('date_from')) {
            $query->where('taken_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('taken_at', '<=', $request->date_to . ' 23:59:59');
        }
        if ($request->filled('q')) {
            $like = '%' . $request->q . '%';
            $query->where(fn($q) =>
                $q->whereHas('panel', fn($p) => $p->where('reference', 'like', $like)->orWhere('name', 'like', $like))
                  ->orWhereHas('campaign', fn($c) => $c->where('name', 'like', $like))
            );
        }

        // ── Tri
        $query->latest('taken_at');

        $piges = $query->paginate(24)->withQueryString();

        // ── Stats globales (1 requête agrégée, pas N+1)
        $stats = Pige::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "en_attente" THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN status = "verifie"    THEN 1 ELSE 0 END) as verifie,
            SUM(CASE WHEN status = "rejete"     THEN 1 ELSE 0 END) as rejete
        ')->first();

        // ── Données filtres (SELECT léger)
        $campaigns = Campaign::orderBy('name')->get(['id', 'name', 'client_id']);
        $clients   = Client::orderBy('name')->get(['id', 'name']);
        $panels    = Panel::orderBy('reference')->get(['id', 'reference', 'name']);

        // Réponse AJAX pour pagination dynamique
        if ($request->ajax()) {
            return response()->json([
                'html'  => view('admin.piges.partials.grid', compact('piges'))->render(),
                'stats' => $stats,
                'pages' => $piges->lastPage(),
                'total' => $piges->total(),
            ]);
        }

        return view('admin.piges.index', compact(
            'piges', 'campaigns', 'clients', 'panels', 'stats'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // UPLOAD — Nouvelle pige (admin ou technicien)
    // ══════════════════════════════════════════════════════════════

    public function upload(Request $request)
    {
        $request->validate([
            'panel_id'    => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'photo'       => 'required|image|mimes:jpeg,jpg,png,webp|max:8192', // 8 Mo max
            'taken_at'    => 'required|date|before_or_equal:now',
            'gps_lat'     => 'nullable|numeric|between:-90,90',
            'gps_lng'     => 'nullable|numeric|between:-180,180',
            'notes'       => 'nullable|string|max:1000',
        ], [
            'photo.max'        => 'La photo ne doit pas dépasser 8 Mo.',
            'taken_at.before_or_equal' => 'La date de prise de vue ne peut pas être dans le futur.',
        ]);

        $path = $request->file('photo')->store('piges/' . now()->format('Y/m'), 'public');

        $pige = Pige::create([
            'panel_id'    => $request->panel_id,
            'campaign_id' => $request->campaign_id ?: null,
            'user_id'     => auth()->id(),
            'photo_path'  => $path,
            'taken_at'    => $request->taken_at,
            'gps_lat'     => $request->gps_lat,
            'gps_lng'     => $request->gps_lng,
            'notes'       => $request->notes,
            'status'      => Pige::STATUS_PENDING,
        ]);

        Log::info('pige.uploaded', [
            'pige_id'     => $pige->id,
            'panel_id'    => $pige->panel_id,
            'campaign_id' => $pige->campaign_id,
            'user_id'     => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'pige_id' => $pige->id], 201);
        }

        return redirect()->route('admin.piges.index')
            ->with('success', 'Pige uploadée avec succès.');
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW — Détail d'une pige
    // ══════════════════════════════════════════════════════════════

    public function show(Pige $pige)
    {
        $pige->load([
            'panel:id,reference,name,commune_id',
            'panel.commune:id,name',
            'panel.format:id,name,width,height',
            'panel.photos' => fn($q) => $q->orderBy('ordre')->limit(1),
            'campaign:id,name,client_id',
            'campaign.client:id,name',
            'takenBy:id,name',
            'verifiedBy:id,name',
        ]);
    
        // ── Réponse JSON pour la lightbox AJAX ──
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'id'               => $pige->id,
                'panel_ref'        => $pige->panel?->reference,
                'panel_name'       => $pige->panel?->name,
                'commune'          => $pige->panel?->commune?->name,
                'campaign'         => $pige->campaign?->name,
                'client'           => $pige->campaign?->client?->name,
                'taken_at'         => $pige->taken_at?->format('d/m/Y à H:i'),
                'taken_by'         => $pige->takenBy?->name,
                'verified_by'      => $pige->verifiedBy?->name,
                'verified_at'      => $pige->verified_at?->format('d/m/Y à H:i'),
                'gps_lat'          => $pige->gps_lat,
                'gps_lng'          => $pige->gps_lng,
                'notes'            => $pige->notes,
                'status'           => $pige->status,
                'rejection_reason' => $pige->rejection_reason,
                'photo_url'        => asset('storage/' . $pige->photo_path),
            ]);
        }
    
        // ── Vue HTML complète ──
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
    
        return view('admin.piges.show', compact('pige', 'siblings'));
    }

    // ══════════════════════════════════════════════════════════════
    // VERIFY — Valider une pige
    // ══════════════════════════════════════════════════════════════

    public function verify(Request $request, Pige $pige)
    {
        if ($pige->isVerified()) {
            return back()->with('info', 'Cette pige est déjà vérifiée.');
        }

        $pige->update([
            'status'      => Pige::STATUS_VERIFIED,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        Log::info('pige.verified', ['pige_id' => $pige->id, 'by' => auth()->id()]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'status' => 'verifie']);
        }

        return back()->with('success', 'Pige validée. ✅');
    }

    // ══════════════════════════════════════════════════════════════
    // REJECT — Rejeter une pige (avec motif obligatoire)
    // ══════════════════════════════════════════════════════════════

    public function reject(Request $request, Pige $pige)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ], [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
            'rejection_reason.min'      => 'Le motif doit contenir au moins 5 caractères.',
        ]);

        $pige->update([
            'status'           => Pige::STATUS_REJECTED,
            'verified_by'      => auth()->id(),
            'verified_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        Log::info('pige.rejected', [
            'pige_id' => $pige->id,
            'by'      => auth()->id(),
            'reason'  => $request->rejection_reason,
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'status' => 'rejete']);
        }

        return back()->with('warning', 'Pige rejetée. Le technicien devra en soumettre une nouvelle.');
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY — Supprimer une pige
    // ══════════════════════════════════════════════════════════════

    public function destroy(Pige $pige)
    {
        // Seules les piges en attente ou rejetées peuvent être supprimées
        if ($pige->isVerified()) {
            return back()->with('error', 'Impossible de supprimer une pige validée.');
        }

        Storage::disk('public')->delete($pige->photo_path);
        $pige->delete();

        Log::info('pige.deleted', ['pige_id' => $pige->id, 'by' => auth()->id()]);

        return redirect()->route('admin.piges.index')
            ->with('success', 'Pige supprimée.');
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT PDF — Rapport de piges (livrable client)
    // ══════════════════════════════════════════════════════════════

    public function exportPdf(Request $request)
    {
        $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'client_id'   => 'nullable|exists:clients,id',
            'status'      => 'nullable|in:en_attente,verifie,rejete',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Pige::with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'panel.format:id,name,width,height',
                'campaign:id,name,client_id',
                'campaign.client:id,name',
                'takenBy:id,name',
            ])
            ->where('status', Pige::STATUS_VERIFIED); // rapport = seulement vérifiées

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int)$request->campaign_id);
        }
        if ($request->filled('client_id')) {
            $query->whereHas('campaign', fn($q) => $q->where('client_id', (int)$request->client_id));
        }
        if ($request->filled('date_from')) {
            $query->where('taken_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('taken_at', '<=', $request->date_to . ' 23:59:59');
        }

        $pigesRaw = $query->orderBy('taken_at')->get();

        // Enrichir les photos en base64 pour DomPDF
        $piges = $pigesRaw->map(function ($pige) {
            $imgB64 = null;
            foreach ([
                storage_path('app/public/' . $pige->photo_path),
                public_path('storage/' . $pige->photo_path),
            ] as $path) {
                if (file_exists($path)) {
                    $ext    = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $mime   = match($ext) { 'png'=>'image/png', 'webp'=>'image/webp', default=>'image/jpeg' };
                    $imgB64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                    break;
                }
            }
            $pige->photo_b64 = $imgB64;
            return $pige;
        });

        $campaign = $request->filled('campaign_id') ? Campaign::with('client')->find($request->campaign_id) : null;
        $client   = $request->filled('client_id')   ? Client::find($request->client_id)                     : ($campaign?->client ?? null);

        $pdf = Pdf::loadView('pdf.piges-report', compact('piges', 'campaign', 'client'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
                'dpi'                  => 96,
            ]);

        $filename = 'rapport-piges-' . ($campaign?->name ? \Str::slug($campaign->name) : 'cibleci') . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }

    // ══════════════════════════════════════════════════════════════
    // PIGES PAR CAMPAGNE (utilisé dans CampaignController::show)
    // ══════════════════════════════════════════════════════════════

    public function byCampaign(Request $request, Campaign $campaign)
    {
        $piges = Pige::with(['panel:id,reference,name', 'takenBy:id,name'])
            ->where('campaign_id', $campaign->id)
            ->latest('taken_at')
            ->paginate(24);

        $stats = [
            'total'      => $piges->total(),
            'verifie'    => Pige::where('campaign_id', $campaign->id)->where('status', 'verifie')->count(),
            'en_attente' => Pige::where('campaign_id', $campaign->id)->where('status', 'en_attente')->count(),
            'rejete'     => Pige::where('campaign_id', $campaign->id)->where('status', 'rejete')->count(),
        ];

        return view('admin.piges.by-campaign', compact('piges', 'campaign', 'stats'));
    }
}