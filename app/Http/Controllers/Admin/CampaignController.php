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
            ->orderByDesc('created_at');

        $campaigns = $query->paginate(20)->withQueryString();

        $rawCounts = Campaign::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $counts = [
            'actif'   => $rawCounts['actif']   ?? 0,
            'pose'    => $rawCounts['pose']     ?? 0,
            'termine' => $rawCounts['termine']  ?? 0,
            'annule'  => $rawCounts['annule']   ?? 0,
        ];

        $nonFactureesCount = Campaign::nonFacturees()->count();
        $endingSoonCount   = Campaign::where('status', 'actif')
            ->where('end_date', '>=', now()->startOfDay())
            ->where('end_date', '<=', now()->addDays(14)->endOfDay())
            ->count();

        $clients = Client::orderBy('name')->get();

        if ($request->ajax()) {
            return response()->json([
                'html'       => view('admin.campaigns.partials.table-rows', compact('campaigns'))->render(),
                'pagination' => $campaigns->links()->render(),
                'stats'      => ['total' => $campaigns->total()],
            ]);
        }

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

        if ($newStatus === CampaignStatus::ANNULE) {
            $this->campaignService->cancel($campaign, 'Annulation manuelle');
        } else {
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