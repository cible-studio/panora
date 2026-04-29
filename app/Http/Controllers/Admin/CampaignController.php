<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use App\Services\AvailabilityService;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\Reservation;
use App\Enums\CampaignStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService     $campaignService,
        protected AvailabilityService $availability
    ) {}

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $query = Campaign::with(['client', 'user'])
            ->withCount(['panels', 'invoices'])
            ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%$s%"))
            ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
            ->when($request->status,      fn($q, $s)  => $q->where('status', $s))
            ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
            ->when($request->non_facturee,fn($q)       => $q->nonFacturees())
            ->when($request->commune_id,  function($q) use ($request) {
                $q->whereHas('panels', function($p) use ($request) {
                    $p->where('commune_id', $request->commune_id);
                });
            })
            ->when($request->zone_id,     function($q) use ($request) {
                $q->whereHas('panels', function($p) use ($request) {
                    $p->where('zone_id', $request->zone_id);
                });
            })
            ->orderByDesc('created_at');

        $communes = \App\Models\Commune::orderBy('name')->get(['id', 'name']);
        $zones    = \App\Models\Zone::orderBy('name')->get(['id', 'name']);

        $campaigns = $query->paginate(20)->withQueryString();

        $rawCounts = Campaign::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $counts = [
            'actif'   => $rawCounts['actif']   ?? 0,
            'pose'    => $rawCounts['pose']    ?? 0,
            'termine' => $rawCounts['termine'] ?? 0,
            'annule'  => $rawCounts['annule']  ?? 0,
        ];

        $nonFactureesCount = Campaign::nonFacturees()->count();
        $endingSoonCount   = Campaign::where('status', 'actif')
            ->where('end_date', '>=', now()->startOfDay())
            ->where('end_date', '<=', now()->addDays(14)->endOfDay())
            ->count();

        $clients = Client::orderBy('name')->get();

        if ($request->ajax()) {
            // Recharger les campagnes avec les filtres appliqués
            $campaigns = $query->paginate(20)->withQueryString();
            
            return response()->json([
                'html'       => view('admin.campaigns.partials.table-rows', compact('campaigns'))->render(),
                'pagination' => $campaigns->links('pagination::bootstrap-4')->render(),
                'stats'      => ['total' => $campaigns->total()],
            ]);
        }

        return view('admin.campaigns.index', compact(
            'campaigns', 'counts', 'nonFactureesCount', 'endingSoonCount', 'clients', 'communes', 'zones'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════
    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'client', 'user', 'updatedBy',
            'reservation',
            'panels.commune', 'panels.format',
            'invoices',
        ]);

        $user = auth()->user();

        $can = [
            'update'       => $user->can('update', $campaign),
            'updateStatus' => $user->can('updateStatus', $campaign),
            'managePanel'  => $user->can('managePanel', $campaign)
                           && in_array($campaign->status->value, ['actif', 'pose']),
            'delete'       => $user->can('delete', $campaign),
        ];

        // Panneaux disponibles pour ajout — source de vérité via AvailabilityService
        $availablePanels = collect();
        if ($can['managePanel']) {
            $availablePanels = $this->availability
                ->getAvailablePanels(
                    $campaign->start_date->format('Y-m-d'),
                    $campaign->end_date->format('Y-m-d'),
                    $campaign->reservation_id
                )
                ->filter(fn($p) => !$campaign->panels->contains('id', $p->id))
                ->take(200)
                ->values();
        }

        $communes = Commune::orderBy('name')->get();
        $formats  = PanelFormat::orderBy('name')->get();
        $allowed  = $campaign->status->allowedTransitionsLabels();

        $daysLeft  = $campaign->daysRemaining();
        $humanTime = $campaign->humanTimeRemaining();
        $pct       = $campaign->progressPercent();

        return view('admin.campaigns.show', compact(
            'campaign', 'can', 'availablePanels',
            'communes', 'formats', 'allowed',
            'daysLeft', 'humanTime', 'pct'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE / STORE
    // ══════════════════════════════════════════════════════════════
    public function create(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $clients      = Client::orderBy('name')->get();
        $reservations = Reservation::with('client', 'panels')
            ->where('status', 'confirme')
            ->whereDoesntHave('campaign')
            ->get();

        $preselectedReservation = null;
        if ($request->filled('reservation_id')) {
            $preselectedReservation = Reservation::with(['client', 'panels'])
                ->where('status', 'confirme')
                ->whereDoesntHave('campaign')
                ->find($request->reservation_id);
        }

        return view('admin.campaigns.create',
            compact('clients', 'reservations', 'preselectedReservation'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns')->where('client_id', $request->client_id),
            ],
            'client_id'      => 'required|exists:clients,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after:start_date',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $client = Client::withTrashed()->findOrFail($data['client_id']);
        if ($client->trashed()) {
            return back()->withInput()->with('error',
                'Impossible de créer une campagne pour un client supprimé.');
        }

        try {
            $campaign = DB::transaction(function () use ($data) {
                $data['status']  = CampaignStatus::ACTIF->value;
                $data['user_id'] = auth()->id();

                $reservation = null;
                if (!empty($data['reservation_id'])) {
                    $reservation = Reservation::with('panels')
                        ->findOrFail($data['reservation_id']);

                    if ($reservation->campaign()->exists()) {
                        throw new \Exception('Cette réservation est déjà liée à une campagne.');
                    }
                    if ($reservation->client_id !== (int)$data['client_id']) {
                        throw new \Exception('Le client ne correspond pas à celui de la réservation.');
                    }

                    $data['total_panels'] = $reservation->panels->count();
                    $data['total_amount'] = $reservation->total_amount;
                    $data['start_date']   ??= $reservation->start_date;
                    $data['end_date']     ??= $reservation->end_date;
                }

                $campaign = Campaign::create($data);

                if ($reservation !== null) {
                    $campaign->panels()->sync($reservation->panels->pluck('id'));
                }

                Log::info('campaign.created', [
                    'campaign_id'    => $campaign->id,
                    'user_id'        => auth()->id(),
                    'client_id'      => $campaign->client_id,
                    'with_reservation'=> $reservation !== null,
                ]);

                return $campaign;
            });

            return redirect()
                ->route('admin.campaigns.show', $campaign)
                ->with('success', "Campagne « {$campaign->name} » créée avec succès.");

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════
    // EDIT / UPDATE
    // ══════════════════════════════════════════════════════════════
    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $clients = Client::orderBy('name')->get();
        return view('admin.campaigns.edit', compact('campaign', 'clients'));
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE — Mise à jour avec gestion intelligente du statut
    // ══════════════════════════════════════════════════════════════
    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        // 1. Récupération des données avec gestion spéciale pour start_date
        $rules = [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns', 'name')
                    ->where('client_id', $request->client_id)
                    ->ignore($campaign->id),
            ],
            'client_id'  => 'required|exists:clients,id',
            'end_date'   => 'required|date|after:start_date',
            'notes'      => 'nullable|string|max:2000',
        ];

        // Pour les campagnes actives, start_date est optionnel (vient du champ caché)
        // Pour les autres, il est requis
        if ($campaign->status !== CampaignStatus::ACTIF) {
            $rules['start_date'] = 'required|date';
        } else {
            $rules['start_date'] = 'nullable|date';
        }

        $data = $request->validate($rules);

        // 2. Gestion de la start_date pour les campagnes actives
        if ($campaign->status === CampaignStatus::ACTIF) {
            // Utiliser la start_date existante si non fournie
            if (empty($data['start_date'])) {
                $data['start_date'] = $campaign->start_date->format('Y-m-d');
            }
        }

        $today = now()->startOfDay();
        $newStart = \Carbon\Carbon::parse($data['start_date']);
        $newEnd = \Carbon\Carbon::parse($data['end_date']);

        // Vérifications supplémentaires
        if ($newEnd->lte($newStart)) {
            return back()->withInput()->with('error', 
                '❌ La date de fin doit être postérieure à la date de début.'
            );
        }

        // === RÈGLES MÉTIER ===
        if ($campaign->status === CampaignStatus::ACTIF) {
            // Vérifier que start_date n'a pas changé
            if (!$campaign->start_date->isSameDay($newStart)) {
                return back()->withInput()->with('error', 
                    '❌ Une campagne active ne peut pas voir sa date de début modifiée. ' .
                    'La campagne a déjà commencé le ' . $campaign->start_date->format('d/m/Y') . '.'
                );
            }

            // Vérifier que la nouvelle end_date n'est pas avant aujourd'hui
            if ($newEnd->lt($today)) {
                $data['status'] = CampaignStatus::TERMINE->value;
            } else {
                $data['status'] = CampaignStatus::ACTIF->value;
            }
        } 
        elseif ($campaign->status === CampaignStatus::PLANIFIE) {
            // Planifiée : tout modifiable, le statut sera recalculé après
        } 
        elseif (in_array($campaign->status->value, ['pose', 'termine', 'annule'])) {
            return back()->withInput()->with('error', 
                "❌ Une campagne « {$campaign->status->label()} » ne peut pas être modifiée. " .
                "Seules les campagnes Planifiées ou Actives sont modifiables."
            );
        }

        // 3. Mise à jour des données
        $data['updated_by'] = auth()->id();
        
        // Sauvegarde des anciennes valeurs
        $oldStart = $campaign->start_date;
        $oldEnd = $campaign->end_date;
        $oldStatus = $campaign->status;

        $campaign->update($data);

        // 4. Recalcul automatique du statut pour les campagnes planifiées
        if ($oldStatus === CampaignStatus::PLANIFIE) {
            $campaign->refresh();
            $newStatus = $this->calculateStatus($campaign);
            
            if ($newStatus !== $campaign->status->value) {
                $campaign->status = $newStatus;
                $campaign->save();
                
                Log::info('campaign.status.auto_updated', [
                    'campaign_id' => $campaign->id,
                    'old_status'  => $oldStatus->value,
                    'new_status'  => $newStatus,
                    'reason'      => 'dates_modified',
                    'user_id'     => auth()->id(),
                ]);
            }
        }

        // 5. Log de l'opération
        Log::info('campaign.updated', [
            'campaign_id' => $campaign->id,
            'user_id'     => auth()->id(),
            'changes'     => [
                'start_date' => [
                    'old' => $oldStart->format('Y-m-d'),
                    'new' => $campaign->start_date->format('Y-m-d')
                ],
                'end_date' => [
                    'old' => $oldEnd->format('Y-m-d'),
                    'new' => $campaign->end_date->format('Y-m-d')
                ],
                'status' => [
                    'old' => $oldStatus->label(),
                    'new' => $campaign->status->label()
                ]
            ]
        ]);

        // 6. Message de succès personnalisé
        $message = "✅ Campagne « {$campaign->name} » mise à jour avec succès.";
        
        if ($oldStatus === CampaignStatus::PLANIFIE && $campaign->status === CampaignStatus::ACTIF) {
            $message .= " La campagne est maintenant active.";
        } elseif ($campaign->status === CampaignStatus::TERMINE && $newEnd->lt($today)) {
            $message .= " La campagne a été automatiquement marquée comme terminée.";
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', $message);
    }

    /**
     * Calcule le statut d'une campagne en fonction de ses dates
     * 
     * @param Campaign $campaign
     * @return string
     */
    protected function calculateStatus(Campaign $campaign): string
    {
        $today = now()->startOfDay();
        $start = $campaign->start_date->startOfDay();
        $end   = $campaign->end_date->startOfDay();

        if ($start > $today) {
            return CampaignStatus::PLANIFIE->value;
        }
        
        if ($start <= $today && $end > $today) {
            return CampaignStatus::ACTIF->value;
        }
        
        if ($end <= $today) {
            return CampaignStatus::TERMINE->value;
        }
        
        return $campaign->status->value;
    }

    // ══════════════════════════════════════════════════════════════
    // ADD PANEL — délégué entièrement à CampaignService
    // ══════════════════════════════════════════════════════════════
    public function addPanel(Request $request, Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        $request->validate([
            'panel_ids'   => 'required|array|min:1|max:50',
            'panel_ids.*' => 'required|integer|exists:panels,id',
        ]);

        if (!in_array($campaign->status->value, ['actif', 'pose'])) {
            return back()->with('error',
                'Impossible d\'ajouter des panneaux à une campagne terminée ou annulée.');
        }

        // Toute la logique métier est dans le service
        $result = $this->campaignService->addPanels($campaign, $request->panel_ids);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success',
            ($result['added'] ?? count($request->panel_ids)) . ' panneau(x) ajouté(s). Montant recalculé.');
    }

    // ══════════════════════════════════════════════════════════════
    // REMOVE PANEL — délégué à CampaignService
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel)
    {
        $this->authorize('managePanel', $campaign);

        $result = $this->campaignService->removePanel($campaign, $panel);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        $msg = "Panneau {$panel->reference} retiré.";

        if (isset($result['warning'])) {
            $msg .= ' ⚠️ ' . $result['warning'];
            return redirect()
                ->route('admin.campaigns.index')
                ->with('warning', $msg);
        }

        return back()->with('success', $msg . ' Montant recalculé.');
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY — délégué à CampaignService
    // ══════════════════════════════════════════════════════════════
    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $result = $this->campaignService->delete($campaign);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campagne supprimée définitivement.');
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE STATUS — cascade si annulation
    // ══════════════════════════════════════════════════════════════

    public function updateStatus(Request $request, Campaign $campaign)
    {
        $this->authorize('updateStatus', $campaign);

        $request->validate([
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ]);

        $newStatus = CampaignStatus::from($request->status);

        if (!$campaign->status->canTransitionTo($newStatus)) {
            return back()->with('error',
                "Transition interdite : {$campaign->status->label()} → {$newStatus->label()}.");
        }

        // ── Routage selon le nouveau statut ──────────────────────────
        // ANNULE  → cancel()    : libère panneaux + annule réservation
        // TERMINE → terminate() : libère panneaux + marque réservation terminée
        // Autres  → update()    : simple changement de statut (ex: actif → pose)

        if ($newStatus === CampaignStatus::ANNULE) {
            $this->campaignService->cancel($campaign, 'Annulation manuelle par ' . auth()->user()?->name);
        } elseif ($newStatus === CampaignStatus::TERMINE) {
            $this->campaignService->terminate($campaign, 'Clôture manuelle par ' . auth()->user()?->name);
        } else {
            // Transition simple : pose, actif, etc.
            $campaign->update([
                'status'     => $newStatus->value,
                'updated_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', "Statut mis à jour : {$newStatus->label()}.");
    }

    // ══════════════════════════════════════════════════════════════
    // PROLONGER
    // ══════════════════════════════════════════════════════════════
    public function prolonger(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status === CampaignStatus::ANNULE) {
            return back()->with('error', 'Impossible de prolonger une campagne annulée.');
        }

        $request->validate([
            'new_end_date' => [
                'required', 'date',
                'after:' . $campaign->end_date->format('Y-m-d'),
            ],
        ]);

        // Vérifier conflits sur la période étendue (ancienne fin → nouvelle fin)
        $panelIds  = $campaign->panels->pluck('id')->toArray();
        $conflicts = $this->availability->getUnavailablePanelIds(
            $panelIds,
            $campaign->end_date->format('Y-m-d'),
            $request->new_end_date,
            $campaign->reservation_id
        );

        if (!empty($conflicts)) {
            $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
            return back()->with('error',
                "Impossible de prolonger — conflits sur la nouvelle période : {$refs}");
        }

        $oldEnd = $campaign->end_date->format('d/m/Y');
        $newEnd = \Carbon\Carbon::parse($request->new_end_date)->format('d/m/Y');

        DB::transaction(function () use ($campaign, $request) {
            $campaign->update([
                'end_date'   => $request->new_end_date,
                'status'     => CampaignStatus::ACTIF->value,
                'updated_by' => auth()->id(),
            ]);

            // Prolonger aussi la réservation liée
            if ($campaign->reservation) {
                $campaign->reservation->update(['end_date' => $request->new_end_date]);
            }

            // Recalculer le montant avec la nouvelle durée
            $this->campaignService->recalculateCampaignAmount($campaign->fresh());
        });

        Log::info('campaign.prolonged', [
            'campaign_id'  => $campaign->id,
            'old_end_date' => $oldEnd,
            'new_end_date' => $newEnd,
            'user_id'      => auth()->id(),
        ]);

        return back()->with('success',
            "Campagne prolongée jusqu'au {$newEnd}. Montant recalculé.");
    }
}