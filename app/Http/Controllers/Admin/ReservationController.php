<?php
namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\StoreReservationRequest;
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
    // DISPONIBILITÉS — écran principal
    // ══════════════════════════════════════════════════════

    public function disponibilites(Request $request)
    {
        $communes = Commune::orderBy('name')->get();
        $formats  = PanelFormat::orderBy('name')->get();
        $zones    = Zone::orderBy('name')->get();
        $clients  = Client::orderBy('name')->get();

        $dimensions = \App\Models\PanelFormat::whereNotNull('width')
        ->whereNotNull('height')
        ->orderBy('width')->orderBy('height')
        ->get()
        ->map(fn($f) => ReservationController::formatDimensions($f->width, $f->height))
        ->filter()
        ->unique()
        ->values();
 
        if ($request->dimensions) {
            [$w, $h] = explode('×', str_replace('m', '', $request->dimensions));
            $query->whereHas('format', fn($q) =>
                $q->whereBetween('width',  [(float)$w - 0.01, (float)$w + 0.01])
                ->whereBetween('height', [(float)$h - 0.01, (float)$h + 0.01])
            );
        }

        $query = Panel::with(['commune', 'format', 'zone', 'category'])
            ->whereNull('deleted_at');

        if ($request->commune_id) $query->where('commune_id', $request->commune_id);
        if ($request->zone_id)    $query->where('zone_id',    $request->zone_id);
        if ($request->format_id)  $query->where('format_id',  $request->format_id);
        if ($request->statut && $request->statut !== 'tous') {
            $query->where('status', $request->statut);
        }

        $allPanels   = $query->orderBy('reference')->get();
        $occupiedIds = collect();
        $optionIds   = collect();
        $startDate   = $request->dispo_du;
        $endDate     = $request->dispo_au;

        if ($startDate && $endDate) {
            $occupiedIds = ReservationPanel::whereHas('reservation', fn($q) =>
                $q->where('status', ReservationStatus::CONFIRME->value)
                  ->where('start_date', '<=', $endDate)
                  ->where('end_date',   '>=', $startDate)
            )->pluck('panel_id')->unique();

            $optionIds = ReservationPanel::whereHas('reservation', fn($q) =>
                $q->where('status', ReservationStatus::EN_ATTENTE->value)
                  ->where('start_date', '<=', $endDate)
                  ->where('end_date',   '>=', $startDate)
            )->pluck('panel_id')->unique();
        }

        return view('admin.reservations.disponibilites', compact(
            'allPanels', 'communes', 'formats', 'zones', 'clients',
            'occupiedIds', 'optionIds', 'startDate', 'endDate'
        ));
    }

    // ── Confirmer sélection depuis la page disponibilités ──
    public function confirmerSelection(Request $request)
    {
        $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'start_date'   => 'required|date|after_or_equal:today',
            'end_date'     => 'required|date|after:start_date',
            'notes'        => 'nullable|string|max:2000',
            'panel_ids'    => 'required|array|min:1|max:50',
            'panel_ids.*'  => 'required|integer|exists:panels,id',
            'type'         => 'required|in:option,ferme',
        ]);

        // Vérifier qu'aucun panneau soumis n'est en maintenance
        $maintenancePanels = Panel::whereIn('id', $request->panel_ids)
            ->where('status', 'maintenance')
            ->pluck('reference');

        if ($maintenancePanels->isNotEmpty()) {
            return back()->withErrors([
                'panel_ids' => 'Panneaux en maintenance non réservables : '
                    . $maintenancePanels->join(', '),
            ])->withInput();
        }

        // Vérifier qu'aucun panneau n'est soft deleted
        $deletedCount = Panel::whereIn('id', $request->panel_ids)
            ->onlyTrashed()
            ->count();

        if ($deletedCount > 0) {
            return back()->withErrors([
                'panel_ids' => 'Un ou plusieurs panneaux sélectionnés n\'existent plus.',
            ])->withInput();
        }

        try {
            DB::transaction(function () use ($request) {
                // ── Verrou pessimiste anti race condition ──
                Panel::whereIn('id', $request->panel_ids)
                    ->lockForUpdate()
                    ->get();

                // Re-vérification DANS la transaction
                $conflicts = $this->availability->getUnavailablePanelIds(
                    $request->panel_ids,
                    $request->start_date,
                    $request->end_date
                );

                if (! empty($conflicts)) {
                    $refs = Panel::whereIn('id', $conflicts)
                        ->pluck('reference')->join(', ');
                    throw new \RuntimeException("CONFLICT:$refs");
                }

                $status = $request->type === 'ferme'
                    ? ReservationStatus::CONFIRME
                    : ReservationStatus::EN_ATTENTE;

                // Référence unique avec retry
                do {
                    $reference = 'RES-' . strtoupper(Str::random(8));
                } while (Reservation::where('reference', $reference)->exists());

                // Calcul du total AVANT création
                $panelData = Panel::whereIn('id', $request->panel_ids)->get()->keyBy('id');
                $months    = $this->monthsBetween($request->start_date, $request->end_date);
                $total     = 0;
                $attachData = [];

                foreach ($request->panel_ids as $panelId) {
                    $panel      = $panelData[$panelId];
                    $unitPrice  = (float) ($panel->monthly_rate ?? 0);
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

                // Sync statuts panneaux
                $this->availability->syncPanelStatuses($request->panel_ids);

                // Log d'audit
                Log::info('reservation.created', [
                    'reservation_id' => $reservation->id,
                    'reference'      => $reservation->reference,
                    'type'           => $request->type,
                    'panel_ids'      => $request->panel_ids,
                    'user_id'        => auth()->id(),
                    'ip'             => request()->ip(),
                ]);
            });

        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:')) {
                $refs = substr($e->getMessage(), 9);
                return back()->withErrors([
                    'panel_ids' => "Conflit détecté (réservé entre temps) : $refs",
                ])->withInput();
            }
            throw $e;
        }

        $msg = $request->type === 'ferme'
            ? 'Réservation ferme créée. Panneaux confirmés.'
            : 'Panneaux mis sous option.';

        return redirect()
            ->route('admin.reservations.disponibilites')
            ->with('success', $msg);
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
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();
 
        // ── Counts en une seule requête agrégée ────────
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
 
        // ── Nouvelles réservations depuis dernière visite ──
        $lastSeenAt = auth()->user()->reservations_last_seen_at;
        $newCount   = $lastSeenAt
            ? Reservation::where('created_at', '>', $lastSeenAt)->count()
            : 0;
 
        $clients  = Client::orderBy('name')->get();
        $statuses = ReservationStatus::cases();
 
        return view('admin.reservations.index',
            compact('reservations', 'clients', 'statuses', 'counts',
                    'lastSeenAt', 'newCount'));
    }

    // ── markSeen() — NOUVELLE MÉTHODE ─────────────────
    public function markSeen()
    {
        auth()->user()->update([
            'reservations_last_seen_at' => now(),
        ]);
 
        return response()->json(['ok' => true]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'client',
            'user',
            'panels.commune',
            'panels.format',
            'campaign',
        ]);
 
        $user = auth()->user();
 
        $can = [
            // Modifier : en_attente + client actif + droits
            'update'       => $reservation->isEditable()
                                && $user->can('update', $reservation),
 
            // Changer statut : client non supprimé + transitions dispo + droits
            'updateStatus' => $reservation->canChangeStatus()
                                && $user->can('updateStatus', $reservation),
 
            // Annuler : en_attente/confirme + client actif + droits
            'annuler'      => $reservation->isCancellable()
                                && $user->can('annuler', $reservation),
 
            // Supprimer : annulé/refusé + pas campagne active + admin
            'delete'       => $reservation->isDeletable()
                                && $user->can('delete', $reservation),
        ];
 
        return view('admin.reservations.show', compact('reservation', 'can'));
    }

     public function edit(Reservation $reservation)
    {
        if (! $reservation->isEditable()) {
            abort(403,
                'Cette réservation ne peut plus être modifiée '
                . '(statut : ' . $reservation->status->label() . ').');
        }
 
        $reservation->load('panels');
        $clients  = Client::orderBy('name')->get();
        $communes = Commune::orderBy('name')->get();
        $formats  = PanelFormat::orderBy('name')->get();
        $zones    = Zone::orderBy('name')->get();
 
        $selectedPanelIds = $reservation->panels->pluck('id')->toArray();
 
        return view('admin.reservations.edit',
            compact('reservation', 'clients', 'communes', 'formats',
                    'zones', 'selectedPanelIds'));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        if (! $reservation->isEditable()) {
            abort(403, 'Cette réservation ne peut plus être modifiée.');
        }

        if ($reservation->client?->trashed()) {
            abort(403, 'Client supprimé — modification impossible.');
        }

        $data      = $request->validated();
        $oldPanels = $reservation->panels->pluck('id')->toArray();

        try {
            DB::transaction(function () use ($data, $reservation, $oldPanels) {
                // Verrou pessimiste
                Panel::whereIn('id', $data['panel_ids'])
                    ->lockForUpdate()
                    ->get();

                $conflicts = $this->availability->getUnavailablePanelIds(
                    $data['panel_ids'],
                    $data['start_date'],
                    $data['end_date'],
                    $reservation->id
                );

                if (! empty($conflicts)) {
                    $refs = Panel::whereIn('id', $conflicts)
                        ->pluck('reference')->join(', ');
                    throw new \RuntimeException("CONFLICT:$refs");
                }

                $months = $this->monthsBetween($data['start_date'], $data['end_date']);
                $panelData = Panel::whereIn('id', $data['panel_ids'])->get()->keyBy('id');
                $sync  = [];
                $total = 0;

                foreach ($data['panel_ids'] as $panelId) {
                    $panel      = $panelData[$panelId];
                    $unitPrice  = (float) ($panel->monthly_rate ?? 0);
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
                $refs = substr($e->getMessage(), 9);
                return back()->withInput()
                    ->withErrors(['panel_ids' => "Conflit détecté : $refs"]);
            }
            throw $e;
        }

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'Réservation mise à jour.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        // Client supprimé → lecture seule totale, aucune action
        if ($reservation->client?->trashed()) {
            return back()->with('error',
                'Impossible : le client de cette réservation a été supprimé.');
        }
 
        $request->validate([
            'status' => 'required|in:' . implode(',',
                array_column(ReservationStatus::cases(), 'value')),
        ]);
 
        $newStatus = $request->status;
 
        // Vérification matrice
        if (! $reservation->canTransitionTo($newStatus)) {
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
        // Client supprimé → lecture seule
        if ($reservation->client?->trashed()) {
            abort(403, 'Impossible : client supprimé.');
        }
 
        if (! $reservation->isCancellable()) {
            abort(403, 'Cette réservation ne peut pas être annulée.');
        }
 
        $panelIds  = $reservation->panels->pluck('id')->toArray();
        $oldStatus = $reservation->status->value;
 
        $reservation->update(['status' => ReservationStatus::ANNULE]);
 
        // Libération immédiate des panneaux
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
            ->with('success',
                'Réservation annulée. '
                . count($panelIds) . ' panneau(x) libéré(s).');
    }
    public function destroy(Reservation $reservation)
    {
        if (! $reservation->isDeletable()) {
            abort(403,
                'Impossible de supprimer : la réservation est active ou liée à une campagne.');
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
 
        // Sync par précaution (panneaux déjà libres normalement)
        if (! empty($panelIds)) {
            $this->availability->syncPanelStatuses($panelIds);
        }
 
        return redirect()
            ->route('admin.reservations.index')
            ->with('success', 'Réservation supprimée définitivement.');
    }

    // ══════════════════════════════════════════════════════
    // API AJAX — Panneaux disponibles
    // ══════════════════════════════════════════════════════

    public function availablePanels(Request $request)
    {
        $request->validate([
            'start_date'   => 'required|date',
            'end_date'     => [
                'required',
                'date',
                'after:start_date',          // ← strictement après
            ],
            'format_width'  => 'nullable|numeric|min:0',
            'format_height' => 'nullable|numeric|min:0',
        ]);
    
        // Vérification croisée supplémentaire
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
                // Dimensions formatées : "4.00×3.00m" ou "4×3m" selon les données
                'dimensions'    => $p->format
                    ? self::formatDimensions($p->format->width, $p->format->height)
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
    
    /**
     * Formate les dimensions : 4.0×3.0m → "4×3m", 2.5×2.5m → "2.5×2.5m"
     */
    private static function formatDimensions(?float $w, ?float $h): ?string
    {
        if (! $w || ! $h) return null;
    
        // Supprimer les .0 inutiles : 4.0 → 4, 2.5 → 2.5
        $wStr = rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
        $hStr = rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.');
    
        return "{$wStr}×{$hStr}m";
    }
 

    // ══════════════════════════════════════════════════════
    // UTILITAIRE
    // ══════════════════════════════════════════════════════

    /**
     * Calcule le nombre de mois entre deux dates.
     * Arrondit au mois supérieur si reste > 0 jours.
     */
    private function monthsBetween(string $start, string $end): float
    {
        $s      = Carbon::parse($start)->startOfDay();
        $e      = Carbon::parse($end)->endOfDay();
        $months = (int) $s->diffInMonths($e);
        $remain = $s->copy()->addMonths($months)->diffInDays($e);

        // Si reste des jours → facturer un mois de plus
        $result = $remain > 0 ? $months + 1 : $months;

        return max((float) $result, 1.0);
    }
}