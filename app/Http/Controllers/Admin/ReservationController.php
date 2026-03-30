<?php
namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\UpdateReservationRequest;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\Reservation;
use App\Models\ReservationPanel;
use App\Models\Zone;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function __construct(protected AvailabilityService $availability) {}

    // ══════════════════════════════════════════════════════
    // DISPONIBILITÉS
    // ══════════════════════════════════════════════════════

    public function disponibilites(Request $request)
{
    $communes = Commune::orderBy('name')->get();
    $formats  = PanelFormat::orderBy('name')->get();
    $zones    = Zone::orderBy('name')->get();
    $clients  = Client::orderBy('name')->get();

    // ── Dimensions disponibles pour le filtre ─────────────────────
    $dimensions = PanelFormat::whereNotNull('width')
        ->whereNotNull('height')
        ->orderBy('width')->orderBy('height')
        ->get()
        ->map(function ($f) {
            if (!$f->width || !$f->height) return null;
            $w = rtrim(rtrim(number_format($f->width, 2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($f->height, 2, '.', ''), '0'), '.');
            return "{$w}×{$h}m";
        })
        ->filter()
        ->unique()
        ->values();

    $startDate = $request->dispo_du  ?: null;
    $endDate   = $request->dispo_au  ?: null;
    $statut    = $request->get('statut', 'tous');
    $dateError = null;

    // ── Requête panneaux ──────────────────────────────────────────
    $query = Panel::with(['commune', 'format', 'zone', 'category'])
        ->whereNull('deleted_at');

    // Multi-communes
    $communeIds = array_filter((array)$request->get('commune_ids', []));
    if (!empty($communeIds)) {
        $query->whereIn('commune_id', $communeIds);
    }

    // Multi-zones
    $zoneIds = array_filter((array)$request->get('zone_ids', []));
    if (!empty($zoneIds)) {
        $query->whereIn('zone_id', $zoneIds);
    }

    // Multi-formats
    $formatIds = array_filter((array)$request->get('format_ids', []));
    if (!empty($formatIds)) {
        $query->whereIn('format_id', $formatIds);
    }

    // Éclairage — uniquement si valeur explicite
    $isLit = $request->input('is_lit', '');
    if ($isLit === '1') {
        $query->where('is_lit', true);
    } elseif ($isLit === '0') {
        $query->where('is_lit', false);
    }

    // Filtre dimensions
    if ($request->filled('dimensions')) {
        $dimClean = str_replace('m', '', $request->dimensions);
        $w = $h = null;
        if (str_contains($dimClean, '×')) {
            [$w, $h] = explode('×', $dimClean);
        } elseif (str_contains($dimClean, 'x')) {
            [$w, $h] = explode('x', $dimClean);
        }
        if ($w !== null && $h !== null
            && is_numeric(trim($w)) && is_numeric(trim($h))) {
            $query->whereHas('format', fn($q) =>
                $q->whereBetween('width',  [(float)trim($w)-0.01, (float)trim($w)+0.01])
                  ->whereBetween('height', [(float)trim($h)-0.01, (float)trim($h)+0.01])
            );
        }
    }

    // Filtre statut DB direct (hors période)
    if (in_array($statut, ['maintenance', 'libre', 'confirme'])) {
        $query->where('status', $statut);
    }

    $allPanels    = $query->orderBy('reference')->get();
    $occupiedIds  = collect();
    $optionIds    = collect();
    $releaseDates = collect();

    // ── Calcul occupation + dates de libération ───────────────────
    if ($startDate && $endDate) {
        if ($endDate <= $startDate) {
            $dateError = 'La date de fin doit être après la date de début.';
        } else {
            // Panneaux avec réservation CONFIRME sur la période
            $occupiedIds = ReservationPanel::whereHas('reservation', fn($q) =>
                $q->where('status', ReservationStatus::CONFIRME->value)
                  ->where('start_date', '<', $endDate)
                  ->where('end_date',   '>', $startDate)
            )->pluck('panel_id')->unique();

            // Panneaux avec réservation EN_ATTENTE (option) sur la période
            $optionIds = ReservationPanel::whereHas('reservation', fn($q) =>
                $q->where('status', ReservationStatus::EN_ATTENTE->value)
                  ->where('start_date', '<', $endDate)
                  ->where('end_date',   '>', $startDate)
            )->pluck('panel_id')->unique();

            // ── Dates de libération ───────────────────────────────
            // Pour chaque panneau bloqué, on cherche la date de fin MAX
            // de ses réservations actives → "Libre le JJ/MM/YYYY"
            $blockedIds = $occupiedIds->merge($optionIds)->unique();

            if ($blockedIds->isNotEmpty()) {
                $releaseDates = ReservationPanel::whereIn('panel_id', $blockedIds)
                    ->join('reservations',
                        'reservations.id', '=', 'reservation_panels.reservation_id')
                    ->whereIn('reservations.status', [
                        ReservationStatus::CONFIRME->value,
                        ReservationStatus::EN_ATTENTE->value,
                    ])
                    ->where('reservations.end_date', '>', $startDate)
                    ->select(
                        'reservation_panels.panel_id',
                        DB::raw('MAX(reservations.end_date) as release_date')
                    )
                    ->groupBy('reservation_panels.panel_id')
                    ->pluck('release_date', 'panel_id');
            }
        }
    } elseif ($startDate && !$endDate) {
        $dateError = 'Veuillez renseigner la date de fin.';
    } elseif (!$startDate && $endDate) {
        $dateError = 'Veuillez renseigner la date de début.';
    }

    // ── Post-filtrage statut période ──────────────────────────────
    if (!$dateError && $startDate && $endDate) {
        if ($statut === 'occupe') {
            // Occupé = confirmé OU option sur la période
            $allPanels = $allPanels->filter(fn($p) =>
                $occupiedIds->contains($p->id) || $optionIds->contains($p->id)
            )->values();
        } elseif ($statut === 'option') {
            $allPanels = $allPanels->filter(fn($p) =>
                $optionIds->contains($p->id)
            )->values();
        } elseif ($statut === 'libre') {
            // Disponible = pas bloqué ET pas maintenance
            $allPanels = $allPanels->filter(fn($p) =>
                !$occupiedIds->contains($p->id) &&
                !$optionIds->contains($p->id) &&
                $p->status->value !== 'maintenance'
            )->values();
        }
    } elseif (in_array($statut, ['occupe', 'option'])
        && (!$startDate || !$endDate)) {
        // Sans période on ne peut pas calculer l'occupation
        $allPanels = collect();
        $dateError = 'Veuillez saisir une période pour filtrer par '
            . ($statut === 'option' ? 'Option' : 'Occupé') . '.';
    }

    return view('admin.reservations.disponibilites', compact(
        'allPanels', 'communes', 'formats', 'zones', 'clients',
        'dimensions', 'occupiedIds', 'optionIds', 'releaseDates',
        'startDate', 'endDate', 'dateError', 'statut'
    ));
}

    // ── PDF images des panneaux sélectionnés ─────────────────────
    public function pdfImages(Request $request)
    {
        $request->validate([
            'panel_ids'   => 'required|array|min:1',
            'panel_ids.*' => 'integer|exists:panels,id',
        ]);

        $panels = \App\Models\Panel::with(['commune', 'format', 'category', 'photos'])
            ->whereIn('id', $request->panel_ids)
            ->orderBy('reference')
            ->get();

        $startDate = $request->start_date;
        $endDate   = $request->end_date;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.selection-images', compact(
            'panels', 'startDate', 'endDate'
        ));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('selection-panneaux-images-' . now()->format('Ymd') . '.pdf');
    }

    // ── PDF liste des panneaux sélectionnés ──────────────────────
    public function pdfListe(Request $request)
    {
        $request->validate([
            'panel_ids'   => 'required|array|min:1',
            'panel_ids.*' => 'integer|exists:panels,id',
        ]);

        $panels = \App\Models\Panel::with(['commune', 'format', 'category'])
            ->whereIn('id', $request->panel_ids)
            ->orderBy('reference')
            ->get();

        $startDate    = $request->start_date;
        $endDate      = $request->end_date;
        $dureeEnMois  = ($startDate && $endDate)
            ? max(1, (int) ceil(\Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) / 30))
            : 1;
        $totalMensuel = $panels->sum(fn($p) => (float)($p->monthly_rate ?? 0));
        $totalPeriode = $totalMensuel * $dureeEnMois;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.selection-liste', compact(
            'panels', 'startDate', 'endDate', 'dureeEnMois', 'totalMensuel', 'totalPeriode'
        ));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('selection-panneaux-liste-' . now()->format('Ymd') . '.pdf');
    }

    // ── Confirmer sélection ───────────────────────────────
    public function confirmerSelection(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|exists:clients,id',
            'start_date'    => [
                'required', 'date',
                function ($attr, $value, $fail) {
                    if ($value < now()->subDay()->format('Y-m-d')) {
                        $fail('La date de début ne peut pas être dans le passé.');
                    }
                },
            ],
            'end_date'      => 'required|date|after:start_date',
            'notes'         => 'nullable|string|max:2000',
            'panel_ids'     => 'required|array|min:1|max:50',
            'panel_ids.*'   => 'required|integer|exists:panels,id',
            'type'          => 'required|in:option,ferme',
            'campaign_name' => 'nullable|string|max:150',
        ]);

        // Vérifications préalables
        $maintenancePanels = Panel::whereIn('id', $request->panel_ids)
            ->where('status', 'maintenance')->pluck('reference');
        if ($maintenancePanels->isNotEmpty()) {
            return back()->withErrors([
                'panel_ids' => 'Panneaux en maintenance non réservables : '
                    . $maintenancePanels->join(', '),
            ])->withInput();
        }

        $deletedCount = Panel::whereIn('id', $request->panel_ids)->onlyTrashed()->count();
        if ($deletedCount > 0) {
            return back()->withErrors([
                'panel_ids' => 'Un ou plusieurs panneaux sélectionnés n\'existent plus.',
            ])->withInput();
        }

        $createdCampaignId = null;

        try {
            DB::transaction(function () use ($request, &$createdCampaignId) {
                // Verrou pessimiste anti race-condition
                Panel::whereIn('id', $request->panel_ids)->lockForUpdate()->get();

                $conflicts = $this->availability->getUnavailablePanelIds(
                    $request->panel_ids,
                    $request->start_date,
                    $request->end_date
                );

                if (!empty($conflicts)) {
                    $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                    throw new \RuntimeException("CONFLICT:$refs");
                }

                $status = $request->type === 'ferme'
                    ? ReservationStatus::CONFIRME
                    : ReservationStatus::EN_ATTENTE;

                // Référence unique avec limite d'essais
                $reference = null;
                $attempts  = 0;
                do {
                    $candidate = 'RES-' . strtoupper(Str::random(8));
                    $attempts++;
                    if ($attempts > 10) {
                        $candidate = 'RES-' . strtoupper(substr(
                            str_replace('-', '', Str::uuid()), 0, 8
                        ));
                    }
                    if ($attempts > 20) {
                        throw new \RuntimeException('SYSTEM:Référence impossible à générer.');
                    }
                    if (!Reservation::where('reference', $candidate)->exists()) {
                        $reference = $candidate;
                    }
                } while ($reference === null);

                $panelData  = Panel::whereIn('id', $request->panel_ids)->get()->keyBy('id');
                $months     = $this->monthsBetween($request->start_date, $request->end_date);
                $total      = 0;
                $attachData = [];

                foreach ($request->panel_ids as $panelId) {
                    $panel      = $panelData[$panelId];
                    $unitPrice  = (float)($panel->monthly_rate ?? 0);
                    $totalPrice = $unitPrice * $months;
                    $total     += $totalPrice;
                    $attachData[$panelId] = [
                        'unit_price'  => $unitPrice,
                        'total_price' => $totalPrice,
                    ];
                }

                $reservation = Reservation::create([
                    'reference'    => $reference,
                    'client_id'    => $request->client_id,
                    'user_id'      => auth()->id(),
                    'start_date'   => $request->start_date,
                    'end_date'     => $request->end_date,
                    'status'       => $status,
                    'type'         => $request->type,
                    'notes'        => $request->notes,
                    'total_amount' => $total,
                    'confirmed_at' => $request->type === 'ferme' ? now() : null,
                ]);

                $reservation->panels()->attach($attachData);
                $this->availability->syncPanelStatuses($request->panel_ids);

                // Création automatique campagne si ferme + nom
                if ($request->type === 'ferme' && $request->filled('campaign_name')) {
                    $campaignExists = \App\Models\Campaign::where('client_id', $request->client_id)
                        ->where('name', $request->campaign_name)
                        ->exists();

                    if ($campaignExists) {
                        throw new \RuntimeException(
                            'CAMPAIGN_EXISTS:Une campagne avec ce nom existe déjà pour ce client.'
                        );
                    }

                    $campaign = \App\Models\Campaign::create([
                        'name'           => $request->campaign_name,
                        'client_id'      => $request->client_id,
                        'reservation_id' => $reservation->id,
                        'user_id'      => auth()->id(),
                        'start_date'     => $request->start_date,
                        'end_date'       => $request->end_date,
                        'status'         => \App\Enums\CampaignStatus::ACTIF->value,
                        'total_panels'   => count($request->panel_ids),
                        'total_amount'   => $total,
                        'notes'          => $request->notes,
                    ]);

                    $campaign->panels()->sync(array_keys($attachData));
                    $createdCampaignId = $campaign->id;

                    Log::info('campaign.auto_created', [
                        'campaign_id'    => $campaign->id,
                        'reservation_id' => $reservation->id,
                        'user_id'        => auth()->id(),
                    ]);
                }

                Log::info('reservation.created', [
                    'reservation_id' => $reservation->id,
                    'reference'      => $reservation->reference,
                    'type'           => $request->type,
                    'panel_ids'      => $request->panel_ids,
                    'total_amount'   => $total,
                    'user_id'        => auth()->id(),
                    'ip'             => request()->ip(),
                ]);
            });

        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:')) {
                return back()->withErrors([
                    'panel_ids' => 'Conflit détecté (réservé entre temps) : '
                        . substr($e->getMessage(), 9),
                ])->withInput();
            }
            if (str_starts_with($e->getMessage(), 'CAMPAIGN_EXISTS:')) {
                return back()->withErrors([
                    'campaign_name' => substr($e->getMessage(), 16),
                ])->withInput();
            }
            if (str_starts_with($e->getMessage(), 'SYSTEM:')) {
                return back()->with('error', substr($e->getMessage(), 7))->withInput();
            }
            throw $e;
        }

        if ($createdCampaignId) {
            return redirect()
                ->route('admin.campaigns.show', $createdCampaignId)
                ->with('success', 'Réservation ferme créée et campagne lancée automatiquement. ✅');
        }

        return redirect()
            ->route('admin.reservations.disponibilites')
            ->with('success', $request->type === 'ferme'
                ? 'Réservation ferme créée. Panneaux confirmés.'
                : 'Panneaux mis sous option. Créez une campagne dès confirmation client.'
            );
    }

    // ══════════════════════════════════════════════════════
    // CRUD RÉSERVATIONS
    // ══════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $reservations = Reservation::with(['client', 'user'])
            ->withCount('panels')
            ->when($request->search, fn($q, $s) =>
                $q->where('reference', 'like', "%$s%")
                  ->orWhereHas('client', fn($q) =>
                      $q->withTrashed()->where('name', 'like', "%$s%"))
            )
            ->when($request->status,    fn($q, $s)  => $q->where('status',    $s))
            ->when($request->type,      fn($q, $t)  => $q->where('type',      $t))
            ->when($request->client_id, fn($q, $id) => $q->where('client_id', $id))
            ->when($request->periode, function ($q, $p) { return match($p) {
                    'this_month'   => $q->whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year),
                    'last_month'   => $q->whereMonth('created_at', now()->subMonth()->month)
                                    ->whereYear('created_at', now()->subMonth()->year),
                    'this_quarter' => $q->whereBetween('created_at', [
                                        now()->startOfQuarter(),
                                        now()->endOfQuarter(),
                                    ]),
                    'this_year'    => $q->whereYear('created_at', now()->year),
                    default        => $q,
                };
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $rawCounts = Reservation::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'total'      => $rawCounts->sum(),
            'en_attente' => $rawCounts['en_attente'] ?? 0,
            'confirme'   => $rawCounts['confirme']   ?? 0,
            'refuse'     => $rawCounts['refuse']     ?? 0,
            'annule'     => $rawCounts['annule']     ?? 0,
        ];

        $lastSeenAt = auth()->user()->reservations_last_seen_at;
        $newCount   = $lastSeenAt
            ? Reservation::where('created_at', '>', $lastSeenAt)->count()
            : 0;

        $clients  = Client::orderBy('name')->get();
        $statuses = ReservationStatus::cases();

        return view('admin.reservations.index',
            compact('reservations', 'clients', 'statuses', 'counts', 'lastSeenAt', 'newCount')
        );
    }

    public function markSeen()
    {
        auth()->user()->update(['reservations_last_seen_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'client', 'user',
            'panels.commune', 'panels.format',
            'campaign',
        ]);

        $user = auth()->user();

        $can = [
            'update'       => $reservation->isEditable()
                                && $user->can('update', $reservation),
            'updateStatus' => $reservation->canChangeStatus()
                                && $user->can('updateStatus', $reservation),
            'annuler'      => $reservation->isCancellable()
                                && $user->can('annuler', $reservation),
            'delete'       => $reservation->isDeletable()
                                && $user->can('delete', $reservation),
        ];

        return view('admin.reservations.show', compact('reservation', 'can'));
    }

    public function edit(Reservation $reservation)
    {
        if (!$reservation->isEditable()) {
            abort(403, 'Cette réservation ne peut plus être modifiée '
                . '(statut : ' . $reservation->status->label() . ').');
        }

        $reservation->load('panels');
        $clients  = Client::orderBy('name')->get();
        $communes = Commune::orderBy('name')->get();
        $formats  = PanelFormat::orderBy('name')->get();
        $zones    = Zone::orderBy('name')->get();

        $selectedPanelIds = $reservation->panels->pluck('id')->toArray();

        return view('admin.reservations.edit',
            compact('reservation', 'clients', 'communes', 'formats', 'zones', 'selectedPanelIds')
        );
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        if (!$reservation->isEditable()) {
            abort(403, 'Cette réservation ne peut plus être modifiée.');
        }
        if ($reservation->client?->trashed()) {
            abort(403, 'Client supprimé — modification impossible.');
        }

        // Protection modification concurrente
        if ((int)$request->last_updated_at !== $reservation->updated_at->timestamp) {
            return back()->with('error',
                'Cette réservation a été modifiée par un autre utilisateur. '
                . 'Veuillez recharger la page.'
            );
        }

        $data      = $request->validated();
        $oldPanels = $reservation->panels->pluck('id')->toArray();

        try {
            DB::transaction(function () use ($data, $reservation, $oldPanels) {
                Panel::whereIn('id', $data['panel_ids'])->lockForUpdate()->get();

                $conflicts = $this->availability->getUnavailablePanelIds(
                    $data['panel_ids'],
                    $data['start_date'],
                    $data['end_date'],
                    $reservation->id
                );

                if (!empty($conflicts)) {
                    $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                    throw new \RuntimeException("CONFLICT:$refs");
                }

                $months    = $this->monthsBetween($data['start_date'], $data['end_date']);
                $panelData = Panel::whereIn('id', $data['panel_ids'])->get()->keyBy('id');
                $sync      = [];
                $total     = 0;

                foreach ($data['panel_ids'] as $panelId) {
                    $panel      = $panelData[$panelId];
                    $unitPrice  = (float)($panel->monthly_rate ?? 0);
                    $totalPrice = $unitPrice * $months;
                    $total     += $totalPrice;
                    $sync[$panelId] = [
                        'unit_price'  => $unitPrice,
                        'total_price' => $totalPrice,
                    ];
                }

                $reservation->update([
                    'client_id'    => $data['client_id'],
                    'start_date'   => $data['start_date'],
                    'end_date'     => $data['end_date'],
                    'notes'        => $data['notes'] ?? null,
                    'total_amount' => $total,
                ]);

                $reservation->panels()->sync($sync);

                $allAffected = array_unique(array_merge($oldPanels, $data['panel_ids']));
                $this->availability->syncPanelStatuses($allAffected);

                Log::info('reservation.updated', [
                    'reservation_id' => $reservation->id,
                    'user_id'        => auth()->id(),
                    'ip'             => request()->ip(),
                ]);
            });

        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:')) {
                return back()->withInput()
                    ->withErrors(['panel_ids' => 'Conflit détecté : ' . substr($e->getMessage(), 9)]);
            }
            throw $e;
        }

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'Réservation mise à jour.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        if ($reservation->client?->trashed()) {
            return back()->with('error',
                'Impossible : le client de cette réservation a été supprimé.');
        }

        $request->validate([
            'status' => 'required|in:' . implode(',',
                array_column(ReservationStatus::cases(), 'value')),
        ]);

        $newStatus = $request->status;

        if (!$reservation->canTransitionTo($newStatus)) {
            return back()->with('error',
                "Transition interdite : {$reservation->status->value} → $newStatus.");
        }

        $oldStatus = $reservation->status->value;
        $data      = ['status' => $newStatus];

        if ($newStatus === ReservationStatus::CONFIRME->value) {
            $data['confirmed_at'] = now();
            $data['type']         = 'ferme';
        }

        $reservation->update($data);
        $this->availability->syncPanelStatuses(
            $reservation->panels->pluck('id')->toArray()
        );

        Log::info('reservation.status_changed', [
            'reservation_id' => $reservation->id,
            'from'           => $oldStatus,
            'to'             => $newStatus,
            'user_id'        => auth()->id(),
            'ip'             => request()->ip(),
        ]);

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', "Statut mis à jour : {$newStatus}.");
    }

    public function annuler(Reservation $reservation)
    {
        if ($reservation->client?->trashed()) {
            abort(403, 'Impossible : client supprimé.');
        }
        if (!$reservation->isCancellable()) {
            abort(403, 'Cette réservation ne peut pas être annulée.');
        }

        $panelIds  = $reservation->panels->pluck('id')->toArray();
        $oldStatus = $reservation->status->value;

        $reservation->update(['status' => ReservationStatus::ANNULE]);
        $this->availability->syncPanelStatuses($panelIds);

        Log::info('reservation.cancelled', [
            'reservation_id' => $reservation->id,
            'from_status'    => $oldStatus,
            'panel_ids'      => $panelIds,
            'user_id'        => auth()->id(),
            'ip'             => request()->ip(),
        ]);

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', 'Réservation annulée. ' . count($panelIds) . ' panneau(x) libéré(s).');
    }

    public function destroy(Reservation $reservation)
    {
        if (!$reservation->isDeletable()) {
            abort(403, 'Impossible de supprimer : la réservation est active ou liée à une campagne.');
        }

        $panelIds = $reservation->panels->pluck('id')->toArray();

        Log::info('reservation.deleted', [
            'reservation_id' => $reservation->id,
            'reference'      => $reservation->reference,
            'status'         => $reservation->status->value,
            'user_id'        => auth()->id(),
            'ip'             => request()->ip(),
        ]);

        $reservation->delete();

        if (!empty($panelIds)) {
            $this->availability->syncPanelStatuses($panelIds);
        }

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', 'Réservation supprimée définitivement.');
    }

    public function availablePanels(Request $request)
    {
        $request->validate([
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after:start_date',
            'format_width'  => 'nullable|numeric|min:0',
            'format_height' => 'nullable|numeric|min:0',
        ]);

        $start = Carbon::parse($request->start_date);
        $end   = Carbon::parse($request->end_date);

        if ($end->lte($start)) {
            return response()->json([
                'error' => 'La date de fin doit être après la date de début.'
            ], 422);
        }

        $panels = $this->availability->getAvailablePanels(
            $request->start_date,
            $request->end_date,
            $request->exclude_reservation_id,
            [
                'commune_id'    => $request->commune_id,
                'zone_id'       => $request->zone_id,
                'format_id'     => $request->format_id,
                'format_width'  => $request->format_width,
                'format_height' => $request->format_height,
            ]
        );

        return response()->json(
            $panels->map(fn($p) => [
                'id'            => $p->id,
                'reference'     => $p->reference,
                'name'          => $p->name,
                'commune'       => $p->commune?->name,
                'format'        => $p->format?->name,
                'dimensions'    => $p->format
                    ? $this->formatDimensions($p->format->width, $p->format->height)
                    : null,
                'format_width'  => $p->format?->width,
                'format_height' => $p->format?->height,
                'zone'          => $p->zone?->name,
                'monthly_rate'  => $p->monthly_rate,
                'is_lit'        => $p->is_lit,
                'status'        => $p->status->value,
                'daily_traffic' => $p->daily_traffic,
            ])
        );
    }

    // ══════════════════════════════════════════════════════
    // UTILITAIRES PRIVÉS
    // ══════════════════════════════════════════════════════

    private function formatDimensions(?float $w, ?float $h): ?string
    {
        if (!$w || !$h) return null;
        $wStr = rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
        $hStr = rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.');
        return "{$wStr}×{$hStr}m";
    }

    private function monthsBetween(string $start, string $end): float
    {
        $s      = Carbon::parse($start)->startOfDay();
        $e      = Carbon::parse($end)->endOfDay();
        $months = (int)$s->diffInMonths($e);
        $remain = $s->copy()->addMonths($months)->diffInDays($e);
        return max((float)($remain > 0 ? $months + 1 : $months), 1.0);
    }
}
