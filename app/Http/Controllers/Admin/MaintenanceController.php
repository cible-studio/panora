<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Panel;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AlertService;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Maintenance::with('panel', 'technicien', 'signaledBy');

        // Filtres
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('priorite')) {
            $query->where('priorite', $request->priorite);
        }
        if ($request->filled('search')) {
            $query->whereHas('panel', function ($q) use ($request) {
                $q->where('reference', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        $maintenances = $query
            ->orderByRaw("FIELD(priorite, 'urgente','haute','normale','faible')")
            ->orderByRaw("FIELD(statut, 'signale','en_cours','resolu','annule')")
            ->paginate(15)
            ->withQueryString();

        // Stats
        $totalSignales = Maintenance::where('statut', 'signale')->count();
        $totalEnCours = Maintenance::where('statut', 'en_cours')->count();
        $totalUrgentes = Maintenance::where('priorite', 'urgente')
            ->whereNotIn('statut', ['resolu', 'annule'])->count();
        $totalResolus = Maintenance::where('statut', 'resolu')->count();

        return view('admin.maintenances.index', compact(
            'maintenances',
            'totalSignales',
            'totalEnCours',
            'totalUrgentes',
            'totalResolus'
        ));
    }

    public function create()
    {
        $panels = Panel::orderBy('reference')->get();
        $techniciens = User::where('role', 'technique')->orderBy('name')->get();
        return view('admin.maintenances.create', compact('panels', 'techniciens'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'panel_id' => 'required|exists:panels,id',
            'type_panne' => 'required|string|max:255',
            'priorite' => 'required|in:faible,normale,haute,urgente',
            'description' => 'nullable|string',
            'technicien_id' => 'nullable|exists:users,id',
            'date_signalement' => 'required|date',
        ]);

        $maintenance = Maintenance::create([
            ...$request->all(),
            'signale_par' => auth()->id(),
            'statut' => 'signale',
        ]);

        // Mettre panneau en maintenance
        Panel::find($request->panel_id)
            ->update(['status' => 'maintenance']);

        AlertService::create(
            'maintenance',
            'danger',
            '🔧 Panne signalée — ' . $maintenance->panel->reference,
            auth()->user()->name . ' a signalé une panne sur ' . $maintenance->panel->reference . ' : ' . $maintenance->type_panne . ' (priorité: ' . $maintenance->priorite . ').',
            $maintenance
        );
        return redirect()->route('admin.maintenances.index')
            ->with('success', 'Maintenance signalée avec succès !');
    }

    public function show(Maintenance $maintenance)
    {
        $maintenance->load('panel', 'technicien', 'signaledBy');
        return view('admin.maintenances.show', compact('maintenance'));
    }

    public function edit(Maintenance $maintenance)
    {
        $panels = Panel::orderBy('reference')->get();
        $techniciens = User::where('role', 'technique')->orderBy('name')->get();
        return view('admin.maintenances.edit', compact(
            'maintenance',
            'panels',
            'techniciens'
        ));
    }

    public function update(Request $request, Maintenance $maintenance)
    {
        $request->validate([
            'type_panne' => 'required|string|max:255',
            'priorite' => 'required|in:faible,normale,haute,urgente',
            'statut' => 'required|in:signale,en_cours,resolu,annule',
            'technicien_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'solution' => 'nullable|string',
            'date_resolution' => 'nullable|date',
        ]);

        $maintenance->update($request->all());

        // Alerte modification maintenance (uniquement si changements importants)
        AlertService::create(
            'maintenance',
            'info',
            '✏️ Maintenance modifiée — ' . $maintenance->panel->reference,
            auth()->user()->name . ' a modifié la maintenance de ' . $maintenance->panel->reference . ' (statut: ' . $maintenance->statut . ', priorité: ' . $maintenance->priorite . ').',
             $maintenance
        );

        return redirect()->route('admin.maintenances.index')
            ->with('success', 'Maintenance mise à jour !');
    }

    public function destroy(Maintenance $maintenance)
    {
        $maintenance->delete();
        return redirect()->route('admin.maintenances.index')
            ->with('success', 'Maintenance supprimée !');
    }

    public function resolve(Request $request, Maintenance $maintenance)
    {
        $request->validate([
            'solution' => 'required|string',
            'date_resolution' => 'required|date',
        ]);

        $maintenance->update([
            'statut' => 'resolu',
            'solution' => $request->solution,
            'date_resolution' => $request->date_resolution,
        ]);

        // Remettre panneau en libre
        $maintenance->panel->update(['status' => 'libre']);

        AlertService::create(
            'maintenance',
            'info',
            '✅ Panne résolue — ' . $maintenance->panel->reference,
            auth()->user()->name . ' a résolu la panne sur ' . $maintenance->panel->reference . '.',
            $maintenance
        );
        return back()->with('success', 'Maintenance résolue ! Panneau remis en service. ✅');
    }
}
