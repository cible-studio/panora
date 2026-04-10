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
        $agencies = ExternalAgency::query()
            ->withCount('externalPanels')
            ->with(['externalPanels.campaigns.client', 'externalPanels.campaigns'])
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('contact', 'like', "%$s%")
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.external-agencies.index', compact('agencies'));
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
