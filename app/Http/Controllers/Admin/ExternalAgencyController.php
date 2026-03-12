<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalAgency\StoreExternalAgencyRequest;
use App\Http\Requests\ExternalAgency\UpdateExternalAgencyRequest;
use App\Http\Requests\ExternalPanel\StoreExternalPanelRequest;
use App\Http\Requests\ExternalPanel\UpdateExternalPanelRequest;
use App\Models\Commune;
use App\Models\ExternalAgency;
use App\Models\ExternalPanel;
use Illuminate\Http\Request;

class ExternalAgencyController extends Controller
{
    // ── Liste des régies ──────────────────────────────────────
    public function index(Request $request)
    {
        $agencies = ExternalAgency::query()
            ->withCount('externalPanels')
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
        $externalAgency->load(['externalPanels.commune']);
        $communes = Commune::orderBy('name')->get();

        return view('admin.external-agencies.show', [
            'agency'   => $externalAgency,
            'communes' => $communes,
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
        $externalAgency->externalPanels()->create($request->validated());

        return redirect()
            ->route('admin.external-agencies.show', $externalAgency)
            ->with('success', 'Panneau ajouté.');
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