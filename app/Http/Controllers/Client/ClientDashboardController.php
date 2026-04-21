<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Controllers/Client/ClientDashboardController.php
// VERSION COMPLÈTE ET CORRIGÉE
// ══════════════════════════════════════════════════════════════

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Services\PropositionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    public function __construct(
        protected PropositionService $propositionService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // DASHBOARD PRINCIPAL
    // ══════════════════════════════════════════════════════════════
       public function index()
    {
        $client = Auth::guard('client')->user();
        
        // Récupérer les IDs des campagnes une seule fois
        $campaignIds = $client->campaigns()->pluck('id');
        
        // Propositions (limitées)
        $propositions = $client->reservations()
            ->where('status', 'en_attente')
            ->whereNotNull('proposition_token')
            ->where('end_date', '>=', now())
            ->with(['panels' => fn($q) => $q->limit(10)])
            ->orderByDesc('proposition_sent_at')
            ->limit(5)
            ->get();
        
        // Campagnes actives
        $campagnesActives = $client->campaigns()
            ->whereIn('status', ['actif', 'pose'])
            ->withCount('panels')
            ->orderByDesc('start_date')
            ->limit(5)
            ->get();
        
        // Stats poses (une seule requête)
        $poseStats = PoseTask::whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                COUNT(CASE WHEN status = 'realisee' THEN 1 END) as realisees,
                COUNT(CASE WHEN status = 'planifiee' THEN 1 END) as planifiees
            ")->first();
        
        // Stats piges vérifiées
        $pigeStats = Pige::whereIn('campaign_id', $campaignIds)
            ->where('status', 'verifie')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(DISTINCT panel_id) as panneaux_couverts
            ")->first();
        
        // Poses récentes
        $recentPoses = PoseTask::whereIn('campaign_id', $campaignIds)
            ->where('status', 'realisee')
            ->with(['panel:id,reference,name', 'campaign:id,name'])
            ->orderByDesc('done_at')
            ->limit(5)
            ->get();
        
        // Piges récentes
        $recentPiges = Pige::whereIn('campaign_id', $campaignIds)
            ->where('status', 'verifie')
            ->with(['panel:id,reference,name', 'campaign:id,name'])
            ->orderByDesc('verified_at')
            ->limit(5)
            ->get();
        
        // Panneaux actifs (une seule requête)
        $activePanelsCount = $client->campaigns()
            ->whereIn('status', ['actif', 'pose'])
            ->withCount('panels')
            ->get()
            ->sum('panels_count');
        
        $stats = [
            'propositions_en_attente' => $client->reservations()
                ->where('status', 'en_attente')
                ->whereNotNull('proposition_token')
                ->where('end_date', '>=', now())
                ->count(),
            'campagnes_actives'  => $campagnesActives->count(),
            'campagnes_total'    => $client->campaigns()->count(),
            'panneaux_actifs'    => $activePanelsCount,
            'poses_realisees'    => (int) ($poseStats->realisees ?? 0),
            'poses_planifiees'   => (int) ($poseStats->planifiees ?? 0),
            'piges_verifiees'    => (int) ($pigeStats->total ?? 0),
            'panneaux_couverts'  => (int) ($pigeStats->panneaux_couverts ?? 0),
        ];
        
        return view('client.dashboard', compact(
            'client', 'propositions', 'campagnesActives', 'stats',
            'recentPoses', 'recentPiges'
        ));
    }


    // ══════════════════════════════════════════════════════════════
    // CAMPAGNES — liste filtrée
    // ══════════════════════════════════════════════════════════════
    public function campagnes(Request $request)
    {
        $client = Auth::guard('client')->user();

        $query = $client->campaigns()
            ->withCount('panels');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $campagnes = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('client.campagnes', compact('client', 'campagnes'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROPOSITIONS — liste
    // ══════════════════════════════════════════════════════════════
    public function propositions()
    {
        $client = Auth::guard('client')->user();

        $propositions = $client->reservations()
            ->whereNotNull('proposition_token')
            ->with(['panels.photos', 'panels.commune', 'panels.format'])
            ->orderByDesc('proposition_sent_at')
            ->paginate(10);

        return view('client.propositions', compact('client', 'propositions'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROPOSITION DETAIL
    // ══════════════════════════════════════════════════════════════
    public function propositionDetail(string $token)
    {
        $client = Auth::guard('client')->user();

        $reservation = $client->reservations()
            ->where('proposition_token', $token)
            ->first();

        if (!$reservation) abort(403, 'Cette proposition ne vous appartient pas.');

        try {
            $reservation = $this->propositionService->validerToken($token);
        } catch (\RuntimeException $e) {
            // Proposition expirée/traitée — afficher quand même
        }

        $reservation->load(['panels.photos', 'panels.commune', 'panels.format', 'panels.zone', 'panels.category']);
        $this->propositionService->marquerVue($reservation);

        $months = $this->monthsBetween($reservation->start_date, $reservation->end_date);
        $panels = $reservation->panels->map(function ($panel) use ($months) {
            $photo = $panel->photos->sortBy('ordre')->first();
            return [
                'id'           => $panel->id,
                'reference'    => $panel->reference,
                'name'         => $panel->name,
                'commune'      => $panel->commune?->name ?? '—',
                'zone'         => $panel->zone?->name ?? '—',
                'format'       => $panel->format?->name ?? '—',
                'dimensions'   => $this->formatDims($panel->format),
                'category'     => $panel->category?->name ?? '—',
                'is_lit'       => (bool) $panel->is_lit,
                'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
                'total'        => (float) ($panel->monthly_rate ?? 0) * $months,
                'photo_url'    => $photo ? asset('storage/' . ltrim($photo->path, '/')) : null,
            ];
        });

        $joursRestants = now()->startOfDay()->diffInDays($reservation->end_date->startOfDay(), false);

        return view('client.proposition-detail', compact(
            'reservation', 'panels', 'months', 'joursRestants', 'token', 'client'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CAMPAGNE DETAIL — avec poses + piges
    // ══════════════════════════════════════════════════════════════
    public function campagneDetail(Campaign $campaign)
    {
        $client = Auth::guard('client')->user();

        if ($campaign->client_id !== $client->id) abort(403, 'Accès non autorisé.');

        $campaign->load([
            'panels.photos',
            'panels.commune:id,name',
            'panels.format:id,name,width,height',
            'invoices',
        ]);

        $panelIds = $campaign->panels->pluck('id');

        // Poses réalisées par panneau — 1 requête, keyBy panel_id
        $poses = PoseTask::where('campaign_id', $campaign->id)
            ->where('status', 'realisee')
            ->orderByDesc('done_at')
            ->get(['panel_id', 'status', 'done_at'])
            ->keyBy('panel_id');

        // Piges vérifiées groupées par panneau — 1 requête
        $pigesVerif = Pige::where('campaign_id', $campaign->id)
            ->where('status', 'verifie')
            ->orderByDesc('verified_at')
            ->get(['id', 'panel_id', 'photo_path', 'photo_thumb', 'verified_at', 'gps_lat', 'gps_lng'])
            ->groupBy('panel_id'); // Collection de collections indexée par panel_id

        // KPI couverture
        $totalPanneaux   = $panelIds->count();
        $posesCount      = $poses->count();         // nb panneaux distincts posés
        $pigesCount      = $pigesVerif->count();    // nb panneaux distincts pigés
        $coveragePercent = $totalPanneaux > 0
            ? round(($pigesCount / $totalPanneaux) * 100)
            : 0;

        return view('client.campagne-detail', compact(
            'client', 'campaign',
            'poses', 'pigesVerif',
            'totalPanneaux', 'posesCount', 'pigesCount', 'coveragePercent'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // POSES — suivi terrain (client voit uniquement réalisées)
    // ══════════════════════════════════════════════════════════════
    public function poses(Request $request)
    {
        $client      = Auth::guard('client')->user();
        $campaignIds = $client->campaigns()->pluck('id');

        $query = PoseTask::whereIn('campaign_id', $campaignIds)
            ->where('status', 'realisee')
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name',
            ]);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->campaign_id);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('panel', fn($p) =>
                $p->where('reference', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
            );
        }
        if ($request->filled('date_from')) {
            $query->whereDate('done_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('done_at', '<=', $request->date_to);
        }

        $poses = $query->orderByDesc('done_at')->paginate(20)->withQueryString();

        // KPI global (toutes campagnes, tous statuts — pour contexte)
        $kpi = PoseTask::whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                SUM(CASE WHEN status = 'realisee' THEN 1 ELSE 0 END) as realisees,
                SUM(CASE WHEN status = 'planifiee' THEN 1 ELSE 0 END) as planifiees,
                SUM(CASE WHEN status = 'en_cours'  THEN 1 ELSE 0 END) as en_cours
            ")->first();

        $campaigns = $client->campaigns()
            ->orderByRaw("FIELD(status, 'actif', 'pose', 'termine', 'annule')")
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return view('client.poses', compact('poses', 'kpi', 'campaigns', 'client'));
    }

    // ══════════════════════════════════════════════════════════════
    // PIGES — preuves d'affichage (client voit uniquement vérifiées)
    // ══════════════════════════════════════════════════════════════
    public function piges(Request $request)
    {
        $client      = Auth::guard('client')->user();
        $campaignIds = $client->campaigns()->pluck('id');

        $query = Pige::whereIn('campaign_id', $campaignIds)
            ->where('status', 'verifie')
            ->with([
                'panel:id,reference,name,commune_id',
                'panel.commune:id,name',
                'campaign:id,name',
            ]);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->campaign_id);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('panel', fn($p) =>
                $p->where('reference', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
            );
        }
        if ($request->filled('date_from')) {
            $query->whereDate('verified_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('verified_at', '<=', $request->date_to);
        }

        $piges = $query->orderByDesc('verified_at')->paginate(24)->withQueryString();

        $kpi = Pige::whereIn('campaign_id', $campaignIds)
            ->selectRaw("
                SUM(CASE WHEN status = 'verifie' THEN 1 ELSE 0 END) as verifiees,
                COUNT(DISTINCT CASE WHEN status = 'verifie' THEN panel_id END) as panneaux_piges,
                COUNT(DISTINCT CASE WHEN status = 'verifie' THEN campaign_id END) as campagnes_avec_pige
            ")->first();

        $campaigns = $client->campaigns()
            ->orderByRaw("FIELD(status, 'actif', 'pose', 'termine', 'annule')")
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return view('client.piges', compact('piges', 'kpi', 'campaigns', 'client'));
    }

    // ══════════════════════════════════════════════════════════════
    // PROFIL
    // ══════════════════════════════════════════════════════════════
    public function profil()
    {
        return view('client.profil', ['client' => Auth::guard('client')->user()]);
    }

    public function updateProfil(Request $request)
    {
        $client = Auth::guard('client')->user();
        $data   = $request->validate([
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'contact_name' => 'nullable|string|max:150',
        ]);
        $client->update($data);
        return back()->with('success', 'Profil mis à jour.');
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════
    private function monthsBetween($start, $end): float
    {
        $s      = Carbon::parse($start)->startOfDay();
        $e      = Carbon::parse($end)->endOfDay();
        $months = (int) $s->diffInMonths($e);
        $remain = $s->copy()->addMonths($months)->diffInDays($e);
        return max((float) ($remain > 0 ? $months + 1 : $months), 1.0);
    }

    private function formatDims($format): ?string
    {
        if (!$format?->width || !$format?->height) return null;
        $w = rtrim(rtrim(number_format($format->width,  2, '.', ''), '0'), '.');
        $h = rtrim(rtrim(number_format($format->height, 2, '.', ''), '0'), '.');
        return "{$w}×{$h}m";
    }
}