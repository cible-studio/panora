<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Panel;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AlertService;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Maintenance::with('panel', 'technicien', 'signaledBy');

        // Filtres "neutres" appliqués au périmètre (et donc aux compteurs)
        if ($request->filled('search')) {
            $query->whereHas('panel', function ($q) use ($request) {
                $q->where('reference', 'like', '%' . $request->search . '%')
                    ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        // ─── COMPTEURS sur le périmètre AVANT filtre statut/priorite ───
        // Permet aux 4 cartes de garder leur valeur réelle quand on en clique une.
        $countsRaw = (clone $query)
            ->setEagerLoads([])
            ->reorder()
            ->select('statut', 'priorite', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
            ->groupBy('statut', 'priorite')
            ->get();

        $totalSignales = (int) $countsRaw->where('statut', 'signale')->sum('total');
        $totalEnCours  = (int) $countsRaw->where('statut', 'en_cours')->sum('total');
        $totalResolus  = (int) $countsRaw->where('statut', 'resolu')->sum('total');
        // Urgentes = priorité urgente ET statut non résolu/annulé (pour signaler les vraies urgences)
        $totalUrgentes = (int) $countsRaw
            ->where('priorite', 'urgente')
            ->whereNotIn('statut', ['resolu', 'annule'])
            ->sum('total');

        // ─── Filtres KPI/select appliqués APRÈS le calcul des compteurs ───
        if ($request->filled('statut'))   $query->where('statut', $request->statut);
        if ($request->filled('priorite')) $query->where('priorite', $request->priorite);

        $maintenances = $query
            ->orderByRaw("FIELD(priorite, 'urgente','haute','normale','faible')")
            ->orderByRaw("FIELD(statut, 'signale','en_cours','resolu','annule')")
            ->paginate(15)
            ->withQueryString();

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

        $oldStatut = $maintenance->statut;
        $maintenance->update($request->all());

        // BUG FIX — Si on bascule vers "resolu" ou "annule" depuis l'édit
        // libre, on doit aussi remettre le panneau en service. Sinon le
        // panneau garde son statut "maintenance" alors que la fiche dit
        // que tout est résolu : il reste invisible dans dispos / inventaire.
        if (in_array($maintenance->statut, ['resolu', 'annule'], true)
            && $oldStatut !== $maintenance->statut) {
            $this->releasePanelFromMaintenance($maintenance->panel);
        }

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
        // Sécurité : si le panneau était en maintenance à cause de CETTE
        // maintenance précisément et qu'il n'y en a plus d'active, on
        // libère le panneau pour qu'il redevienne disponible.
        $panel = $maintenance->panel;
        $maintenance->delete();

        if ($panel && $panel->status->value === 'maintenance') {
            $hasOther = Maintenance::where('panel_id', $panel->id)
                ->whereNotIn('statut', ['resolu', 'annule'])
                ->whereKeyNot($maintenance->id)
                ->exists();
            if (!$hasOther) {
                $this->releasePanelFromMaintenance($panel);
            }
        }

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

        // Remet le panneau en service en respectant les éventuelles
        // réservations / campagnes actives (libre / option / confirme /
        // occupé) — pas forcé en "libre".
        $this->releasePanelFromMaintenance($maintenance->panel);

        AlertService::create(
            'maintenance',
            'info',
            '✅ Panne résolue — ' . $maintenance->panel->reference,
            auth()->user()->name . ' a résolu la panne sur ' . $maintenance->panel->reference . '.',
            $maintenance
        );
        return back()->with('success', 'Maintenance résolue ! Panneau remis en service. ✅');
    }

    /**
     * Sort un panneau du statut "maintenance" et recalcule son statut
     * réel via AvailabilityService (libre / option / confirme / occupé)
     * en fonction des réservations et campagnes actives.
     *
     * Pourquoi pas un simple update(['status' => 'libre']) ?
     *   Si le panneau a une réservation en cours ou une campagne actif,
     *   il doit être marqué "confirme" / "occupe", pas "libre" → sinon
     *   les vues dispos / inventaire mentent à l'admin.
     *
     * AvailabilityService::syncPanelStatuses skip les panneaux déjà en
     * maintenance (sécurité côté lui), donc on passe d'abord à 'libre'
     * pour débloquer la sync, puis on appelle sync qui dérive le bon
     * statut depuis les bookings.
     */
    private function releasePanelFromMaintenance(?Panel $panel): void
    {
        if (!$panel) return;
        if ($panel->status->value !== 'maintenance') return;

        // Étape 1 : sortir le panneau de la maintenance (statut transitoire
        // 'libre' qui sera réajusté ensuite par la sync).
        $panel->update(['status' => 'libre']);

        // Étape 2 : laisser AvailabilityService recalculer le bon statut
        // en fonction des réservations / campagnes actives sur ce panneau.
        try {
            app(AvailabilityService::class)->syncPanelStatuses([$panel->id]);
        } catch (\Throwable $e) {
            Log::warning('maintenance.release.sync_failed', [
                'panel_id' => $panel->id,
                'error'    => $e->getMessage(),
            ]);
        }

        Log::info('maintenance.panel_released', [
            'panel_id'    => $panel->id,
            'panel_ref'   => $panel->reference,
            'new_status'  => $panel->fresh()?->status?->value,
            'released_by' => auth()->id(),
        ]);
    }
}
