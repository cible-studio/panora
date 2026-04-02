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

use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;


use App\Services\AvailabilityService;
use App\Services\ReservationService;
use App\Services\PropositionService;


use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\ExternalPanel;
use App\Models\ExternalAgency;
use App\Enums\PanelStatus;
use App\Enums\ReservationType;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ReservationController extends Controller
{
    public function __construct(
        protected AvailabilityService $availability,
        protected ReservationService  $reservationService,
        protected PropositionService   $propositionService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // DISPONIBILITÉS — page principale (filtres uniquement)
    // ══════════════════════════════════════════════════════════════
    public function disponibilites(Request $request)
    {
        $communes   = Commune::orderBy('name')->get(['id', 'name']);
        $formats    = PanelFormat::orderBy('name')->get(['id', 'name', 'width', 'height']);
        $zones      = Zone::orderBy('name')->get(['id', 'name']);
        $clients    = Client::orderBy('name')->get(['id', 'name']);
        $agencies   = \App\Models\ExternalAgency::where('is_active', true)
                        ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        $dimensions = PanelFormat::whereNotNull('width')->whereNotNull('height')
            ->orderBy('width')->orderBy('height')->get(['width', 'height'])
            ->map(function ($f) {
                if (!$f->width || !$f->height) return null;
                $w = rtrim(rtrim(number_format($f->width,  2, '.', ''), '0'), '.');
                $h = rtrim(rtrim(number_format($f->height, 2, '.', ''), '0'), '.');
                return "{$w}×{$h}m";
            })->filter()->unique()->values();

        return view('admin.reservations.disponibilites',
            compact('communes', 'formats', 'zones', 'clients', 'dimensions', 'agencies'));
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — grille panneaux (inchangé — logique de formatage gardée)
    // ══════════════════════════════════════════════════════════════
    public function panneauxAjax(Request $request): \Illuminate\Http\JsonResponse
    {
        
        $startDate  = $request->dispo_du  ?: null;
        $endDate    = $request->dispo_au  ?: null;
        $statut     = $request->get('statut', 'tous');
        $source     = $request->get('source', 'all');   // 'internal'|'external'|'all'
        $search     = trim($request->get('q', ''));      // recherche texte libre
        $perPage    = min((int)$request->get('per_page', 48), 200); // pagination AJAX
        $page       = max((int)$request->get('page', 1), 1);

        // Filtres multi-valeurs — cast integer pour éviter le bug de type
        $communeIds = array_map('intval', array_filter((array)$request->get('commune_ids', [])));
        $zoneIds    = array_map('intval', array_filter((array)$request->get('zone_ids',    [])));
        $formatIds  = array_map('intval', array_filter((array)$request->get('format_ids', [])));
        $agencyIds  = array_map('intval', array_filter((array)$request->get('agency_ids', [])));
        $isLit      = $request->input('is_lit', '');

        $dateError = null;
        if ($startDate && $endDate && $endDate <= $startDate) {
            $dateError = 'La date de fin doit être après la date de début.';
        } elseif ($startDate && !$endDate) {
            $dateError = 'Veuillez renseigner la date de fin.';
        } elseif (!$startDate && $endDate) {
            $dateError = 'Veuillez renseigner la date de début.';
        }

        $internalResult = collect();
        $externalResult = collect();
        $occupiedIds    = collect();
        $optionIds      = collect();
        $releaseDates   = collect();

        // ══ PANNEAUX INTERNES ════════════════════════════════════════
        if (in_array($source, ['internal', 'all'])) {

            $query = Panel::with([
                    'commune:id,name',
                    'format:id,name,width,height',
                    'zone:id,name',
                    'category:id,name',
                    'photos',
                ])
                ->whereNull('deleted_at')
                ->select([
                    'id', 'reference', 'name', 'commune_id', 'zone_id', 'format_id',
                    'category_id', 'status', 'is_lit', 'monthly_rate',
                    'daily_traffic', 'zone_description',
                ]);

            // Filtres scalaires
            if (!empty($communeIds)) $query->whereIn('commune_id', $communeIds);
            if (!empty($zoneIds))    $query->whereIn('zone_id',    $zoneIds);
            if (!empty($formatIds))  $query->whereIn('format_id',  $formatIds);
            if ($isLit === '1')      $query->where('is_lit', true);
            elseif ($isLit === '0')  $query->where('is_lit', false);

            // Recherche texte libre — LIKE sur référence + nom + zone_description
            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(fn($q) =>
                    $q->where('reference', 'like', $like)
                    ->orWhere('name',    'like', $like)
                    ->orWhere('zone_description', 'like', $like)
                );
            }

            // Dimensions
            if ($request->filled('dimensions')) {
                [$w, $h] = self::parseDimensions($request->dimensions);
                if ($w !== null) {
                    $query->whereHas('format', fn($q) =>
                        $q->whereBetween('width',  [$w - 0.01, $w + 0.01])
                        ->whereBetween('height', [$h - 0.01, $h + 0.01])
                    );
                }
            }

            // Statut DB direct (sans période)
            if (!$dateError && in_array($statut, ['maintenance', 'confirme'])) {
                $query->where('status', $statut);
            } elseif (!$dateError && $statut === 'libre' && (!$startDate || !$endDate)) {
                $query->where('status', 'libre');
            }

            // Statuts qui nécessitent une période — on bloque sans dates
            if (in_array($statut, ['occupe', 'option']) && (!$startDate || !$endDate)) {
                $internalResult = collect();
                $dateError = $dateError ?: 'Saisissez une période pour filtrer par '
                    . ($statut === 'option' ? 'Option' : 'Occupé') . '.';
            } else {
                $panels = $query->orderBy('reference')->get();

                // Calcul occupation — une seule requête groupée pour tous les IDs
                if ($startDate && $endDate && !$dateError && $panels->isNotEmpty()) {
                    $allIds = $panels->pluck('id');

                    // Requête unique : récupère à la fois occupé + option + release_date
                    $bookings = ReservationPanel::whereIn('panel_id', $allIds)
                        ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                        ->whereIn('reservations.status', [
                            ReservationStatus::CONFIRME->value,
                            ReservationStatus::EN_ATTENTE->value,
                        ])
                        ->where('reservations.start_date', '<', $endDate)
                        ->where('reservations.end_date',   '>', $startDate)
                        ->select(
                            'reservation_panels.panel_id',
                            'reservations.status',
                            DB::raw('MAX(reservations.end_date) as release_date')
                        )
                        ->groupBy('reservation_panels.panel_id', 'reservations.status')
                        ->get();

                    $occupiedIds  = $bookings->where('status', ReservationStatus::CONFIRME->value)
                                            ->pluck('panel_id')->unique();
                    $optionIds    = $bookings->where('status', ReservationStatus::EN_ATTENTE->value)
                                            ->pluck('panel_id')->unique();
                    $releaseDates = $bookings->groupBy('panel_id')
                                            ->map(fn($g) => $g->max('release_date'));
                }

                // Post-filtrage statut période
                if (!$dateError && $startDate && $endDate) {
                    $panels = match($statut) {
                        'occupe' => $panels->filter(fn($p) =>
                            $occupiedIds->contains($p->id) || $optionIds->contains($p->id)
                        )->values(),
                        'option' => $panels->filter(fn($p) =>
                            $optionIds->contains($p->id)
                        )->values(),
                        'libre'  => $panels->filter(fn($p) =>
                            !$occupiedIds->contains($p->id) &&
                            !$optionIds->contains($p->id) &&
                            $p->status->value !== 'maintenance'
                        )->values(),
                        default  => $panels,
                    };
                }

                $now = Carbon::now()->startOfDay();
                $internalResult = $panels->map(fn($panel) =>
                    self::formatInternalPanel($panel, $occupiedIds, $optionIds, $releaseDates, $startDate, $endDate, $dateError, $now)
                );
            }
        }

        // ══ PANNEAUX EXTERNES ════════════════════════════════════════
        if (in_array($source, ['external', 'all']) && !$dateError) {

            $extQuery = \App\Models\ExternalPanel::with([
                    'agency:id,name',
                    'commune:id,name',
                    'zone:id,name',
                    'format:id,name,width,height',
                ])
                ->whereNull('external_panels.deleted_at')
                ->whereHas('agency', fn($q) =>
                    $q->where('is_active', true)->whereNull('deleted_at')
                );

            if (!empty($communeIds)) $extQuery->whereIn('commune_id', $communeIds);
            if (!empty($zoneIds))    $extQuery->whereIn('zone_id',    $zoneIds);
            if (!empty($formatIds))  $extQuery->whereIn('format_id',  $formatIds);
            if (!empty($agencyIds))  $extQuery->whereIn('agency_id',  $agencyIds); // FIX: cast intval
            if ($isLit === '1')      $extQuery->where('is_lit', true);
            elseif ($isLit === '0')  $extQuery->where('is_lit', false);

            if ($search !== '') {
                $like = '%' . $search . '%';
                $extQuery->where(fn($q) =>
                    $q->where('code_panneau', 'like', $like)
                    ->orWhere('designation', 'like', $like)
                );
            }

            // Statuts externes
            if ($statut === 'libre') {
                $extQuery->where('availability_status', 'disponible');
            } elseif ($statut === 'occupe') {
                $extQuery->where('availability_status', 'occupe');
            } elseif (in_array($statut, ['maintenance', 'confirme', 'option'])) {
                $extQuery->whereRaw('1=0'); // pas de résultat pour ces statuts
            }

            $extPanels      = $extQuery->orderBy('code_panneau')->get();
            $externalResult = $extPanels->map(fn($p) =>
                self::formatExternalPanel($p, $startDate, $endDate)
            );
        }

        // ══ FUSION + PAGINATION LÉGÈRE ═══════════════════════════════
        $allPanels = $internalResult->merge($externalResult)->values();
        $total     = $allPanels->count();

        // Pagination côté serveur (évite d'envoyer 1000 cartes au navigateur)
        $paginated = $allPanels->forPage($page, $perPage)->values();

        $stats = [
            'total'       => $total,
            'displayed'   => $paginated->count(),
            'disponibles' => $allPanels->where('display_status', 'libre')->count(),
            'occupes'     => $allPanels->whereIn('display_status', ['occupe', 'option_periode'])->count(),
            'options'     => $allPanels->where('display_status', 'option_periode')->count(),
            'maintenance' => $allPanels->where('display_status', 'maintenance')->count(),
            'externes'    => $externalResult->count(),
            'internes'    => $internalResult->count(),
            'a_verifier'  => $allPanels->where('display_status', 'a_verifier')->count(),
            'pages'       => (int) ceil($total / $perPage),
            'current_page'=> $page,
        ];

        return response()->json([
            'panels'      => $paginated,
            'stats'       => $stats,
            'date_error'  => $dateError,
            'has_period'  => (bool)($startDate && $endDate && !$dateError),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    //  HELPERS PRIVÉS
    // ──────────────────────────────────────────────────────────────────

    private static function formatInternalPanel(
        $panel, $occupiedIds, $optionIds, $releaseDates,
        $startDate, $endDate, $dateError, $now
    ): array {
        $isOccupied = $occupiedIds->contains($panel->id);
        $isOption   = $optionIds->contains($panel->id);

        $displayStatus = match(true) {
            $panel->status->value === 'maintenance'             => 'maintenance',
            $isOccupied && $startDate && $endDate && !$dateError => 'occupe',
            $isOption   && $startDate && $endDate && !$dateError => 'option_periode',
            default                                              => $panel->status->value,
        };

        $releaseInfo = null;
        if ($isOccupied || $isOption) {
            $rdRaw = $releaseDates->get($panel->id);
            if ($rdRaw) {
                $rd       = Carbon::parse($rdRaw)->startOfDay();
                $daysLeft = (int)$now->diffInDays($rd, false);
                $releaseInfo = [
                    'date'      => $rd->format('d/m/Y'),
                    'days_left' => $daysLeft,
                    'label'     => match(true) {
                        $daysLeft === 0  => "Libre aujourd'hui",
                        $daysLeft === 1  => 'Libre demain',
                        $daysLeft > 0    => "Libre le {$rd->format('d/m/Y')} ({$daysLeft}j)",
                        default          => 'Date passée',
                    },
                    'color'     => $daysLeft <= 0 ? 'green' : ($daysLeft <= 7 ? 'orange' : 'default'),
                ];
            }
        }

        $dims = self::buildDims($panel->format);

        // Récupérer la première photo du panneau
        $photo = $panel->relationLoaded('photos')
            ? $panel->photos->sortBy('ordre')->first()
            : null;
        $photoUrl = $photo
            ? asset('storage/' . ltrim($photo->path, '/'))
            : null;

        return [
            'id'               => $panel->id,
            'source'           => 'internal',
            'reference'        => $panel->reference,
            'name'             => $panel->name,
            'commune'          => $panel->commune?->name    ?? '—',
            'commune_id'       => $panel->commune_id,
            'zone'             => $panel->zone?->name       ?? '—',
            'zone_id'          => $panel->zone_id,
            'format'           => $panel->format?->name     ?? '—',
            'format_id'        => $panel->format_id,
            'dimensions'       => $dims,
            'category'         => $panel->category?->name   ?? '—',
            'agency_name'      => null,
            'agency_id'        => null,
            'is_lit'           => (bool)$panel->is_lit,
            'monthly_rate'     => (float)($panel->monthly_rate ?? 0),
            'daily_traffic'    => (int)($panel->daily_traffic  ?? 0),
            'zone_description' => $panel->zone_description    ?? '',
            'status_db'        => $panel->status->value,
            'display_status'   => $displayStatus,
            'is_selectable'    => $displayStatus === 'libre',
            'release_info'     => $releaseInfo,
            'card_color_idx'   => abs(crc32($panel->reference)) % 6,
            'photo_url'        => $photoUrl,
        ];
    }

    private static function formatExternalPanel($panel, $startDate, $endDate): array
    {
        $displayStatus = match($panel->getDisplayStatusForPeriod($startDate, $endDate)) {
            'disponible' => 'libre',
            'occupe'     => 'occupe',
            default      => 'a_verifier',
        };

        $releaseInfo = null;
        if ($displayStatus === 'occupe' && $panel->available_from) {
            $rd       = $panel->available_from;
            $daysLeft = (int)now()->startOfDay()->diffInDays($rd->startOfDay(), false);
            $releaseInfo = [
                'date'      => $rd->format('d/m/Y'),
                'days_left' => $daysLeft,
                'label'     => $daysLeft <= 0 ? 'Disponible bientôt'
                            : ($daysLeft === 1 ? 'Libre demain'
                            : "Libre le {$rd->format('d/m/Y')} ({$daysLeft}j)"),
                'color'     => $daysLeft <= 0 ? 'green' : ($daysLeft <= 7 ? 'orange' : 'default'),
            ];
        }

        return [
            'id'               => 'ext_' . $panel->id,
            'source'           => 'external',
            'reference'        => $panel->code_panneau,
            'name'             => $panel->designation,
            'commune'          => $panel->commune?->name  ?? '—',
            'commune_id'       => $panel->commune_id,
            'zone'             => $panel->zone?->name     ?? '—',
            'zone_id'          => $panel->zone_id,
            'format'           => $panel->format?->name   ?? '—',
            'format_id'        => $panel->format_id,
            'dimensions'       => self::buildDims($panel->format),
            'category'         => $panel->type             ?? '—',
            'agency_name'      => $panel->agency?->name    ?? '—',
            'agency_id'        => $panel->agency_id,
            'is_lit'           => (bool)$panel->is_lit,
            'monthly_rate'     => (float)($panel->monthly_rate ?? 0),
            'daily_traffic'    => (int)($panel->daily_traffic  ?? 0),
            'zone_description' => $panel->zone_description ?? '',
            'status_db'        => $panel->availability_status,
            'display_status'   => $displayStatus,
            'is_selectable'    => in_array($displayStatus, ['libre', 'a_verifier']),
            'release_info'     => $releaseInfo,
            'card_color_idx'   => abs(crc32($panel->code_panneau)) % 6,
        ];
    }

    private static function buildDims($format): ?string
    {
        if (!$format?->width || !$format?->height) return null;
        $w = rtrim(rtrim(number_format($format->width,  2, '.', ''), '0'), '.');
        $h = rtrim(rtrim(number_format($format->height, 2, '.', ''), '0'), '.');
        return "{$w}×{$h}m";
    }


    // ── Confirmer sélection ───────────────────────────────
    public function confirmerSelection(Request $request)
    {
        // ── Séparer IDs internes et externes ─────────────────────────
        $rawIds      = (array)$request->input('panel_ids', []);
        $internalIds = [];
        $externalIds = [];

        foreach ($rawIds as $id) {
            if (is_string($id) && str_starts_with($id, 'ext_')) {
                $externalIds[] = (int)substr($id, 4);
            } elseif (is_numeric($id)) {
                $internalIds[] = (int)$id;
            }
        }

        // ── Validation — uniquement les panneaux internes sont réservés
        $request->merge(['panel_ids' => $internalIds]);


        $request->validate([
            'client_id'     => 'required|exists:clients,id',
            'start_date'    => [
                'required',
                'date',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) {
                    if ($value < now()->subDay()->format('Y-m-d')) {
                        $fail('La date de début ne peut pas être dans le passé.');
                    }
                },
            ],
            'end_date'      => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after:start_date',
            ],
            'notes'         => 'nullable|string|max:2000',
            'panel_ids'     => 'required|array|min:1|max:50',
            'panel_ids.*'   => 'required|integer|exists:panels,id',
            'type'          => 'required|in:option,ferme',
            'campaign_name' => 'nullable|string|max:150',
        ], [
            // Messages en français
            'client_id.required'    => 'Veuillez sélectionner un client.',
            'client_id.exists'      => 'Client invalide.',
            'start_date.required'   => 'La date de début est obligatoire.',
            'start_date.date'       => 'Format de date invalide.',
            'end_date.required'     => 'La date de fin est obligatoire.',
            'end_date.date'         => 'Format de date invalide.',
            'end_date.after'        => 'La date de fin doit être après la date de début.',
            'panel_ids.required'    => 'Sélectionnez au moins un panneau.',
            'panel_ids.min'         => 'Sélectionnez au moins un panneau.',
            'panel_ids.max'         => 'Maximum 50 panneaux par réservation.',
            'panel_ids.*.exists'    => 'Un panneau sélectionné est invalide.',
            'type.required'         => 'Le type de réservation est obligatoire.',
            'type.in'               => 'Type de réservation invalide.',
            'campaign_name.max'     => 'Le nom de campagne ne doit pas dépasser 150 caractères.',
        ]);

        // ── Vérifications hors transaction ───────────────────────────
        $maintenancePanels = Panel::whereIn('id', $internalIds)
            ->where('status', PanelStatus::MAINTENANCE->value)
            ->pluck('reference');

        if ($maintenancePanels->isNotEmpty()) {
            return back()->withErrors([
                'panel_ids' => 'Panneaux en maintenance : ' . $maintenancePanels->join(', '),
            ])->withInput();
        }

        $createdCampaignId = null;

        try {
            DB::transaction(function () use ($request, $internalIds, &$createdCampaignId) {

                // 🔒 Verrou pessimiste — évite la race condition
                Panel::whereIn('id', $internalIds)->lockForUpdate()->get();

                // Source de vérité — vérifie les conflits APRÈS verrouillage
                $conflicts = $this->availability->getUnavailablePanelIds(
                    $internalIds,
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

                // Référence unique
                $reference = $this->generateUniqueReference();

                // Calcul montant
                $panelData = Panel::whereIn('id', $internalIds)->get()->keyBy('id');
                $months    = $this->monthsBetween($request->start_date, $request->end_date);
                $total     = 0;
                $attach    = [];

                foreach ($internalIds as $panelId) {
                    $panel     = $panelData[$panelId];
                    $unit      = (float)($panel->monthly_rate ?? 0);
                    $tot       = $unit * $months;
                    $total    += $tot;
                    $attach[$panelId] = ['unit_price' => $unit, 'total_price' => $tot];
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

                $reservation->panels()->attach($attach);

                // Sync cache panels.status
                $this->availability->syncPanelStatuses($internalIds);

                // Campagne automatique si ferme + nom
                if ($request->type === 'ferme' && $request->filled('campaign_name')) {
                    $exists = \App\Models\Campaign::where('client_id', $request->client_id)
                        ->where('name', $request->campaign_name)
                        ->exists();
                    if ($exists) {
                        throw new \RuntimeException(
                            'CAMPAIGN_EXISTS:Une campagne avec ce nom existe déjà pour ce client.'
                        );
                    }
                    $campaign = \App\Models\Campaign::create([
                        'name'           => $request->campaign_name,
                        'client_id'      => $request->client_id,
                        'reservation_id' => $reservation->id,
                        'user_id'        => auth()->id(),
                        'start_date'     => $request->start_date,
                        'end_date'       => $request->end_date,
                        'status'         => \App\Enums\CampaignStatus::ACTIF->value,
                        'total_panels'   => count($internalIds),
                        'total_amount'   => $total,
                        'notes'          => $request->notes,
                    ]);
                    $campaign->panels()->sync(array_keys($attach));
                    $createdCampaignId = $campaign->id;
                }

                Log::info('reservation.created', [
                    'reservation_id' => $reservation->id,
                    'reference'      => $reference,
                    'type'           => $request->type,
                    'panel_count'    => count($internalIds),
                    'ext_count'      => count($externalIds ?? []),
                    'user_id'        => auth()->id(),
                ]);
            });

        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:')) {
                return back()->withErrors([
                    'panel_ids' => 'Conflit détecté : ' . substr($e->getMessage(), 9),
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
                ->with('success', 'Réservation ferme créée et campagne lancée. ✅');
        }

        return redirect()
            ->route('admin.reservations.disponibilites')
            ->with('success', $request->type === 'ferme'
                ? 'Réservation ferme créée. Panneaux confirmés.'
                : 'Panneaux mis sous option.'
            );
    }

    // ── Helper : référence unique ─────────────────────────────────────
    private function generateUniqueReference(): string
    {
        $attempts = 0;
        do {
            $candidate = 'RES-' . strtoupper(Str::random(8));
            $attempts++;
            if ($attempts > 10) {
                $candidate = 'RES-' . strtoupper(substr(str_replace('-', '', (string)Str::uuid()), 0, 8));
            }
            if ($attempts > 20) {
                throw new \RuntimeException('SYSTEM:Référence impossible à générer.');
            }
        } while (Reservation::where('reference', $candidate)->exists());

        return $candidate;
    }

    // ══════════════════════════════════════════════════════════════════
    // MÉTHODE monthsBetween
    // ══════════════════════════════════════════════════════════════════
    private function monthsBetween(string $start, string $end): float
    {
        $s      = Carbon::parse($start)->startOfDay();
        $e      = Carbon::parse($end)->endOfDay();
        $months = (int)$s->diffInMonths($e);
        $remain = $s->copy()->addMonths($months)->diffInDays($e);
        return max((float)($remain > 0 ? $months + 1 : $months), 1.0);
    }

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Reservation::with(['client', 'user'])->withCount('panels');

        if ($request->search) {
            $query->where(fn($q) =>
                $q->where('reference', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn($q) => $q->withTrashed()
                      ->where('name', 'like', "%{$request->search}%"))
            );
        }
        if ($request->status)    $query->where('status', $request->status);
        if ($request->type)      $query->where('type', $request->type);
        if ($request->client_id) $query->where('client_id', $request->client_id);

        if ($request->periode) {
            match($request->periode) {
                'this_month'    => $query->whereMonth('created_at', now()->month)
                                         ->whereYear('created_at', now()->year),
                'last_month'    => $query->whereMonth('created_at', now()->subMonth()->month)
                                         ->whereYear('created_at', now()->subMonth()->year),
                'this_quarter'  => $query->whereBetween('created_at',
                                       [now()->startOfQuarter(), now()->endOfQuarter()]),
                'this_year'     => $query->whereYear('created_at', now()->year),
                default         => null,
            };
        }

        $reservations = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $rawCounts = Reservation::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
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

        if ($request->ajax()) {
            return response()->json([
                'html'       => view('admin.reservations.partials.table-rows',
                                    compact('reservations', 'lastSeenAt'))->render(),
                'pagination' => $reservations->links()->render(),
                'stats'      => $counts,
                'has_more'   => $reservations->hasMorePages(),
            ]);
        }

        return view('admin.reservations.index',
            compact('reservations', 'clients', 'statuses', 'counts', 'lastSeenAt', 'newCount'));
    }

    public function markSeen()
    {
        auth()->user()->update(['reservations_last_seen_at' => now()]);
        return response()->json(['ok' => true]);
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════
    public function show(Reservation $reservation)
    {
        $reservation->load(['client', 'user', 'panels.commune', 'panels.format', 'campaign']);

        $user = auth()->user();
        $can  = [
            'update'       => $reservation->isEditable()      && $user->can('update', $reservation),
            'updateStatus' => $reservation->canChangeStatus()  && $user->can('updateStatus', $reservation),
            'annuler'      => $reservation->isCancellable()    && $user->can('annuler', $reservation),
            'delete'       => $reservation->isDeletable()      && $user->can('delete', $reservation),
        ];

        return view('admin.reservations.show', compact('reservation', 'can'));
    }

    // ══════════════════════════════════════════════════════════════
    // EDIT
    // ══════════════════════════════════════════════════════════════
    public function edit(Reservation $reservation)
    {
        if (!$reservation->isEditable()) {
            abort(403, 'Cette réservation ne peut plus être modifiée ('
                . $reservation->status->label() . ').');
        }

        $reservation->load('panels');
        $clients    = Client::orderBy('name')->get();
        $communes   = Commune::orderBy('name')->get();
        $formats    = PanelFormat::orderBy('name')->get();
        $zones      = Zone::orderBy('name')->get();
        $dimensions = PanelFormat::whereNotNull('width')->whereNotNull('height')
            ->orderBy('width')->orderBy('height')->get()
            ->map(function ($f) {
                if (!$f->width || !$f->height) return null;
                $w = rtrim(rtrim(number_format($f->width,  2, '.', ''), '0'), '.');
                $h = rtrim(rtrim(number_format($f->height, 2, '.', ''), '0'), '.');
                return "{$w}×{$h}m";
            })->filter()->unique()->values();

        $selectedPanelIds = $reservation->panels->pluck('id')->toArray();

        return view('admin.reservations.edit',
            compact('reservation', 'clients', 'communes', 'formats',
                    'zones', 'selectedPanelIds', 'dimensions'));
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE — délégué à ReservationService
    // ══════════════════════════════════════════════════════════════
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
                'Cette réservation a été modifiée par un autre utilisateur. Rechargez la page.');
        }

        $oldPanels = $reservation->panels->pluck('id')->toArray();

        try {
            $this->reservationService->updateReservation(
                $reservation,
                $request->validated(),
                $oldPanels
            );
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:')) {
                return back()->withInput()
                    ->withErrors(['panel_ids' => 'Conflit : ' . substr($e->getMessage(), 9)]);
            }
            throw $e;
        }

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'Réservation mise à jour.');
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE STATUS — délégué à ReservationService
    // ══════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, Reservation $reservation)
    {
        if ($reservation->client?->trashed()) {
            return back()->with('error', 'Impossible : client supprimé.');
        }

        $request->validate([
            'status' => 'required|in:' . implode(',',
                array_column(ReservationStatus::cases(), 'value')),
        ]);

        if (!$reservation->canTransitionTo($request->status)) {
            return back()->with('error',
                "Transition interdite : {$reservation->status->value} → {$request->status}.");
        }

        $this->reservationService->changeStatus($reservation, $request->status);

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', "Statut mis à jour : {$request->status}.");
    }

    // ══════════════════════════════════════════════════════════════
    // ANNULER — délégué à ReservationService
    // ══════════════════════════════════════════════════════════════
    public function annuler(Reservation $reservation)
    {
        if ($reservation->client?->trashed()) abort(403, 'Impossible : client supprimé.');
        if (!$reservation->isCancellable())   abort(403, 'Réservation non annulable.');

        $panelCount = $reservation->panels->count();
        $this->reservationService->cancel($reservation);

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', "Réservation annulée. {$panelCount} panneau(x) libéré(s).");
    }

    // ══════════════════════════════════════════════════════════════
    // DESTROY — délégué à ReservationService
    // ══════════════════════════════════════════════════════════════
    public function destroy(Reservation $reservation)
    {
        if (!$reservation->isDeletable()) {
            abort(403, 'Impossible : réservation active ou liée à une campagne.');
        }

        $panelCount   = $reservation->panels()->count();
        $hasCampaign  = $reservation->campaign !== null;

        try {
            $this->reservationService->delete($reservation);
        } catch (\Exception $e) {
            Log::error('reservation.deletion_failed', [
                'reservation_id' => $reservation->id,
                'error'          => $e->getMessage(),
                'user_id'        => auth()->id(),
            ]);
            return back()->with('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.reservations.index')
            ->with('success', 'Réservation supprimée. '
                . ($hasCampaign ? 'Campagne liée annulée. ' : '')
                . "{$panelCount} panneau(x) libéré(s).");
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — panneaux disponibles pour la page edit (inchangé)
    // ══════════════════════════════════════════════════════════════
    public function availablePanels(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'start_date'             => 'required|date',
                'end_date'               => 'required|date|after:start_date',
                'exclude_reservation_id' => 'nullable|integer|exists:reservations,id',
                'commune_id'             => 'nullable|integer|exists:communes,id',
                'zone_id'                => 'nullable|integer|exists:zones,id',
                'format_id'              => 'nullable|integer|exists:panel_formats,id',
                'dimensions'             => 'nullable|string|max:20',
                'is_lit'                 => 'nullable|in:0,1',
            ]);

            $startDate = $request->start_date;
            $endDate   = $request->end_date;
            $excludeId = $request->exclude_reservation_id
                ? (int)$request->exclude_reservation_id
                : null;

            // ── Construire la requête panneaux ────────────────────────
            $query = Panel::with([
                    'commune:id,name',
                    'format:id,name,width,height',
                    'zone:id,name',
                ])
                ->whereNull('deleted_at')
                ->where('status', '!=', PanelStatus::MAINTENANCE->value)
                ->select(['id','reference','name','commune_id','zone_id',
                        'format_id','is_lit','monthly_rate','daily_traffic',
                        'zone_description']);

            // Filtres
            if ($request->filled('commune_id')) {
                $query->where('commune_id', (int)$request->commune_id);
            }
            if ($request->filled('zone_id')) {
                $query->where('zone_id', (int)$request->zone_id);
            }
            if ($request->filled('format_id')) {
                $query->where('format_id', (int)$request->format_id);
            }
            if ($request->filled('is_lit') && $request->is_lit !== '') {
                $query->where('is_lit', $request->is_lit === '1');
            }
            if ($request->filled('dimensions')) {
                [$w, $h] = $this->parseDimensions($request->dimensions);
                if ($w !== null && $h !== null) {
                    $query->whereHas('format', fn($q) =>
                        $q->whereBetween('width',  [$w - 0.01, $w + 0.01])
                        ->whereBetween('height', [$h - 0.01, $h + 0.01])
                    );
                }
            }

            $panels    = $query->orderBy('reference')->get();
            $panelIds  = $panels->pluck('id')->toArray();

            // ── Source de vérité : availability data ─────────────────
            // PAS panels.status — on calcule depuis reservation_panels
            $availabilityData = $this->availability->getPanelAvailabilityData(
                $panelIds,
                $startDate,
                $endDate,
                $excludeId
            );

            return response()->json(
                $panels->map(function ($p) use ($availabilityData, $startDate, $endDate) {
                    $avail       = $availabilityData->get($p->id, ['available' => true, 'release_date' => null, 'blocking_status' => null]);
                    $isAvailable = $avail['available'];
                    $releaseDate = $avail['release_date'];
                    $releaseFmt  = null;

                    if ($releaseDate) {
                        $rd         = \Carbon\Carbon::parse($releaseDate);
                        $daysLeft   = (int)now()->startOfDay()->diffInDays($rd->startOfDay(), false);
                        $releaseFmt = match(true) {
                            $daysLeft <= 0  => 'Libre maintenant',
                            $daysLeft === 1 => 'Libre demain',
                            default         => 'Libre le ' . $rd->format('d/m/Y') . ' (dans ' . $daysLeft . 'j)',
                        };
                    }

                    return [
                        'id'              => $p->id,
                        'reference'       => $p->reference,
                        'name'            => $p->name,
                        'commune'         => $p->commune?->name ?? '—',
                        'zone'            => $p->zone?->name    ?? '—',
                        'format'          => $p->format?->name  ?? '—',
                        'dimensions'      => $this->formatDimensions($p->format?->width, $p->format?->height),
                        'is_lit'          => (bool)$p->is_lit,
                        'monthly_rate'    => (float)($p->monthly_rate ?? 0),
                        'daily_traffic'   => (int)($p->daily_traffic ?? 0),
                        // ← source de vérité, pas panels.status
                        'available'       => $isAvailable,
                        'release_date'    => $releaseFmt,
                        'blocking_status' => $avail['blocking_status'],
                    ];
                })
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('availablePanels.error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Erreur serveur'], 500);
        }
    }

    // Ces méthodes sont utilisées par availablePanels()
    private function parseDimensions(string $dim): array
    {
        $clean = str_replace('m', '', trim($dim));
        foreach (['×', 'x', 'X'] as $sep) {
            if (str_contains($clean, $sep)) {
                $parts = explode($sep, $clean, 2);
                if (count($parts) === 2
                    && is_numeric(trim($parts[0]))
                    && is_numeric(trim($parts[1]))) {
                    return [(float)trim($parts[0]), (float)trim($parts[1])];
                }
            }
        }
        return [null, null];
    }

    private function formatDimensions(?float $w, ?float $h): ?string
    {
        if (!$w || !$h) return null;
        $ws = rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.');
        $hs = rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.');
        return "{$ws}×{$hs}m";
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORT DISPONIBILITÉS (Excel / CSV / PDF liste / PDF images)
    // ══════════════════════════════════════════════════════════════
    public function exportDisponibilites(Request $request)
    {
        $request->merge(['per_page' => 9999, 'page' => 1]);
        $json     = $this->panneauxAjax($request);
        $data     = json_decode($json->getContent(), true);
        $panels   = collect($data['panels'] ?? []);
        $format   = $request->get('format', 'csv');
        $filename = 'disponibilites-' . now()->format('Y-m-d');
    
        switch ($format) {
    
            // ── CSV (séparateur point-virgule, compatible Excel FR) ──────
            case 'csv':
                $headers = [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename={$filename}.csv",
                    'Cache-Control'       => 'no-cache, must-revalidate',
                ];
                $callback = function () use ($panels) {
                    $handle = fopen('php://output', 'w');
                    fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 pour Excel FR
    
                    // En-tête
                    fputcsv($handle, [
                        'Référence', 'Désignation', 'Commune', 'Zone',
                        'Format', 'Dimensions', 'Éclairé', 'Catégorie',
                        'Tarif/mois (FCFA)', 'Trafic/jour', 'Statut',
                        'Date libération',
                    ], ';');
    
                    foreach ($panels as $p) {
                        fputcsv($handle, [
                            $p['reference'],
                            $p['name'],
                            $p['commune'],
                            $p['zone'],
                            $p['format'],
                            $p['dimensions'] ?? '',
                            $p['is_lit'] ? 'Oui' : 'Non',
                            $p['category'] ?? '',
                            $p['monthly_rate'] ?? 0,
                            $p['daily_traffic'] ?? 0,
                            match($p['display_status']) {
                                'libre'          => 'Disponible',
                                'occupe'         => 'Occupé',
                                'option_periode' => 'En option',
                                'option'         => 'En option',
                                'maintenance'    => 'Maintenance',
                                default          => ucfirst($p['display_status']),
                            },
                            $p['release_info']['date'] ?? '',
                        ], ';');
                    }
                    fclose($handle);
                };
                return response()->stream($callback, 200, $headers);
    
            // ── EXCEL .xlsx via PhpSpreadsheet (si installé) ─────────────
            // ou fallback vers XLS HTML si PhpSpreadsheet non disponible
            case 'excel':
                // Tentative PhpSpreadsheet
                if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                    return $this->_exportExcelPhpSpreadsheet($panels, $filename);
                }
                // Fallback : Excel HTML (compatible Excel/LibreOffice)
                return $this->_exportExcelHtml($panels, $filename);
    
            // ── PDF LISTE ─────────────────────────────────────────────────
            case 'pdf_list':
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
                    'admin.reservations.pdf.disponibilites-list',
                    [
                        'panels'    => $panels,
                        'generated' => now()->format('d/m/Y H:i'),
                        'total'     => $panels->count(),
                        'disponibles' => $panels->where('display_status', 'libre')->count(),
                        'occupes'     => $panels->whereIn('display_status', ['occupe','option_periode'])->count(),
                    ]
                )->setPaper('a4', 'landscape');
                return $pdf->download("{$filename}-liste.pdf");
    
            // ── PDF AVEC IMAGES ───────────────────────────────────────────
            case 'pdf_images':
                $internalIds = $panels
                    ->where('source', 'internal')
                    ->pluck('id')
                    ->filter(fn($id) => is_numeric($id))
                    ->toArray();
    
                // Charger les photos depuis la BD avec chemin absolu
                $panelsWithPhotos = \App\Models\Panel::with(['photos' => fn($q) => $q->orderBy('ordre')])
                    ->whereIn('id', $internalIds)
                    ->get()
                    ->keyBy('id');
    
                // Enrichir avec photo_path absolu (DomPDF ne lit pas les URLs HTTP)
                $enrichedPanels = $panels->map(function ($p) use ($panelsWithPhotos) {
                    $p['photo_path'] = null;
                    if ($p['source'] === 'internal' && isset($panelsWithPhotos[$p['id']])) {
                        $model = $panelsWithPhotos[$p['id']];
                        $photo = $model->photos->first();
                        if ($photo) {
                            // Construire le chemin absolu vers le fichier
                            $relativePath = ltrim($photo->path, '/');
                            // Essayer les deux chemins possibles
                            $path1 = storage_path('app/public/' . $relativePath);
                            $path2 = public_path('storage/' . $relativePath);
                            if (file_exists($path1)) {
                                $p['photo_path'] = $path1;
                            } elseif (file_exists($path2)) {
                                $p['photo_path'] = $path2;
                            }
                        }
                    }
                    return $p;
                });
    
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
                    'admin.reservations.pdf.disponibilites-images',
                    [
                        'panels'    => $enrichedPanels,
                        'generated' => now()->format('d/m/Y H:i'),
                    ]
                )
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isRemoteEnabled'    => false, // désactivé — on utilise chemins locaux
                    'isHtml5ParserEnabled' => true,
                    'defaultFont'        => 'DejaVu Sans',
                    'chroot'             => storage_path('app/public'), // sécurité DomPDF
                ]);
    
                return $pdf->download("{$filename}-images.pdf");
    
            default:
                abort(400, 'Format non supporté. Valeurs acceptées : csv, excel, pdf_list, pdf_images');
        }
    }

    /**
     * Export Excel via PhpSpreadsheet (composer require phpoffice/phpspreadsheet)
     * Produit un vrai .xlsx avec mise en forme professionnelle
     */
    private function _exportExcelPhpSpreadsheet(\Illuminate\Support\Collection $panels, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Disponibilités');
    
        // ── En-tête ───────────────────────────────────────────────────
        $headers = [
            'A' => 'Référence',
            'B' => 'Désignation',
            'C' => 'Commune',
            'D' => 'Zone',
            'E' => 'Format',
            'F' => 'Dimensions',
            'G' => 'Éclairé',
            'H' => 'Catégorie',
            'I' => 'Tarif/mois (FCFA)',
            'J' => 'Trafic/jour',
            'K' => 'Statut',
            'L' => 'Date libération',
        ];
    
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }
    
        // Style en-tête
        $headerRange = 'A1:L1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
    
        // ── Données ────────────────────────────────────────────────────
        $statusLabels = [
            'libre'          => 'Disponible',
            'occupe'         => 'Occupé',
            'option_periode' => 'En option',
            'option'         => 'En option',
            'maintenance'    => 'Maintenance',
            'confirme'       => 'Confirmé',
        ];
    
        $statusColors = [
            'libre'          => '166534', // vert foncé
            'occupe'         => '991B1B', // rouge foncé
            'option_periode' => '92400E', // ambre foncé
            'option'         => '92400E',
            'maintenance'    => '374151', // gris
            'confirme'       => '5B21B6', // violet
        ];
    
        $row = 2;
        foreach ($panels as $p) {
            $sheet->setCellValue('A' . $row, $p['reference']);
            $sheet->setCellValue('B' . $row, $p['name']);
            $sheet->setCellValue('C' . $row, $p['commune']);
            $sheet->setCellValue('D' . $row, $p['zone']);
            $sheet->setCellValue('E' . $row, $p['format']);
            $sheet->setCellValue('F' . $row, $p['dimensions'] ?? '');
            $sheet->setCellValue('G' . $row, $p['is_lit'] ? 'Oui' : 'Non');
            $sheet->setCellValue('H' . $row, $p['category'] ?? '');
            $sheet->setCellValue('I' . $row, (float)($p['monthly_rate'] ?? 0));
            $sheet->setCellValue('J' . $row, (int)($p['daily_traffic'] ?? 0));
    
            $statusLabel = $statusLabels[$p['display_status']] ?? ucfirst($p['display_status']);
            $sheet->setCellValue('K' . $row, $statusLabel);
            $sheet->setCellValue('L' . $row, $p['release_info']['date'] ?? '');
    
            // Couleur de fond alternée
            $bgColor = ($row % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$row}:L{$row}")->getFill()
                ->setFillType('solid')
                ->getStartColor()->setRGB($bgColor);
    
            // Couleur colonne statut
            $statusColor = $statusColors[$p['display_status']] ?? '374151';
            $sheet->getStyle("K{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $statusColor]],
            ]);
    
            // Format monétaire pour tarif
            $sheet->getStyle("I{$row}")->getNumberFormat()
                ->setFormatCode('#,##0" FCFA"');
    
            $row++;
        }
    
        // ── Bordures sur les données ───────────────────────────────────
        if ($row > 2) {
            $sheet->getStyle("A2:L" . ($row - 1))->getBorders()
                ->getAllBorders()->setBorderStyle('thin')
                ->getColor()->setRGB('E2E8F0');
        }
    
        // ── Largeurs colonnes ──────────────────────────────────────────
        $widths = ['A'=>14,'B'=>30,'C'=>16,'D'=>14,'E'=>14,'F'=>12,'G'=>10,'H'=>14,'I'=>18,'J'=>14,'K'=>14,'L'=>16];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    
        // ── Freeze ligne 1 + filtre auto ──────────────────────────────
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:L1");
    
        // ── Résumé en bas ─────────────────────────────────────────────
        $summaryRow = $row + 1;
        $sheet->setCellValue('A' . $summaryRow, 'Total panneaux :');
        $sheet->setCellValue('B' . $summaryRow, $panels->count());
        $sheet->setCellValue('C' . $summaryRow, 'Disponibles :');
        $sheet->setCellValue('D' . $summaryRow, $panels->where('display_status', 'libre')->count());
        $sheet->setCellValue('E' . $summaryRow, 'Occupés :');
        $sheet->setCellValue('F' . $summaryRow, $panels->whereIn('display_status', ['occupe','option_periode'])->count());
        $sheet->setCellValue('G' . $summaryRow, 'Générée le :');
        $sheet->setCellValue('H' . $summaryRow, now()->format('d/m/Y H:i'));
    
        $sheet->getStyle("A{$summaryRow}:H{$summaryRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '64748B'], 'size' => 10],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
    
        // ── Écriture et téléchargement ─────────────────────────────────
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$filename}.xlsx",
            'Cache-Control'       => 'no-cache, must-revalidate',
        ]);
    }
 
    /**
     * Fallback Excel si PhpSpreadsheet non installé.
     * Produit un .xls HTML reconnu par Excel/LibreOffice.
     */
    private function _exportExcelHtml(\Illuminate\Support\Collection $panels, string $filename): \Illuminate\Http\Response
    {
        $statusLabels = [
            'libre'          => 'Disponible',
            'occupe'         => 'Occupé',
            'option_periode' => 'En option',
            'option'         => 'En option',
            'maintenance'    => 'Maintenance',
            'confirme'       => 'Confirmé',
        ];
        $statusBg = [
            'libre'          => '#d1fae5',
            'occupe'         => '#fee2e2',
            'option_periode' => '#fef3c7',
            'option'         => '#fef3c7',
            'maintenance'    => '#f1f5f9',
            'confirme'       => '#ede9fe',
        ];
    
        $rows = '';
        foreach ($panels as $i => $p) {
            $bg    = ($i % 2 === 0) ? '#ffffff' : '#f8fafc';
            $sBg   = $statusBg[$p['display_status']] ?? '#f1f5f9';
            $label = $statusLabels[$p['display_status']] ?? ucfirst($p['display_status']);
            $tarif = $p['monthly_rate'] ? number_format($p['monthly_rate'], 0, ',', ' ') . ' FCFA' : '—';
            $trafic = $p['daily_traffic'] ? number_format($p['daily_traffic']) . ' contacts' : '—';
    
            $rows .= "<tr style='background:{$bg}'>
                <td style='font-family:monospace;font-weight:bold'>" . htmlspecialchars($p['reference']) . "</td>
                <td>" . htmlspecialchars($p['name']) . "</td>
                <td>" . htmlspecialchars($p['commune']) . "</td>
                <td>" . htmlspecialchars($p['zone']) . "</td>
                <td>" . htmlspecialchars($p['format'] ?? '—') . "</td>
                <td>" . htmlspecialchars($p['dimensions'] ?? '—') . "</td>
                <td style='text-align:center'>" . ($p['is_lit'] ? '✓' : '') . "</td>
                <td>" . htmlspecialchars($p['category'] ?? '—') . "</td>
                <td style='text-align:right;font-weight:bold'>{$tarif}</td>
                <td style='text-align:right'>{$trafic}</td>
                <td style='background:{$sBg};text-align:center;font-weight:bold'>{$label}</td>
                <td>" . htmlspecialchars($p['release_info']['date'] ?? '') . "</td>
            </tr>\n";
        }
    
        $total    = $panels->count();
        $dispos   = $panels->where('display_status', 'libre')->count();
        $occupes  = $panels->whereIn('display_status', ['occupe','option_periode'])->count();
        $generated = now()->format('d/m/Y H:i');
    
        $html = "<html xmlns:o='urn:schemas-microsoft-com:office:office'
        xmlns:x='urn:schemas-microsoft-com:office:excel'
        xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
    <meta charset='UTF-8'>
    <style>
    body     { font-family: Calibri, Arial, sans-serif; font-size: 11px; }
    table    { border-collapse: collapse; width: 100%; }
    th       { background: #0F172A; color: white; padding: 7px 10px; text-align: left;
                font-size: 11px; border: 1px solid #334155; }
    td       { padding: 6px 10px; border: 1px solid #E2E8F0; font-size: 11px; vertical-align: middle; }
    .summary { background: #F1F5F9; font-weight: bold; color: #64748B; padding: 6px 10px; }
    h2       { color: #0F172A; font-size: 14px; margin-bottom: 4px; }
    .meta    { color: #64748B; font-size: 10px; margin-bottom: 12px; }
    </style>
    </head>
    <body>
    <h2>CIBLE CI — Disponibilités &amp; Panneaux</h2>
    <div class='meta'>
        Généré le {$generated} &nbsp;|&nbsp;
        Total : <strong>{$total}</strong> panneaux &nbsp;|&nbsp;
        Disponibles : <strong>{$dispos}</strong> &nbsp;|&nbsp;
        Occupés : <strong>{$occupes}</strong>
    </div>
    <table>
    <thead>
    <tr>
        <th>Référence</th><th>Désignation</th><th>Commune</th><th>Zone</th>
        <th>Format</th><th>Dims</th><th>Éclairé</th><th>Catégorie</th>
        <th>Tarif/mois</th><th>Trafic/j</th><th>Statut</th><th>Libre le</th>
    </tr>
    </thead>
    <tbody>
    {$rows}
    </tbody>
    </table>
    </body>
    </html>";
    
        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}.xls",
            'Cache-Control'       => 'no-cache',
        ]);
    }



    // Injecter PropositionService dans le constructeur existant :
    // public function __construct(
    //     protected AvailabilityService  $availability,
    //     protected ReservationService   $reservationService,
    //     protected \App\Services\PropositionService $propositionService  ← AJOUTER
    // ) {}
    
    /**
     * Envoyer une proposition au client.
     * POST /admin/reservations/{reservation}/proposition/envoyer
     */
    public function envoyerProposition(Reservation $reservation)
    {
        try {
            $this->propositionService->envoyer($reservation);
    
            return back()->with('success',
                '📧 Proposition envoyée à ' . ($reservation->client?->email ?? $reservation->client?->name) .
                ' · Expire dans 72h.'
            );
    
        } catch (\RuntimeException $e) {
            $code    = explode(':', $e->getMessage())[0];
            $message = substr($e->getMessage(), strlen($code) + 1);
    
            return match($code) {
                'NO_EMAIL'       => back()->with('error', '❌ ' . $message . ' — Mettez à jour la fiche client.'),
                'INVALID_STATUS' => back()->with('error', '⚠️ ' . $message),
                'CLIENT_DELETED' => back()->with('error', '❌ ' . $message),
                'MAIL_FAILED'    => back()->with('error', '📧 Erreur d\'envoi email. Vérifiez la config SMTP. Détail : ' . $message),
                default          => back()->with('error', 'Erreur : ' . $e->getMessage()),
            };
        }
    }
    
    /**
     * Réinitialiser la proposition (admin retire le token).
     * POST /admin/reservations/{reservation}/proposition/reinitialiser
     */
    public function reinitialiserProposition(Reservation $reservation)
    {
        $this->propositionService->reinitialiser($reservation);
        return back()->with('success', 'Proposition réinitialisée. Vous pouvez renvoyer.');
    }
    


}