<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\CampaignService;
use App\Services\AvailabilityService;
use App\Services\AlertService;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\Reservation;
use App\Models\Zone;

use App\Enums\CampaignStatus;
use App\Exports\CampaignsExport;
use App\Support\PdfAssets;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CampaignController extends Controller
{
    use PdfAssets;

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
            ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%{$s}%"))
            ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
            ->when($request->status,      fn($q, $s)  => $q->where('status', $s))
            // Filtres date originaux : date_from (start) / date_to (end)
            ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
            // T12 : Filtre période personnalisée (start_date BETWEEN date_debut AND date_fin)
            ->when($request->date_debut,  fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_fin,    fn($q, $d)  => $q->where('start_date', '<=', $d))
            ->when($request->non_facturee, fn($q)     => $q->nonFacturees())
            ->when($request->commune_id,  fn($q, $id) => $q->whereHas('panels', fn($p) => $p->where('commune_id', $id)))
            ->when($request->zone_id,     fn($q, $id) => $q->whereHas('panels', fn($p) => $p->where('zone_id', $id)))
            ->orderByDesc('created_at');

        $campaigns = $query->paginate(20)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'html'       => view('admin.campaigns.partials.table-rows', compact('campaigns'))->render(),
                'pagination' => $campaigns->links('pagination::bootstrap-4')->render(),
                'stats'      => ['total' => $campaigns->total()],
            ]);
        }

        $rawCounts = Campaign::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        $counts = [
            'planifie' => $rawCounts['planifie'] ?? 0,
            'actif'    => $rawCounts['actif']    ?? 0,
            'pose'     => $rawCounts['pose']     ?? 0,
            'termine'  => $rawCounts['termine']  ?? 0,
            'annule'   => $rawCounts['annule']   ?? 0,
        ];

        $nonFactureesCount = Campaign::nonFacturees()->count();
        $endingSoonCount   = Campaign::endingSoon(14)->count();

        $clients  = Client::orderBy('name')->get(['id', 'name']);
        $communes = Commune::orderBy('name')->get(['id', 'name']);
        $zones    = Zone::orderBy('name')->get(['id', 'name']);

        return view('admin.campaigns.index', compact(
            'campaigns', 'counts', 'nonFactureesCount', 'endingSoonCount',
            'clients', 'communes', 'zones'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════
    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        // Synchroniser le statut avec les dates (idempotent, gratuit si déjà à jour)
        $campaign->syncStatusWithDates();

        $campaign->load([
            'client', 'user', 'updatedBy',
            'reservation:id,reference,status,start_date,end_date',
            'panels.commune:id,name',
            'panels.format:id,name',
            'invoices:id,campaign_id,reference,amount_ttc',
        ]);

        $user = auth()->user();
        $canManagePanel = $user->can('managePanel', $campaign)
            && in_array($campaign->status->value, ['planifie', 'actif', 'pose']);

        $can = [
            'update'       => $user->can('update', $campaign),
            'updateStatus' => $user->can('updateStatus', $campaign),
            'managePanel'  => $canManagePanel,
            'delete'       => $user->can('delete', $campaign),
        ];

        $allowed = $campaign->status->allowedTransitionsLabels();

        // Panneaux disponibles : chargés en AJAX à l'ouverture du modal (cf. méthode availablePanels())
        // pour ne pas pénaliser le rendu initial de la page.

        return view('admin.campaigns.show', compact('campaign', 'can', 'allowed'));
    }

    /**
     * Endpoint JSON léger pour rafraîchir la progression sans recharger la page.
     * Appelé par le JS toutes les 60 secondes sur la page show.
     */
    public function progress(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        // Sync silencieuse — si end_date est passée, le statut bascule en TERMINE
        $changed = $campaign->syncStatusWithDates();

        return response()->json([
            'pct'         => $campaign->progressPercent(),
            'days_left'   => $campaign->daysRemaining(),
            'human_time'  => $campaign->humanTimeRemaining(),
            'status'      => $campaign->status->value,
            'status_label'=> $campaign->status->label(),
            'ending_soon' => $campaign->isEndingSoon(),
            'is_running'  => in_array($campaign->status, [CampaignStatus::ACTIF, CampaignStatus::POSE]),
            'reload'      => $changed, // Frontend recharge la page si statut a changé
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Endpoint JSON pour charger les panneaux disponibles à l'ouverture du modal d'ajout.
     * Évite de précharger 200 panneaux à chaque rendu de la page show.
     */
    public function availablePanels(Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        if (!in_array($campaign->status->value, ['planifie', 'actif', 'pose'])) {
            return response()->json(['panels' => []]);
        }

        $existingIds = $campaign->panels()->pluck('panels.id')->all();

        $panels = $this->availability
            ->getAvailablePanels(
                $campaign->start_date->format('Y-m-d'),
                $campaign->end_date->format('Y-m-d'),
                $campaign->reservation_id
            )
            ->reject(fn($p) => in_array($p->id, $existingIds))
            ->take(500)
            ->map(fn($p) => [
                'id'           => $p->id,
                'reference'    => $p->reference,
                'name'         => $p->name,
                'commune'      => $p->commune?->name ?? '',
                'format'       => $p->format?->name ?? '',
                'monthly_rate' => (float) ($p->monthly_rate ?? 0),
                'is_lit'       => (bool) $p->is_lit,
            ])
            ->values();

        return response()->json([
            'panels'           => $panels,
            'campaign_months'  => $campaign->billableMonths(),
        ]);
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
                $data['user_id'] = auth()->id();

                // Statut initial dérivé des dates
                $today = now()->startOfDay();
                $start = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
                $data['status'] = $start->gt($today)
                    ? CampaignStatus::PLANIFIE->value
                    : CampaignStatus::ACTIF->value;

                $reservation = null;
                if (!empty($data['reservation_id'])) {
                    $reservation = Reservation::with('panels')->findOrFail($data['reservation_id']);

                    if ($reservation->campaign()->exists()) {
                        throw new \Exception('Cette réservation est déjà liée à une campagne.');
                    }
                    if ($reservation->client_id !== (int) $data['client_id']) {
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
                    'campaign_id'      => $campaign->id,
                    'user_id'          => auth()->id(),
                    'client_id'        => $campaign->client_id,
                    'with_reservation' => $reservation !== null,
                    'status'           => $campaign->status->value,
                ]);

                AlertService::create(
                    'campagne',
                    'info',
                    '🚀 Campagne créée — ' . $campaign->name,
                    auth()->user()?->name . ' a créé la campagne "' . $campaign->name . '"'
                        . ($reservation ? ' depuis la réservation ' . $reservation->reference : ''),
                    $campaign
                );

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

        // Garde-fou : seules les campagnes PLANIFIEE / ACTIVE sont modifiables
        if (in_array($campaign->status->value, ['pose', 'termine', 'annule'])) {
            return back()->withInput()->with('error',
                "❌ Une campagne « {$campaign->status->label()} » ne peut pas être modifiée. " .
                "Seules les campagnes Planifiées ou Actives sont modifiables."
            );
        }

        $rules = [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('campaigns', 'name')
                    ->where('client_id', $request->client_id)
                    ->ignore($campaign->id),
            ],
            'client_id' => 'required|exists:clients,id',
            'end_date'  => 'required|date|after:start_date',
            'notes'     => 'nullable|string|max:2000',
        ];

        // start_date : verrouillée pour campagne ACTIVE, modifiable pour PLANIFIEE
        $rules['start_date'] = $campaign->status === CampaignStatus::ACTIF
            ? 'nullable|date'
            : 'required|date';

        $data = $request->validate($rules);

        if ($campaign->status === CampaignStatus::ACTIF && empty($data['start_date'])) {
            $data['start_date'] = $campaign->start_date->format('Y-m-d');
        }

        $today    = now()->startOfDay();
        $newStart = \Carbon\Carbon::parse($data['start_date'])->startOfDay();
        $newEnd   = \Carbon\Carbon::parse($data['end_date'])->startOfDay();

        if ($newEnd->lte($newStart)) {
            return back()->withInput()->with('error',
                '❌ La date de fin doit être postérieure à la date de début.');
        }

        // Garde-fou durée maximale : 36 mois (cohérence Reservation)
        if (abs($newStart->diffInMonths($newEnd)) > 36) {
            return back()->withInput()->with('error',
                '❌ La durée maximale d\'une campagne est de 36 mois.');
        }

        // Verrou date début pour campagne active
        if ($campaign->status === CampaignStatus::ACTIF
            && !$campaign->start_date->isSameDay($newStart)) {
            return back()->withInput()->with('error',
                '❌ Une campagne active ne peut pas voir sa date de début modifiée. ' .
                'La campagne a déjà commencé le ' . $campaign->start_date->format('d/m/Y') . '.');
        }

        // Statut recalculé en fonction des nouvelles dates
        $data['status'] = $this->calculateStatus($newStart, $newEnd, $campaign->status);

        $oldStart  = $campaign->start_date;
        $oldEnd    = $campaign->end_date;
        $oldStatus = $campaign->status;

        $data['updated_by'] = auth()->id();

        DB::transaction(function () use ($campaign, $data) {
            $campaign->update($data);

            // Si dates changées et qu'il y a une réservation liée, on doit
            // au minimum aligner end_date pour la cohérence facturation
            if ($campaign->reservation && $campaign->wasChanged(['start_date', 'end_date'])) {
                $campaign->reservation->updateWithoutObservers([
                    'start_date' => $campaign->start_date->format('Y-m-d'),
                    'end_date'   => $campaign->end_date->format('Y-m-d'),
                ]);
            }

            // Recalcul du montant si la durée a changé
            if ($campaign->wasChanged(['start_date', 'end_date'])) {
                $this->campaignService->recalculateCampaignAmount($campaign->fresh());
            }
        });

        Log::info('campaign.updated', [
            'campaign_id' => $campaign->id,
            'user_id'     => auth()->id(),
            'changes'     => [
                'start_date' => ['old' => $oldStart->format('Y-m-d'), 'new' => $campaign->start_date->format('Y-m-d')],
                'end_date'   => ['old' => $oldEnd->format('Y-m-d'),   'new' => $campaign->end_date->format('Y-m-d')],
                'status'     => ['old' => $oldStatus->value,           'new' => $campaign->status->value],
            ],
        ]);

        AlertService::create(
            'campagne',
            'info',
            '✏️ Campagne modifiée — ' . $campaign->name,
            auth()->user()?->name . ' a modifié la campagne "' . $campaign->name . '"',
            $campaign
        );

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

    /** Calcule le statut cible d'une campagne à partir de ses nouvelles dates */
    protected function calculateStatus(\Carbon\Carbon $start, \Carbon\Carbon $end, CampaignStatus $current): string
    {
        $today = now()->startOfDay();

        if ($end->lte($today))   return CampaignStatus::TERMINE->value;
        if ($start->gt($today))  return CampaignStatus::PLANIFIE->value;
        // Si campagne en pose et toujours dans la fenêtre, on garde POSE ; sinon ACTIF
        if ($current === CampaignStatus::POSE) return CampaignStatus::POSE->value;
        return CampaignStatus::ACTIF->value;
    }

    // ══════════════════════════════════════════════════════════════
    // ADD PANEL
    // ══════════════════════════════════════════════════════════════
    public function addPanel(Request $request, Campaign $campaign)
    {
        $this->authorize('managePanel', $campaign);

        $request->validate([
            'panel_ids'   => 'required|array|min:1|max:50',
            'panel_ids.*' => 'required|integer|exists:panels,id',
        ]);

        if (!in_array($campaign->status->value, ['planifie', 'actif', 'pose'])) {
            return back()->with('error',
                'Impossible d\'ajouter des panneaux à une campagne terminée ou annulée.');
        }

        $result = $this->campaignService->addPanels($campaign, $request->panel_ids);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        $count = $result['added'] ?? count($request->panel_ids);

        AlertService::create(
            'campagne',
            'info',
            '➕ Panneau ajouté — ' . $campaign->name,
            auth()->user()?->name . " a ajouté {$count} panneau(x) à la campagne \"{$campaign->name}\"",
            $campaign
        );

        return back()->with('success', "{$count} panneau(x) ajouté(s). Montant recalculé.");
    }

    // ══════════════════════════════════════════════════════════════
    // REMOVE PANEL
    // ══════════════════════════════════════════════════════════════
    public function removePanel(Campaign $campaign, Panel $panel)
    {
        $this->authorize('managePanel', $campaign);

        $result = $this->campaignService->removePanel($campaign, $panel);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        AlertService::create(
            'campagne',
            'warning',
            '➖ Panneau retiré — ' . $campaign->name,
            auth()->user()?->name . " a retiré le panneau {$panel->reference} de la campagne \"{$campaign->name}\"",
            $campaign
        );

        $msg = "Panneau {$panel->reference} retiré.";

        if (isset($result['warning'])) {
            return redirect()
                ->route('admin.campaigns.index')
                ->with('warning', $msg . ' ⚠️ ' . $result['warning']);
        }

        return back()->with('success', $msg . ' Montant recalculé.');
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════
    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $name   = $campaign->name;
        $result = $this->campaignService->delete($campaign);

        if (!$result['ok']) {
            return back()->with('error', $result['error']);
        }

        AlertService::create(
            'campagne',
            'danger',
            '🗑 Campagne supprimée — ' . $name,
            auth()->user()?->name . " a supprimé la campagne \"{$name}\"",
            null
        );

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campagne supprimée définitivement.');
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

        $userName = auth()->user()?->name;

        if ($newStatus === CampaignStatus::ANNULE) {
            $this->campaignService->cancel($campaign, "Annulation manuelle par {$userName}");
            $alertLevel = 'danger';
            $alertIcon  = '🚫';
            $alertVerb  = 'a annulé';
        } elseif ($newStatus === CampaignStatus::TERMINE) {
            $this->campaignService->terminate($campaign, "Clôture manuelle par {$userName}");
            $alertLevel = 'info';
            $alertIcon  = '✅';
            $alertVerb  = 'a clôturé';
        } else {
            $campaign->update([
                'status'     => $newStatus->value,
                'updated_by' => auth()->id(),
            ]);
            $alertLevel = 'info';
            $alertIcon  = '🔄';
            $alertVerb  = 'a changé le statut de';
        }

        AlertService::create(
            'campagne',
            $alertLevel,
            "{$alertIcon} Campagne — {$campaign->name}",
            "{$userName} {$alertVerb} la campagne \"{$campaign->name}\" → {$newStatus->label()}",
            $campaign
        );

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

        $newEndDate = \Carbon\Carbon::parse($request->new_end_date)->startOfDay();

        // Garde-fou durée maximale 36 mois
        if (abs($campaign->start_date->copy()->startOfDay()->diffInMonths($newEndDate)) > 36) {
            return back()->with('error',
                '❌ La durée totale dépasserait 36 mois (limite régie).');
        }

        // Vérifier conflits sur la période étendue
        $panelIds  = $campaign->panels->pluck('id')->toArray();
        $conflicts = $this->availability->getUnavailablePanelIds(
            $panelIds,
            $campaign->end_date->format('Y-m-d'),
            $newEndDate->format('Y-m-d'),
            $campaign->reservation_id
        );

        if (!empty($conflicts)) {
            $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
            return back()->with('error',
                "Impossible de prolonger — conflits sur la nouvelle période : {$refs}");
        }

        $oldEnd = $campaign->end_date->format('d/m/Y');
        $newEnd = $newEndDate->format('d/m/Y');

        DB::transaction(function () use ($campaign, $newEndDate) {
            $campaign->update([
                'end_date'   => $newEndDate->format('Y-m-d'),
                'status'     => CampaignStatus::ACTIF->value,
                'updated_by' => auth()->id(),
            ]);

            if ($campaign->reservation) {
                $campaign->reservation->updateWithoutObservers([
                    'end_date' => $newEndDate->format('Y-m-d'),
                ]);
            }

            $this->campaignService->recalculateCampaignAmount($campaign->fresh());
        });

        Log::info('campaign.prolonged', [
            'campaign_id'  => $campaign->id,
            'old_end_date' => $oldEnd,
            'new_end_date' => $newEnd,
            'user_id'      => auth()->id(),
        ]);

        AlertService::create(
            'campagne',
            'info',
            '📅 Campagne prolongée — ' . $campaign->name,
            auth()->user()?->name . " a prolongé la campagne \"{$campaign->name}\" jusqu'au {$newEnd}",
            $campaign
        );

        return back()->with('success', "Campagne prolongée jusqu'au {$newEnd}. Montant recalculé.");
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT EXCEL — applique les mêmes filtres que l'index
    // ══════════════════════════════════════════════════════════════
    public function exportExcel(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $filters = $request->only([
            'search', 'status', 'client_id',
            'date_debut', 'date_fin', 'date_from', 'date_to',
        ]);

        $filename = 'campagnes-' . now()->format('Ymd-His') . '.xlsx';

        Log::info('campaigns.export.excel', [
            'filters' => $filters,
            'user_id' => auth()->id(),
        ]);

        return Excel::download(new CampaignsExport($filters), $filename);
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT PDF — liste filtrée, format A4 paysage
    // ══════════════════════════════════════════════════════════════
    public function exportPdf(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $query = Campaign::with(['client:id,name', 'user:id,name'])
            ->withCount('panels')
            ->when($request->search,      fn($q, $s)  => $q->where('name', 'like', "%{$s}%"))
            ->when($request->client_id,   fn($q, $id) => $q->where('client_id', $id))
            ->when($request->status,      fn($q, $s)  => $q->where('status', $s))
            ->when($request->date_debut,  fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_fin,    fn($q, $d)  => $q->where('start_date', '<=', $d))
            ->when($request->date_from,   fn($q, $d)  => $q->where('start_date', '>=', $d))
            ->when($request->date_to,     fn($q, $d)  => $q->where('end_date', '<=', $d))
            ->orderByDesc('created_at')
            ->limit(2000); // garde-fou perf : pas plus de 2000 lignes par PDF

        $campaigns = $query->get();
        $logoSrc   = $this->getLogoPdf();
        $generated = now()->format('d/m/Y à H:i');
        $totalAmount = (float) $campaigns->sum('total_amount');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.campaigns.pdf.list', compact(
            'campaigns', 'logoSrc', 'generated', 'totalAmount'
        ))->setPaper('a4', 'landscape')->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
            'dpi'                  => 96,
        ]);

        Log::info('campaigns.export.pdf', [
            'count'   => $campaigns->count(),
            'user_id' => auth()->id(),
        ]);

        return $pdf->download('campagnes-' . now()->format('Ymd-His') . '.pdf');
    }
}
