<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Panel;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AlertService;
use App\Services\AvailabilityService;
use App\Services\MaintenanceNotifier;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    public function __construct(
        protected MaintenanceNotifier $notifier,
    ) {}

    public function index(Request $request)
    {
        $query = Maintenance::with('panel', 'technicien', 'signaledBy');

        // Filtres "neutres" appliqués au périmètre (et donc aux compteurs).
        // Recherche full-text étendue à zone_description + commune.name
        // (un fragment comme "ange" trouve "Boulv. de l'Angré" ou la
        //  commune Adjamé via leur join).
        if ($request->filled('search')) {
            $like = '%' . $request->search . '%';
            $query->whereHas('panel', function ($q) use ($like) {
                $q->where('reference', 'LIKE', $like)
                  ->orWhere('name', 'LIKE', $like)
                  ->orWhere('zone_description', 'LIKE', $like)
                  ->orWhereHas('commune', fn($qc) => $qc->where('name', 'LIKE', $like));
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
        // On ne charge plus tous les panneaux côté JS — la recherche se fait
        // désormais via AJAX searchPanels() avec debounce. Permet de scaler
        // sur des parcs de 1000+ panneaux sans charger un payload énorme.
        $techniciens = User::where('role', 'technique')->orderBy('name')->get(['id', 'name', 'whatsapp_number']);
        return view('admin.maintenances.create', compact('techniciens'));
    }

    /**
     * Recherche AJAX de panneaux pour le formulaire de création
     * maintenance. Match full-text sur reference, name, zone_description,
     * commune.name — fragments OK ("ange" trouve "Angré", "CDY" trouve
     * "CDY-001A").
     *
     * Retourne max 15 résultats triés par référence — assez pour une
     * dropdown utilisable sur mobile, et léger sur le réseau.
     */
    public function searchPanels(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . $q . '%';

        $panels = Panel::with(['commune:id,name'])
            ->where(function ($query) use ($like) {
                $query->where('reference', 'LIKE', $like)
                      ->orWhere('name', 'LIKE', $like)
                      ->orWhere('zone_description', 'LIKE', $like)
                      ->orWhereHas('commune', fn($q2) => $q2->where('name', 'LIKE', $like));
            })
            ->orderBy('reference')
            ->limit(15)
            ->get(['id', 'reference', 'name', 'commune_id', 'status'])
            ->map(fn($p) => [
                'id'       => $p->id,
                'ref'      => $p->reference,
                'name'     => $p->name,
                'commune'  => $p->commune?->name ?? '—',
                'status'   => $p->status->value,
            ]);

        return response()->json($panels);
    }

    /**
     * Création rapide d'un technicien depuis la modale du formulaire
     * maintenance — évite à l'admin d'aller dans le module Utilisateurs.
     *
     * Le technicien créé est immédiatement utilisable (rôle technique,
     * actif). Le mot de passe est généré aléatoirement — l'admin le
     * communique au tech via WhatsApp / SMS / appel. Le tech pourra le
     * changer à sa première connexion s'il en a besoin.
     */
    public function quickCreateTechnician(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'email'           => 'nullable|email|unique:users,email|max:150',
        ], [
            'email.unique' => 'Cet email est déjà utilisé.',
        ]);

        // Email auto-généré si non fourni — nécessaire car la table
        // users a email NOT NULL. Format : prenom.nom@tech.local
        if (empty($data['email'])) {
            $slug = \Illuminate\Support\Str::slug($data['name'], '.');
            $data['email'] = $slug . '.' . \Illuminate\Support\Str::random(4) . '@tech.local';
        }

        // Normalisation WhatsApp (idem UserController::store)
        $whatsapp = null;
        if (!empty($data['whatsapp_number'])) {
            $whatsapp = app(\App\Services\WhatsAppService::class)->normalizeNumber($data['whatsapp_number']);
        }

        $plainPassword = \Illuminate\Support\Str::random(10);

        $user = \App\Models\User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => \Illuminate\Support\Facades\Hash::make($plainPassword),
            'role'            => 'technique',
            'whatsapp_number' => $whatsapp,
            'agent_code'      => \App\Models\User::generateAgentCode('technique'),
            'is_active'       => true,
        ]);

        Log::info('maintenance.technician.quick_created', [
            'user_id'    => $user->id,
            'agent_code' => $user->agent_code,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'ok'              => true,
            'message'         => "Technicien {$user->name} créé ({$user->agent_code}).",
            'technician'      => [
                'id'         => $user->id,
                'name'       => $user->name,
                'agent_code' => $user->agent_code,
                'email'      => $user->email,
                'whatsapp'   => $user->whatsapp_number,
            ],
            'plain_password'  => $plainPassword, // affiché 1 fois à l'admin pour transmission au tech
        ]);
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
            'statut'      => $request->filled('technicien_id') ? 'en_cours' : 'signale',
        ]);

        // Mettre panneau en maintenance (l'AvailabilityService rendra le
        // statut "libre" automatiquement à la résolution / annulation).
        Panel::find($request->panel_id)->update(['status' => 'maintenance']);

        AlertService::create(
            'maintenance',
            'danger',
            '🔧 Panne signalée — ' . $maintenance->panel->reference,
            auth()->user()->name . ' a signalé une panne sur ' . $maintenance->panel->reference . ' : ' . $maintenance->type_panne . ' (priorité: ' . $maintenance->priorite . ').',
            $maintenance
        );

        // Notification immédiate au technicien si assigné dès la création.
        if ($maintenance->technicien_id) {
            $this->notifier->notifyAssigned($maintenance);
        }

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
        // Une fiche close est verrouillée : pour rouvrir, l'admin doit
        // utiliser le bouton "Rouvrir" qui crée une nouvelle maintenance
        // liée à celle-ci. Évite qu'on défasse une résolution validée.
        if ($maintenance->isLocked()) {
            return redirect()->route('admin.maintenances.show', $maintenance)
                ->with('error', 'Cette maintenance est verrouillée ('
                    . $maintenance->statut . '). Utilisez "Rouvrir" pour signaler une nouvelle panne.');
        }

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
        // Garde serveur : impossible de modifier une fiche close, même via
        // POST direct. La seule sortie est le bouton "Rouvrir".
        if ($maintenance->isLocked()) {
            return redirect()->route('admin.maintenances.show', $maintenance)
                ->with('error', 'Maintenance verrouillée — modification refusée.');
        }

        $request->validate([
            'type_panne'       => 'required|string|max:255',
            'priorite'         => 'required|in:faible,normale,haute,urgente',
            'statut'           => 'required|in:signale,en_cours,resolu,annule',
            'technicien_id'    => 'nullable|exists:users,id',
            'description'      => 'nullable|string',
            'solution'         => 'nullable|string',
            'date_resolution'  => 'nullable|date',
        ]);

        $oldStatut    = $maintenance->statut;
        $oldTechId    = $maintenance->technicien_id;
        $oldPriorite  = $maintenance->priorite;

        $maintenance->update($request->all());
        $maintenance->refresh();

        // Si on bascule vers "resolu" ou "annule" depuis l'édit libre,
        // remet le panneau en service via AvailabilityService.
        if (in_array($maintenance->statut, ['resolu', 'annule'], true)
            && $oldStatut !== $maintenance->statut) {
            $this->releasePanelFromMaintenance($maintenance->panel);

            if ($maintenance->statut === 'resolu') {
                $this->notifier->notifyResolved($maintenance);
                try {
                    $this->notifier->notifyClientCampaignBack($maintenance);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('maintenance.update_notify_client_back.failed', [
                        'maintenance_id' => $maintenance->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        } else {
            // Hors transition vers close : notif si changement technicien
            // ou si priorité a grimpé à haute/urgente.
            $newTechAssigned = $maintenance->technicien_id
                && $maintenance->technicien_id !== $oldTechId;
            if ($newTechAssigned) {
                $this->notifier->notifyAssigned($maintenance);
            } elseif ($oldPriorite !== $maintenance->priorite
                && in_array($maintenance->priorite, ['haute', 'urgente'], true)) {
                $this->notifier->notifyPriorityRaised($maintenance);
            } else {
                $this->notifier->notifyUpdated($maintenance);
            }
        }

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

    /**
     * Rouvre une maintenance fermée en créant une nouvelle ligne reliée à
     * la précédente via parent_maintenance_id. Le panneau repasse en
     * maintenance. On reprend le type de panne + le technicien à titre
     * indicatif — éditables par l'admin sur la nouvelle fiche.
     */
    public function reopen(Maintenance $maintenance)
    {
        if (!$maintenance->isLocked()) {
            return redirect()->route('admin.maintenances.show', $maintenance)
                ->with('error', 'Cette maintenance est encore ouverte — pas besoin de la rouvrir.');
        }

        $new = Maintenance::create([
            'panel_id'              => $maintenance->panel_id,
            'parent_maintenance_id' => $maintenance->id,
            'technicien_id'         => $maintenance->technicien_id,
            'signale_par'           => auth()->id(),
            'type_panne'            => $maintenance->type_panne,
            'priorite'              => $maintenance->priorite,
            'statut'                => $maintenance->technicien_id ? 'en_cours' : 'signale',
            'date_signalement'      => now()->toDateString(),
            'description'           => 'Récurrence de la maintenance #' . $maintenance->id
                . ($maintenance->solution ? ' (solution précédente : ' . $maintenance->solution . ')' : ''),
        ]);

        Panel::find($maintenance->panel_id)->update(['status' => 'maintenance']);

        AlertService::create(
            'maintenance',
            'warning',
            '🔄 Maintenance rouverte — ' . $new->panel->reference,
            auth()->user()->name . ' a rouvert la maintenance #' . $maintenance->id
                . ' (nouvelle fiche #' . $new->id . ').',
            $new
        );

        if ($new->technicien_id) {
            $this->notifier->notifyAssigned($new);
        }

        return redirect()->route('admin.maintenances.show', $new)
            ->with('success', 'Maintenance rouverte — nouvelle fiche #' . $new->id . ' créée.');
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
        // Garde : une maintenance déjà résolue ne se "re-résout" pas.
        if ($maintenance->isLocked()) {
            return back()->with('error', 'Cette maintenance est déjà clôturée.');
        }

        $request->validate([
            'solution'        => 'required|string',
            'date_resolution' => 'required|date',
        ]);

        $maintenance->update([
            'statut'          => 'resolu',
            'solution'        => $request->solution,
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

        $this->notifier->notifyResolved($maintenance);

        // Informe les clients des campagnes en cours que ce panneau est de
        // retour en ligne. Best-effort — ne casse pas la résolution si la
        // notif échoue.
        try {
            $this->notifier->notifyClientCampaignBack($maintenance);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('maintenance.notify_client_back.failed', [
                'maintenance_id' => $maintenance->id,
                'error'          => $e->getMessage(),
            ]);
        }

        // Warning : si des signalements terrain restent non traités sur ce
        // panneau (autres que celui qui a déclenché cette maintenance), on
        // alerte l'admin pour qu'il décide. Évite de remettre en service un
        // panneau dont d'autres problèmes ont été remontés et oubliés.
        $pendingSignals = \App\Models\PoseTaskAction::where('action', 'problem_reported')
            ->whereNull('resolved_at')
            ->whereHas('task', fn($q) => $q->where('panel_id', $maintenance->panel_id))
            ->count();

        if ($pendingSignals > 0) {
            return back()
                ->with('success', 'Maintenance résolue ! Panneau remis en service. ✅')
                ->with('warning', "⚠️ Attention : {$pendingSignals} signalement(s) terrain non traité(s) sur ce panneau — vérifie sur l'écran Signalements avant de le considérer comme OK.");
        }

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
