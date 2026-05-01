<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalAgency\StoreExternalAgencyRequest;
use App\Http\Requests\ExternalAgency\UpdateExternalAgencyRequest;
use App\Http\Requests\ExternalAgency\StoreExternalPanelRequest;
use App\Http\Requests\ExternalAgency\UpdateExternalPanelRequest;
use App\Models\PanelCategory;
use App\Models\Commune;
use App\Models\ExternalAgency;
use App\Models\Client;
use App\Models\ExternalPanel;
use App\Models\PanelFormat;
use App\Models\Zone;
use Illuminate\Http\Request;

class ExternalAgencyController extends Controller
{
    // ── Liste des régies ──────────────────────────────────────
    public function index(Request $request)
    {
        $query = ExternalAgency::query()
            ->withCount('externalPanels')
            ->with(['externalPanels.campaigns.client', 'externalPanels.campaigns']);

        // Recherche : mot entier en début de mot (LIKE 'terme%') sur plusieurs colonnes
        if ($request->filled('search')) {
            $term     = trim($request->input('search'));
            $startLike = $term . '%';
            $anyLike  = '%' . $term . '%';

            $query->where(function ($q) use ($startLike, $anyLike) {
                $q->where('name', 'like', $startLike)
                  ->orWhere('manager_name', 'like', $startLike)
                  ->orWhere('commercial_name', 'like', $startLike)
                  ->orWhere('email', 'like', $startLike)
                  ->orWhere('commercial_email', 'like', $startLike)
                  ->orWhere('contact', 'like', $startLike)
                  // Recherche large sur ville/adresse pour catch les partials utiles
                  ->orWhere('city', 'like', $anyLike)
                  ->orWhere('address', 'like', $anyLike);
            });
        }

        // Filtre statut depuis les KPI cliquables : ?status=active|inactive
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $agencies = $query->orderBy('name')->paginate(15)->withQueryString();

        // Stats pour les KPI cliquables
        $stats = [
            'total'    => ExternalAgency::count(),
            'active'   => ExternalAgency::where('is_active', true)->count(),
            'inactive' => ExternalAgency::where('is_active', false)->count(),
        ];

        return view('admin.external-agencies.index', compact('agencies', 'stats'));
    }

    // ── Fiche régie + ses panneaux ────────────────────────────
    public function show(ExternalAgency $externalAgency)
    {
        $externalAgency->load([
            'externalPanels.commune',
            'externalPanels.format',
            'externalPanels.category',
            'externalPanels.client',
            'externalPanels.campaign',
        ]);
        $communes   = Commune::orderBy('name')->get();
        $zones      = Zone::orderBy('name')->get();
        $formats    = PanelFormat::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();
        $clients    = Client::with('campaigns')->orderBy('name')->get();

        return view('admin.external-agencies.show', [
            'agency'     => $externalAgency,
            'communes'   => $communes,
            'zones'      => $zones,
            'formats'    => $formats,
            'categories' => $categories,
            'clients'    => $clients,
        ]);
    }

    // ── Créer régie ───────────────────────────────────────────
    public function store(StoreExternalAgencyRequest $request)
    {
        ExternalAgency::create($request->validated());

        return redirect()
            ->route('admin.external-agencies.index')
            ->with('success', 'Régie créée avec succès.');
    }

    // ── Modifier régie ────────────────────────────────────────
    public function update(UpdateExternalAgencyRequest $request, ExternalAgency $externalAgency)
    {
        $externalAgency->update($request->validated());

        return redirect()
            ->route('admin.external-agencies.index')
            ->with('success', 'Régie mise à jour.');
    }

    // ── Supprimer régie ───────────────────────────────────────
    public function destroy(ExternalAgency $externalAgency)
    {
        $externalAgency->delete();

        return redirect()
            ->route('admin.external-agencies.index')
            ->with('success', 'Régie supprimée.');
    }

    // ══════════════════════════════════════════════════════════
    // PANNEAUX EXTERNES (actions imbriquées)
    // ══════════════════════════════════════════════════════════

    // ── Ajouter un panneau à une régie ────────────────────────
    public function storePanel(StoreExternalPanelRequest $request, ExternalAgency $externalAgency)
    {
        $data = array_merge($request->validated(), [
            'client_id'   => $request->client_id ?: null,
            'campaign_id' => $request->campaign_id ?: null,
        ]);

        $externalAgency->externalPanels()->create($data);

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau ajouté avec succès.');
    }

    // ── Modifier un panneau ───────────────────────────────────
    public function updatePanel(UpdateExternalPanelRequest $request, ExternalAgency $externalAgency, ExternalPanel $panel)
    {
        abort_if($panel->agency_id !== $externalAgency->id, 403);
        $panel->update($request->validated());

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau modifié.');
    }

    // ── Supprimer un panneau ──────────────────────────────────
    public function destroyPanel(ExternalAgency $externalAgency, ExternalPanel $panel)
    {
        abort_if($panel->agency_id !== $externalAgency->id, 403);
        $panel->delete();

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau supprimé.');
    }
}
