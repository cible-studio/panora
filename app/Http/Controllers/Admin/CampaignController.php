<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\Reservation;
use App\Enums\CampaignStatus;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // INDEX — optimisé 1M de campagnes
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        // Eager load minimal + withCount pour éviter N+1 et chargement mémoire
        $campaigns = Campaign::with(['client', 'user'])
            ->withCount(['panels', 'invoices'])
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%$s%")
            )
            ->when($request->client_id, fn($q, $id) => $q->where('client_id', $id))
            ->when($request->status,    fn($q, $s)  => $q->where('status', $s))
            ->when($request->date_from, fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,   fn($q, $d)  => $q->where('end_date', '<=', $d))
            ->when($request->non_facturee, fn($q) =>
                $q->whereIn('status', ['actif', 'pose', 'termine'])
                  ->doesntHave('invoices')
            )
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Counts par statut — 1 requête agrégée
        $rawCounts = Campaign::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'actif'   => $rawCounts['actif']   ?? 0,
            'pose'    => $rawCounts['pose']     ?? 0,
            'termine' => $rawCounts['termine']  ?? 0,
            'annule'  => $rawCounts['annule']   ?? 0,
        ];

        // Non facturées — requête directe avec index
        $nonFactureesCount = Campaign::nonFacturees()->count();

        // Alertes fin proche — campagnes actives se terminant dans 14 jours
        $endingSoonCount = Campaign::where('status', CampaignStatus::ACTIF->value)
            ->where('end_date', '>=', now()->startOfDay())
            ->where('end_date', '<=', now()->addDays(14)->endOfDay())
            ->count();

        $clients = Client::orderBy('name')->get();

        return view('admin.campaigns.index', compact(
            'campaigns', 'counts', 'nonFactureesCount', 'endingSoonCount', 'clients'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════
    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'client',
            'user',
            'updatedBy',
            'reservation',
            'panels.commune',
            'panels.format',
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

        // Panneaux disponibles pour ajout — limités à 200
        $availablePanels = collect();
        if ($can['managePanel']) {
            $availablePanels = app(AvailabilityService::class)
                ->getAvailablePanels(
                    $campaign->start_date->format('Y-m-d'),
                    $campaign->end_date->format('Y-m-d'),
                    $campaign->reservation_id
                )
                ->filter(fn($p) =>
                    !$campaign->panels->contains('id', $p->id)
                    && $p->status->value === 'libre'  // uniquement libres
                )
                ->take(200)
                ->values();
        }

        $communes = Commune::orderBy('name')->get();
        $formats  = PanelFormat::orderBy('name')->get();

        // Transitions lisibles
        $transitions = [
            'actif'   => [
                'termine' => 'Terminer la campagne',
                'annule'  => 'Annuler la campagne',
            ],
            'pose'    => [
                'actif'   => 'Remettre en cours',
                'termine' => 'Terminer la campagne',
                'annule'  => 'Annuler la campagne',
            ],
            'termine' => [],
            'annule'  => [],
        ];
        $allowed = $transitions[$campaign->status->value] ?? [];

        return view('admin.campaigns.show', compact(
            'campaign', 'can', 'availablePanels',
            'communes', 'formats', 'allowed'
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
                        throw new \Exception(
                            'Cette réservation est déjà liée à une campagne.');
                    }
                    if ($reservation->client_id !== (int)$data['client_id']) {
                        throw new \Exception(
                            'Le client ne correspond pas à celui de la réservation.');
                    }

                    $data['total_panels'] = $reservation->panels->count();
                    $data['total_amount'] = $reservation->total_amount;
                    $data['start_date'] ??= $reservation->start_date;
                    $data['end_date']   ??= $reservation->end_date;
                }

                $campaign = Campaign::create($data);

                if ($reservation !== null) {
                    $campaign->panels()->sync($reservation->panels->pluck('id'));
                }

                Log::info('campaign.created', [
                    'campaign_id' => $campaign->id,
                    'user_id'     => auth()->id(),
                    'client_id'   => $campaign->client_id,
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

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns', 'name')
                    ->where('client_id', $request->client_id)
                    ->ignore($campaign->id),
            ],
            'client_id'  => 'required|exists:clients,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'notes'      => 'nullable|string|max:2000',
        ]);

        $data['updated_by'] = auth()->id();
        $campaign->update($data);

        Log::info('campaign.updated', [
            'campaign_id' => $campaign->id,
            'user_id'     => auth()->id(),
        ]);

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'Campagne mise à jour.');
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE STATUS
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

        $oldStatus = $campaign->status;

        $campaign->update([
            'status'     => $newStatus->value,
            'updated_by' => auth()->id(),
        ]);

        // Libérer panneaux si annulée
        if ($newStatus === CampaignStatus::ANNULE) {
            $panelIds = $campaign->panels->pluck('id')->toArray();
            if (!empty($panelIds)) {
                app(AvailabilityService::class)->syncPanelStatuses($panelIds);
            }
        }

        Log::info('campaign.status_changed', [
            'campaign_id' => $campaign->id,
            'from'        => $oldStatus->value,
            'to'          => $newStatus->value,
            'user_id'     => auth()->id(),
            'ip'          => request()->ip(),
        ]);

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', "Statut mis à jour : {$newStatus->label()}.");
    }

    // ══════════════════════════════════════════════════════════════
    // ADD PANEL — bloque les non-libres + recalcule le prix
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

        $panelIds = $request->panel_ids;

        // Doublons dans la campagne
        $alreadyIn = $campaign->panels->pluck('id')
            ->intersect($panelIds)->toArray();
        if (!empty($alreadyIn)) {
            $refs = Panel::whereIn('id', $alreadyIn)->pluck('reference')->join(', ');
            return back()->with('error',
                "Ces panneaux sont déjà dans cette campagne : {$refs}");
        }

        // Bloquer panneaux non libres
        $nonLibres = Panel::whereIn('id', $panelIds)
            ->where('status', '!=', 'libre')
            ->get();
        if ($nonLibres->isNotEmpty()) {
            $refs = $nonLibres->map(fn($p) =>
                "{$p->reference} ({$p->status->label()})"
            )->join(', ');
            return back()->with('error',
                "Panneaux non disponibles (statut ≠ libre) : {$refs}");
        }

        // Vérifier disponibilité sur la période
        $conflicts = app(AvailabilityService::class)->getUnavailablePanelIds(
            $panelIds,
            $campaign->start_date->format('Y-m-d'),
            $campaign->end_date->format('Y-m-d'),
            $campaign->reservation_id
        );

        if (!empty($conflicts)) {
            $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
            return back()->with('error',
                "Panneaux non disponibles sur cette période : {$refs}");
        }

        // Attacher + recalculer prix total
        $months    = $campaign->durationInMonths();
        $panelData = Panel::whereIn('id', $panelIds)->get()->keyBy('id');

        DB::transaction(function () use ($campaign, $panelIds, $panelData, $months) {
            $campaign->panels()->syncWithoutDetaching($panelIds);

            // Recalculer le montant total de la campagne
            $newTotal = $campaign->panels()->get()->sum(fn($p) =>
                (float)($p->monthly_rate ?? 0) * $months
            );

            $campaign->update([
                'total_panels' => $campaign->panels()->count(),
                'total_amount' => $newTotal,
                'updated_by'   => auth()->id(),
            ]);

            // Sync statut des panneaux ajoutés → confirme
            app(AvailabilityService::class)->syncPanelStatuses($panelIds);
        });

        Log::info('campaign.panels_added', [
            'campaign_id' => $campaign->id,
            'panel_ids'   => $panelIds,
            'user_id'     => auth()->id(),
        ]);

        return back()->with('success',
            count($panelIds) . ' panneau(x) ajouté(s). Montant recalculé.');
    }

    // ══════════════════════════════════════════════════════════════
    // REMOVE PANEL
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel)
    {
        $this->authorize('managePanel', $campaign);

        if (!in_array($campaign->status->value, ['actif', 'pose'])) {
            return back()->with('error',
                'Impossible de modifier les panneaux d\'une campagne terminée ou annulée.');
        }

        $months = $campaign->durationInMonths();

        DB::transaction(function () use ($campaign, $panel, $months) {
            $campaign->panels()->detach($panel->id);

            $newTotal = $campaign->panels()->get()->sum(fn($p) =>
                (float)($p->monthly_rate ?? 0) * $months
            );

            $campaign->update([
                'total_panels' => $campaign->panels()->count(),
                'total_amount' => $newTotal,
                'updated_by'   => auth()->id(),
            ]);

            // Recalculer statut panneau
            $stillActive = \App\Models\ReservationPanel::where('panel_id', $panel->id)
                ->whereHas('reservation', fn($q) =>
                    $q->whereIn('status', ['en_attente', 'confirme'])
                      ->where('end_date', '>=', now()->toDateString())
                )
                ->exists();

            if (!$stillActive) {
                $panel->update(['status' => \App\Enums\PanelStatus::LIBRE->value]);
            }
        });

        Log::info('campaign.panel_removed', [
            'campaign_id' => $campaign->id,
            'panel_id'    => $panel->id,
            'user_id'     => auth()->id(),
        ]);

        return back()->with('success',
            "Panneau {$panel->reference} retiré. Montant recalculé.");
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════
    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $panelIds = $campaign->panels->pluck('id')->toArray();

        DB::transaction(function () use ($campaign, $panelIds) {
            $campaign->delete();
            if (!empty($panelIds)) {
                app(AvailabilityService::class)->syncPanelStatuses($panelIds);
            }
        });

        Log::info('campaign.deleted', [
            'campaign_id'  => $campaign->id,
            'panels_freed' => count($panelIds),
            'user_id'      => auth()->id(),
        ]);

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campagne supprimée. ' . count($panelIds) . ' panneau(x) libéré(s).');
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

        $oldEnd = $campaign->end_date->format('d/m/Y');
        $newEnd = \Carbon\Carbon::parse($request->new_end_date)->format('d/m/Y');

        DB::transaction(function () use ($campaign, $request) {
            $campaign->update([
                'end_date'   => $request->new_end_date,
                'status'     => CampaignStatus::ACTIF->value,
                'updated_by' => auth()->id(),
            ]);

            if ($campaign->reservation) {
                $campaign->reservation->update(['end_date' => $request->new_end_date]);
            }

            // Recalculer le montant avec la nouvelle durée
            $months   = $campaign->fresh()->durationInMonths();
            $newTotal = $campaign->panels()->get()->sum(fn($p) =>
                (float)($p->monthly_rate ?? 0) * $months
            );
            $campaign->update(['total_amount' => $newTotal]);
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