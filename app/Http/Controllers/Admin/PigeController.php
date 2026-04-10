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

        $piges     = $query->latest()->paginate(20)->withQueryString();
        $campaigns = Campaign::orderBy('name')->get();
        $panels    = Panel::orderBy('reference')->get();

        $totalPiges      = Pige::count();
        $totalVerifiees  = Pige::where('is_verified', true)->count();
        $totalEnAttente  = Pige::where('is_verified', false)->count();

        return view('admin.piges.index', compact(
            'piges', 'campaigns', 'panels',
            'totalPiges', 'totalVerifiees', 'totalEnAttente'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // UPLOAD — Nouvelle pige (admin ou technicien)
    // ══════════════════════════════════════════════════════════════

    public function upload(Request $request)
    {
        $request->validate([
            'panel_id' => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'photo'       => 'required|image|max:5120',
            'date_prise'  => 'required|date',
            'gps_lat'     => 'nullable|numeric',
            'gps_lng'     => 'nullable|numeric',
            'notes'       => 'nullable|string',
        ]);

        $path = $request->file('photo')->store('piges/' . now()->format('Y/m'), 'public');

        Pige::create([
            'panel_id'    => $request->panel_id,
            'campaign_id' => $request->campaign_id,
            'user_id'     => auth()->id(),
            'photo_path'  => $path,
            'taken_at'    => $request->date_prise,
            'gps_lat'     => $request->gps_lat,
            'gps_lng'     => $request->gps_lng,
            'notes'       => $request->notes,
            'is_verified' => false,
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

        $piges    = $query->latest()->get();
        $campaign = $request->filled('campaign_id')
            ? Campaign::find($request->campaign_id)
            : null;

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
