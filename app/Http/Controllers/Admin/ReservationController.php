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
use App\Services\AlertService;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Enums\PanelStatus;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Exports\PanelsExport;
use App\Models\ExternalPanel;
use App\Services\PdfExportService;
use App\Support\PdfAssets;
use Maatwebsite\Excel\Facades\Excel;

class ReservationController extends Controller
{
    use PdfAssets;

    public function __construct(
        protected AvailabilityService $availability,
        protected ReservationService $reservationService
    ) {
    }

    // ══════════════════════════════════════════════════════════════
    // DISPONIBILITÉS — rendu initial
    // ══════════════════════════════════════════════════════════════
    public function disponibilites(Request $request)
    {
        $communes = Commune::orderBy('name')->get(['id', 'name']);
        $formats = PanelFormat::orderBy('name')->get(['id', 'name', 'width', 'height']);
        $zones = Zone::orderBy('name')->get(['id', 'name']);
        $clients = Client::orderBy('name')->get(['id', 'name']);
        $agencies = \App\Models\ExternalAgency::where('is_active', true)
            ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        $dimensions = PanelFormat::whereNotNull('width')->whereNotNull('height')
            ->orderBy('width')->orderBy('height')->get(['width', 'height'])
            ->map(function ($f) {
                if (!$f->width || !$f->height)
                    return null;
                $w = rtrim(rtrim(number_format($f->width, 2, '.', ''), '0'), '.');
                $h = rtrim(rtrim(number_format($f->height, 2, '.', ''), '0'), '.');
                return "{$w}x{$h}m";
            })->filter()->unique()->values();

        return view(
            'admin.reservations.disponibilites',
            compact('communes', 'formats', 'zones', 'clients', 'dimensions', 'agencies')
        );
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — grille panneaux
    // ══════════════════════════════════════════════════════════════
    public function panneauxAjax(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = $request->dispo_du ?: null;
        $endDate = $request->dispo_au ?: null;
        $statut = $request->get('statut', 'tous');
        $source = $request->get('source', 'all');
        $search = trim($request->get('q', ''));
        $perPage = min((int) $request->get('per_page', 48), 200);
        $page = max((int) $request->get('page', 1), 1);

        $communeIds = array_map('intval', array_filter((array) $request->get('commune_ids', [])));
        $zoneIds = array_map('intval', array_filter((array) $request->get('zone_ids', [])));
        $formatIds = array_map('intval', array_filter((array) $request->get('format_ids', [])));
        $agencyIds = array_map('intval', array_filter((array) $request->get('agency_ids', [])));
        $isLit = $request->input('is_lit', '');

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
        $occupiedIds = collect();
        $optionIds = collect();
        $releaseDates = collect();

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
                    'id',
                    'reference',
                    'name',
                    'commune_id',
                    'zone_id',
                    'format_id',
                    'category_id',
                    'status',
                    'is_lit',
                    'monthly_rate',
                    'daily_traffic',
                    'zone_description'
                ]);

            if (!empty($communeIds))
                $query->whereIn('commune_id', $communeIds);
            if (!empty($zoneIds))
                $query->whereIn('zone_id', $zoneIds);
            if (!empty($formatIds))
                $query->whereIn('format_id', $formatIds);
            if ($isLit === '1')
                $query->where('is_lit', true);
            elseif ($isLit === '0')
                $query->where('is_lit', false);

            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(
                    fn($q) =>
                    $q->where('reference', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('zone_description', 'like', $like)
                );
            }

            if ($request->filled('dimensions')) {
                [$w, $h] = self::parseDimensions($request->dimensions);
                if ($w !== null) {
                    $query->whereHas(
                        'format',
                        fn($q) =>
                        $q->whereBetween('width', [$w - 0.01, $w + 0.01])
                            ->whereBetween('height', [$h - 0.01, $h + 0.01])
                    );
                }
            }

            if (!$dateError && in_array($statut, ['maintenance', 'confirme'])) {
                $query->where('status', $statut);
            } elseif (!$dateError && $statut === 'libre' && (!$startDate || !$endDate)) {
                $query->where('status', 'libre');
            }

            if (in_array($statut, ['occupe', 'option']) && (!$startDate || !$endDate)) {
                $internalResult = collect();
                $dateError = $dateError ?: 'Saisissez une période pour filtrer par '
                    . ($statut === 'option' ? 'Option' : 'Occupé') . '.';
            } else {
                $panels = $query->orderBy('reference')->get();

                if ($startDate && $endDate && !$dateError && $panels->isNotEmpty()) {
                    $bookings = ReservationPanel::whereIn('panel_id', $panels->pluck('id'))
                        ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                        ->whereIn('reservations.status', [
                            ReservationStatus::CONFIRME->value,
                            ReservationStatus::EN_ATTENTE->value,
                        ])
                        ->where('reservations.start_date', '<', $endDate)
                        ->where('reservations.end_date', '>', $startDate)
                        ->select(
                            'reservation_panels.panel_id',
                            'reservations.status',
                            DB::raw('MAX(reservations.end_date) as release_date')
                        )
                        ->groupBy('reservation_panels.panel_id', 'reservations.status')
                        ->get();

                    $occupiedIds = $bookings->where('status', ReservationStatus::CONFIRME->value)->pluck('panel_id')->unique();
                    $optionIds = $bookings->where('status', ReservationStatus::EN_ATTENTE->value)->pluck('panel_id')->unique();
                    $releaseDates = $bookings->groupBy('panel_id')->map(fn($g) => $g->max('release_date'));
                }

                if (!$dateError && $startDate && $endDate) {
                    // Règle métier : un panneau confirmé prime sur l'option.
                    // → "occupe"  = uniquement réservations confirmées (rouge)
                    // → "option"  = en attente sans confirmation parallèle (orange)
                    // → "libre"   = ni l'un ni l'autre + pas en maintenance
                    $panels = match ($statut) {
                        'occupe' => $panels->filter(fn($p) => $occupiedIds->contains($p->id))->values(),
                        'option' => $panels->filter(fn($p) => $optionIds->contains($p->id) && !$occupiedIds->contains($p->id))->values(),
                        'libre'  => $panels->filter(fn($p) => !$occupiedIds->contains($p->id) && !$optionIds->contains($p->id) && $p->status->value !== 'maintenance')->values(),
                        default  => $panels,
                    };
                }

                $now = Carbon::now()->startOfDay();
                $internalResult = $panels->map(
                    fn($panel) =>
                    self::formatInternalPanel($panel, $occupiedIds, $optionIds, $releaseDates, $startDate, $endDate, $dateError, $now)
                );
            }
        }

        // ══ PANNEAUX EXTERNES ════════════════════════════════════════
        // Règle : les filtres communs (commune/zone/format/dimensions/is_lit/
        // search) s'appliquent AUSSI aux externes. Les bookings (occupé/option)
        // sont calculés depuis reservation_panels (source='externe') sur la
        // période demandée — exactement comme pour les internes.
        // Defensive : si une erreur SQL survient (donnée corrompue, colonne
        // manquante…), on log et on continue avec les seuls internes — pas
        // de 500 sur la grille entière.
        if (in_array($source, ['external', 'all']) && !$dateError) try {
            $extQuery = \App\Models\ExternalPanel::with([
                'agency:id,name',
                'commune:id,name',
                'zone:id,name',
                'format:id,name,width,height',
                'category:id,name',
            ])->whereHas('agency', fn($q) => $q->where('is_active', true)->whereNull('deleted_at'));

            if (!empty($communeIds))  $extQuery->whereIn('commune_id', $communeIds);
            if (!empty($zoneIds))     $extQuery->whereIn('zone_id', $zoneIds);
            if (!empty($formatIds))   $extQuery->whereIn('format_id', $formatIds);
            if (!empty($agencyIds))   $extQuery->whereIn('agency_id', $agencyIds);
            if ($isLit === '1')       $extQuery->where('is_lit', true);
            elseif ($isLit === '0')   $extQuery->where('is_lit', false);

            if ($search !== '') {
                $like = '%' . $search . '%';
                $extQuery->where(function ($q) use ($like) {
                    $q->where('code_panneau', 'like', $like)
                      ->orWhere('designation', 'like', $like)
                      ->orWhere('zone_description', 'like', $like)
                      ->orWhereHas('agency', fn($qa) => $qa->where('name', 'like', $like))
                      ->orWhereHas('commune', fn($qc) => $qc->where('name', 'like', $like));
                });
            }

            if ($request->filled('dimensions')) {
                [$w, $h] = self::parseDimensions($request->dimensions);
                if ($w !== null) {
                    $extQuery->whereHas('format', fn($q) =>
                        $q->whereBetween('width', [$w - 0.01, $w + 0.01])
                          ->whereBetween('height', [$h - 0.01, $h + 0.01])
                    );
                }
            }

            // Pré-filtre statut "stable" (maintenance / confirme déjà figés
            // dans availability_status — pas besoin de période).
            if (!$dateError && in_array($statut, ['maintenance', 'confirme'])) {
                $extQuery->where('availability_status', $statut);
            }

            // Statut occupe/option exigent une période — sinon erreur métier.
            if (in_array($statut, ['occupe', 'option']) && (!$startDate || !$endDate)) {
                // L'erreur a déjà été posée plus haut pour les internes.
                $externalResult = collect();
            } else {
                $extPanels = $extQuery->orderBy('code_panneau')->get();

                // Bookings sur la période (mêmes règles qu'internes)
                $extBookings = collect();
                if ($startDate && $endDate && $extPanels->isNotEmpty()) {
                    $extBookings = $this->availability->getExternalPanelBookingMap(
                        $extPanels->pluck('id')->all(),
                        $startDate,
                        $endDate
                    );
                }

                // Filtre métier : occupe = a_confirmé ; option = a_attente sans confirmé ; libre = ni l'un ni l'autre + pas maintenance
                if (!$dateError && $startDate && $endDate) {
                    $extPanels = match ($statut) {
                        'occupe' => $extPanels->filter(fn($p) =>
                            ($extBookings->get($p->id)?->has_confirmed) ||
                            in_array($p->availability_status, ['occupe', 'confirme'])
                        )->values(),
                        'option' => $extPanels->filter(function ($p) use ($extBookings) {
                            $b = $extBookings->get($p->id);
                            return $b && $b->has_option && !$b->has_confirmed;
                        })->values(),
                        'libre'  => $extPanels->filter(function ($p) use ($extBookings) {
                            $b = $extBookings->get($p->id);
                            $hasBlocking = $b && ($b->has_confirmed || $b->has_option);
                            return !$hasBlocking
                                && !in_array($p->availability_status, ['maintenance', 'confirme', 'occupe']);
                        })->values(),
                        default  => $extPanels,
                    };
                }

                $externalResult = $extPanels->map(
                    fn($p) => self::formatExternalPanel($p, $startDate, $endDate, $extBookings->get($p->id))
                );
            }
        } catch (\Throwable $e) {
            Log::error('disponibilites.external_query_failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
                'search'  => $search,
                'statut'  => $statut,
                'source'  => $source,
            ]);
            $externalResult = collect();
        }

        // ══ FUSION + PAGINATION ══════════════════════════════════════
        $allPanels = $internalResult->merge($externalResult)->values();
        $total = $allPanels->count();
        $paginated = $allPanels->forPage($page, $perPage)->values();

        return response()->json([
            'panels' => $paginated,
            'stats' => [
            'total'        => $total,
            'displayed'    => $paginated->count(),
            'disponibles'  => $allPanels->where('display_status', 'libre')->count(),
            'occupes'      => $allPanels->where('display_status', 'occupe')->count(),
            'options'      => $allPanels->where('display_status', 'option_periode')->count(),
            'maintenance'  => $allPanels->where('display_status', 'maintenance')->count(),
            'externes'     => $externalResult->count(),
            'internes'     => $internalResult->count(),
            'pages'        => (int) ceil($total / $perPage),
            'current_page' => $page,
        ],
            'date_error' => $dateError,
            'has_period' => (bool) ($startDate && $endDate && !$dateError),
        ]);
    }

    // ══ HELPERS FORMATAGE ═══════════════════════════════════════════

    private static function formatInternalPanel($panel, $occupiedIds, $optionIds, $releaseDates, $startDate, $endDate, $dateError, $now): array
    {
        $isOccupied = $occupiedIds->contains($panel->id);
        $isOption = $optionIds->contains($panel->id);
        $displayStatus = match (true) {
            $panel->status->value === 'maintenance' => 'maintenance',
            $isOccupied && $startDate && $endDate && !$dateError => 'occupe',
            $isOption && $startDate && $endDate && !$dateError => 'option_periode',
            default => $panel->status->value,
        };

        $releaseInfo = null;
        if ($isOccupied || $isOption) {
            $rdRaw = $releaseDates->get($panel->id);
            if ($rdRaw) {
                $rd = Carbon::parse($rdRaw)->startOfDay();
                $daysLeft = (int) $now->diffInDays($rd, false);
                $releaseInfo = [
                    'date' => $rd->format('d/m/Y'),
                    'days_left' => $daysLeft,
                    'label' => match (true) {
                        $daysLeft === 0 => "Libre aujourd'hui",
                        $daysLeft === 1 => 'Libre demain',
                        $daysLeft > 0 => "Libre le {$rd->format('d/m/Y')} ({$daysLeft}j)",
                        default => 'Date passée',
                    },
                    'color' => $daysLeft <= 0 ? 'green' : ($daysLeft <= 7 ? 'orange' : 'default'),
                ];
            }
        }

        return [
            'id' => $panel->id,
            'source' => 'internal',
            'reference' => $panel->reference,
            'name' => $panel->name,
            'commune' => $panel->commune?->name ?? '—',
            'commune_id' => $panel->commune_id,
            'zone' => $panel->zone?->name ?? '—',
            'zone_id' => $panel->zone_id,
            'format' => $panel->format?->name ?? '—',
            'format_id' => $panel->format_id,
            'dimensions' => self::buildDims($panel->format),
            'category' => $panel->category?->name ?? '—',
            'agency_name' => null,
            'agency_id' => null,
            'is_lit' => (bool) $panel->is_lit,
            'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
            'daily_traffic' => (int) ($panel->daily_traffic ?? 0),
            'zone_description' => $panel->zone_description ?? '',
            'photo_url' => $panel->photos->isNotEmpty()
                ? asset('storage/' . $panel->photos->first()->path)
                : null,
            'status_db' => $panel->status->value,
            'display_status' => $displayStatus,
            'is_selectable' => $displayStatus === 'libre',
            'release_info' => $releaseInfo,
            'card_color_idx' => abs(crc32($panel->reference)) % 6,
        ];
    }

    /**
     * @param  object|null $booking  Ligne agrégée renvoyée par
     *                               AvailabilityService::getExternalPanelBookingMap()
     */
    private static function formatExternalPanel($panel, $startDate, $endDate, $booking = null): array
    {
        // Calcul du display_status — exactement comme les internes :
        //   confirmé sur période   → 'occupe'         (rouge)
        //   en attente sur période → 'option_periode' (orange)
        //   maintenance            → 'maintenance'
        //   sinon                  → 'libre'
        $rawStatus = $panel->availability_status ?? 'disponible';
        $hasConfirmed = (bool) ($booking->has_confirmed ?? false);
        $hasOption    = (bool) ($booking->has_option ?? false);

        $displayStatus = match (true) {
            $rawStatus === 'maintenance' => 'maintenance',
            $hasConfirmed && $startDate && $endDate => 'occupe',
            $hasOption    && $startDate && $endDate => 'option_periode',
            in_array($rawStatus, ['occupe', 'confirme']) => 'occupe',
            $rawStatus === 'option'                      => 'option_periode',
            default => 'libre',
        };

        $releaseInfo = null;
        $rdRaw = $booking->release_date ?? ($panel->available_from ?? null);
        if (in_array($displayStatus, ['occupe', 'option_periode']) && $rdRaw) {
            $rd = Carbon::parse($rdRaw)->startOfDay();
            $daysLeft = (int) now()->startOfDay()->diffInDays($rd, false);
            $releaseInfo = [
                'date' => $rd->format('d/m/Y'),
                'days_left' => $daysLeft,
                'label' => match (true) {
                    $daysLeft === 0 => "Libre aujourd'hui",
                    $daysLeft === 1 => 'Libre demain',
                    $daysLeft > 0   => "Libre le {$rd->format('d/m/Y')} ({$daysLeft}j)",
                    default         => 'Date passée',
                },
                'color' => $daysLeft <= 0 ? 'green' : ($daysLeft <= 7 ? 'orange' : 'default'),
            ];
        }

        // Photo : storage public (asset URL) si présente — DomPDF utilise un
        // autre chemin (data-URI) géré côté enrichExternalPanel().
        $photoUrl = null;
        if (!empty($panel->photo_path)) {
            $photoUrl = asset('storage/' . ltrim($panel->photo_path, '/'));
        }

        return [
            'id' => 'ext_' . $panel->id,
            'source' => 'external',
            'reference' => $panel->code_panneau,
            'name' => $panel->designation,
            'commune' => $panel->commune?->name ?? '—',
            'commune_id' => $panel->commune_id,
            'zone' => $panel->zone?->name ?? '—',
            'zone_id' => $panel->zone_id ?? null,
            'format' => $panel->format?->name ?? '—',
            'format_id' => $panel->format_id ?? null,
            'dimensions' => self::buildDims($panel->format ?? null),
            'category' => $panel->category?->name ?? ($panel->type ?? '—'),
            'agency_name' => $panel->agency?->name ?? '—',
            'agency_id' => $panel->agency_id,
            'is_lit' => (bool) ($panel->is_lit ?? false),
            'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
            'daily_traffic' => (int) ($panel->daily_traffic ?? 0),
            'zone_description' => $panel->zone_description ?? '',
            'photo_url' => $photoUrl,
            'status_db' => $rawStatus,
            'display_status' => $displayStatus,
            'is_selectable' => $displayStatus === 'libre',
            'release_info' => $releaseInfo,
            'card_color_idx' => abs(crc32($panel->code_panneau)) % 6,
        ];
    }

    private static function buildDims($format): ?string
    {
        if (!$format?->width || !$format?->height)
            return null;
        $w = rtrim(rtrim(number_format($format->width, 2, '.', ''), '0'), '.');
        $h = rtrim(rtrim(number_format($format->height, 2, '.', ''), '0'), '.');
        return "{$w}×{$h}m";
    }

    private static function parseDimensions(string $dim): array
    {
        $clean = str_replace('m', '', $dim);
        foreach (['×', 'x'] as $sep) {
            if (str_contains($clean, $sep)) {
                [$w, $h] = explode($sep, $clean, 2);
                if (is_numeric(trim($w)) && is_numeric(trim($h))) {
                    return [(float) trim($w), (float) trim($h)];
                }
            }
        }
        return [null, null];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS — parsing des panel_ids mixtes
    //   Format accepté en entrée : entier (interne) ou "ext_<id>" (externe).
    // ══════════════════════════════════════════════════════════════

    /**
     * Sépare une liste mixte en deux tableaux d'entiers : internes / externes.
     * Filtre les entrées invalides silencieusement (validation dédiée ci-dessous).
     */
    private function splitMixedPanelIds(array $rawIds): array
    {
        $internalIds = [];
        $externalIds = [];
        foreach ($rawIds as $id) {
            if (is_string($id) && str_starts_with($id, 'ext_')) {
                $n = (int) substr($id, 4);
                if ($n > 0) $externalIds[] = $n;
            } elseif (is_numeric($id)) {
                $n = (int) $id;
                if ($n > 0) $internalIds[] = $n;
            }
        }
        return [array_values(array_unique($internalIds)), array_values(array_unique($externalIds))];
    }

    /**
     * Validation dédiée pour les IDs mixtes : au moins un ID valide,
     * existence vérifiée en base sur les deux tables.
     * Retourne [internalIds, externalIds] ou jette ValidationException.
     */
    private function validateMixedPanelIds(Request $request): array
    {
        $raw = (array) $request->input('panel_ids', []);
        if (empty($raw)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'panel_ids' => 'Aucun panneau sélectionné.',
            ]);
        }

        [$internalIds, $externalIds] = $this->splitMixedPanelIds($raw);

        if (empty($internalIds) && empty($externalIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'panel_ids' => 'Aucun panneau valide sélectionné.',
            ]);
        }

        // Vérification d'existence (en deux requêtes count) — moins coûteux
        // qu'un exists par ligne, et permet un message d'erreur clair.
        if ($internalIds && Panel::whereIn('id', $internalIds)->count() !== count($internalIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'panel_ids' => 'Un ou plusieurs panneaux internes sont introuvables.',
            ]);
        }
        if ($externalIds && ExternalPanel::whereIn('id', $externalIds)->count() !== count($externalIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'panel_ids' => 'Un ou plusieurs panneaux externes sont introuvables.',
            ]);
        }

        return [$internalIds, $externalIds];
    }

    // ══════════════════════════════════════════════════════════════
    // PDF — images (supporte sélection mixte interne + externe)
    // ══════════════════════════════════════════════════════════════
    public function pdfImages(Request $request)
    {
        $request->validate([
            'panel_ids'    => 'required|array|min:1|max:200',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
            'show_pricing' => 'nullable|boolean',
            'hide_status'  => 'nullable|boolean',
        ]);

        [$internalIds, $externalIds] = $this->validateMixedPanelIds($request);

        $reservationRef = $request->reservation_ref ?? null;
        $clientName = $request->client_name ?? null;

        $service = app(PdfExportService::class);
        $enriched = collect();

        if ($internalIds) {
            $internals = Panel::with([
                'commune:id,name',
                'zone:id,name',
                'format:id,name,width,height',
                'category:id,name',
                'photos' => fn($q) => $q->orderBy('ordre'),
            ])
                ->whereIn('id', $internalIds)
                ->orderByRaw('FIELD(id,' . implode(',', $internalIds) . ')')
                ->get();
            $enriched = $enriched->merge($internals->map(fn($p) => $service->enrichPanel($p)));
        }

        if ($externalIds) {
            $externals = ExternalPanel::with([
                'commune:id,name',
                'zone:id,name',
                'format:id,name,width,height',
                'category:id,name',
                'agency:id,name',
            ])
                ->whereIn('id', $externalIds)
                ->orderByRaw('FIELD(id,' . implode(',', $externalIds) . ')')
                ->get();
            $enriched = $enriched->merge($externals->map(fn($p) => $service->enrichExternalPanel($p)));
        }

        $panels = $enriched->values();

        $startDate = $request->start_date ?? null;
        $endDate = $request->end_date ?? null;

        $filename = 'panneaux-' . now()->format('Ymd_His');

        // Règle métier : par défaut on cache prix + statut (proposition propre).
        // L'admin coche "show_pricing" pour afficher l'info commerciale.
        $showPricing = $request->boolean('show_pricing');
        $hideStatus  = $request->has('show_pricing')
            ? !$showPricing
            : (bool) $request->boolean('hide_status', true);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'admin.reservations.pdf.disponibilites-images',
            [
                'panels'          => $panels,
                'startDate'       => $startDate,
                'endDate'         => $endDate,
                'generated'       => now()->format('d/m/Y à H:i'),
                'reservation_ref' => $reservationRef,
                'client_name'     => $clientName,
                'logoSrc'         => $this->getLogoPdf(),
                'hideStatus'      => $hideStatus,
                'showPricing'     => $showPricing,
            ]
        )
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'DejaVu Sans',
                'dpi'                  => 96,
            ]);

        return $pdf->download($filename . '.pdf');
    }


    public function pdfListe(Request $request)
    {
        $request->validate([
            'panel_ids'    => 'required|array|min:1',
            'show_pricing' => 'nullable|boolean',
            // Compat ascendante : ancien paramètre "hide_status" toujours accepté
            'hide_status'  => 'nullable|boolean',
        ]);

        [$internalIds, $externalIds] = $this->validateMixedPanelIds($request);

        // Charge les internes en Eloquent (la vue lit les relations) puis on
        // adapte les externes en stdClass avec les mêmes clés (reference/name/
        // status->value) pour ne PAS toucher la vue partagée.
        $panels = collect();

        if ($internalIds) {
            $internals = Panel::with(['commune', 'zone', 'format', 'category'])
                ->whereIn('id', $internalIds)
                ->orderBy('reference')
                ->get();
            $panels = $panels->merge($internals);
        }

        if ($externalIds) {
            $externals = ExternalPanel::with(['commune', 'zone', 'format', 'category', 'agency:id,name'])
                ->whereIn('id', $externalIds)
                ->orderBy('code_panneau')
                ->get();

            $panels = $panels->merge($externals->map(fn($p) => (object) [
                'reference'      => $p->code_panneau,
                'name'           => $p->designation,
                'commune'        => $p->commune,
                'zone'           => $p->zone,
                'format'         => $p->format,
                'category'       => $p->category,
                'monthly_rate'   => $p->monthly_rate,
                'daily_traffic'  => $p->daily_traffic,
                'is_lit'         => (bool) $p->is_lit,
                'status'         => (object) ['value' => $p->availability_status ?? 'libre'],
                'agency_name'    => $p->agency?->name,
                '_external'      => true,
            ]));
        }

        $startDate    = $request->start_date;
        $endDate      = $request->end_date;
        $dureeEnMois  = ($startDate && $endDate)
            ? max(1, (int) ceil(Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) / 30))
            : 1;
        $totalMensuel = (float) $panels->sum(fn($p) => (float) ($p->monthly_rate ?? 0));
        $totalPeriode = $totalMensuel * $dureeEnMois;
        $generated    = now()->format('d/m/Y à H:i');

        // Règle métier : par défaut PAS de prix ni de statut affichés (PDF =
        // proposition commerciale propre). Coche pour révéler.
        // Compat ascendante : si l'ancien hide_status=1 est envoyé, on respecte.
        $showPricing  = $request->boolean('show_pricing');
        $hideStatus   = $request->has('show_pricing')
            ? !$showPricing
            : (bool) $request->boolean('hide_status', true);
        $logoSrc      = $this->getLogoPdf(); // via trait PdfAssets

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reservations.pdf.disponibilites-list', compact(
            'panels',
            'startDate',
            'endDate',
            'dureeEnMois',
            'totalMensuel',
            'totalPeriode',
            'generated',
            'hideStatus',
            'showPricing',
            'logoSrc'
        ));

        $pdf->setPaper('A4', 'landscape');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
            'dpi'                  => 96,
        ]);

        $suffix = $hideStatus ? '-proposition' : '';
        return $pdf->download('selection-panneaux-liste' . $suffix . '-' . now()->format('Ymd') . '.pdf');
    }

    /**
     * EXPORT EXCEL — Liste des panneaux disponibles (internes + externes)
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'panel_ids'    => 'required|array|min:1',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
            'show_pricing' => 'nullable|boolean',
            'hide_status'  => 'nullable|boolean',
        ]);

        [$internalIds, $externalIds] = $this->validateMixedPanelIds($request);

        $panels = collect();

        if ($internalIds) {
            $panels = $panels->merge(
                Panel::with(['commune', 'zone', 'format', 'category'])
                    ->whereIn('id', $internalIds)
                    ->orderBy('reference')
                    ->get()
            );
        }

        if ($externalIds) {
            // Adapter ExternalPanel -> stdClass aux clés attendues par PanelsExport.
            // status->value = availability_status (mappé), reference = code_panneau,
            // name = designation. PanelsExport::map() lit ces clés via $p->xxx.
            $externals = ExternalPanel::with(['commune', 'zone', 'format', 'category', 'agency:id,name'])
                ->whereIn('id', $externalIds)
                ->orderBy('code_panneau')
                ->get();

            $panels = $panels->merge($externals->map(fn($p) => (object) [
                'reference'     => $p->code_panneau,
                'name'          => $p->designation,
                'commune'       => $p->commune,
                'zone'          => $p->zone,
                'format'        => $p->format,
                'category'      => $p->category,
                'is_lit'        => (bool) $p->is_lit,
                'daily_traffic' => $p->daily_traffic,
                'monthly_rate'  => $p->monthly_rate,
                'status'        => (object) ['value' => $p->availability_status ?? 'libre'],
                'agency_name'   => $p->agency?->name,
                '_external'     => true,
            ]));
        }

        $startDate  = $request->start_date;
        $endDate    = $request->end_date;
        // Cohérence avec les PDF : show_pricing prioritaire, fallback hide_status (compat).
        $hideStatus = $request->has('show_pricing')
            ? !$request->boolean('show_pricing')
            : (bool) $request->boolean('hide_status', false);

        return Excel::download(
            new PanelsExport($panels, $startDate, $endDate, $hideStatus),
            'panneaux-disponibles-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    // ══════════════════════════════════════════════════════════════
    // CONFIRMER SÉLECTION
    // ══════════════════════════════════════════════════════════════

    // ajouter alerte création réservation
    public function confirmerSelection(Request $request)
    {
        // 1) Parse mixte (interne + ext_<id>)
        [$internalIds, $externalIds] = $this->splitMixedPanelIds(
            (array) $request->input('panel_ids', [])
        );

        // 2) Au moins UN panneau (interne ou externe)
        if (empty($internalIds) && empty($externalIds)) {
            return back()->withErrors([
                'panel_ids' => 'Aucun panneau sélectionné.',
            ])->withInput();
        }

        // 3) Validation des autres champs (sans toucher panel_ids — on l'a déjà parsé)
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) {
                    if ($value < now()->subDay()->format('Y-m-d'))
                        $fail('La date de début ne peut pas être dans le passé.');
                }
            ],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after:start_date'],
            'notes' => 'nullable|string|max:2000',
            'type' => 'required|in:option,ferme',
            'campaign_name' => 'nullable|string|max:150',
            // Tâche 6.1 — montant personnalisé optionnel (override du calcul auto)
            'amount' => 'nullable|numeric|min:0|max:9999999999',
        ]);

        // 4) Cap volume (50 internes + 50 externes max — symétrique)
        if (count($internalIds) > 50 || count($externalIds) > 50) {
            return back()->withErrors([
                'panel_ids' => 'Trop de panneaux sélectionnés (max 50 internes + 50 externes).',
            ])->withInput();
        }

        // 5) Existence base de données — interne et externe (en deux requêtes count)
        if ($internalIds && Panel::whereIn('id', $internalIds)->count() !== count($internalIds)) {
            return back()->withErrors(['panel_ids' => 'Un ou plusieurs panneaux internes sont introuvables.'])->withInput();
        }
        if ($externalIds && ExternalPanel::whereIn('id', $externalIds)->count() !== count($externalIds)) {
            return back()->withErrors(['panel_ids' => 'Un ou plusieurs panneaux externes sont introuvables.'])->withInput();
        }

        // 6) Internes en maintenance ?
        $maintenancePanels = $internalIds
            ? Panel::whereIn('id', $internalIds)
                ->where('status', PanelStatus::MAINTENANCE->value)->pluck('reference')
            : collect();

        if ($maintenancePanels->isNotEmpty()) {
            return back()->withErrors(['panel_ids' => 'Panneaux en maintenance : ' . $maintenancePanels->join(', ')])->withInput();
        }

        $createdCampaignId = null;

        $reservation = null;

        try {
            DB::transaction(function () use ($request, $internalIds, $externalIds, &$createdCampaignId, &$reservation) {
                // Lock + check conflits UNIQUEMENT pour les internes (l'antidouble-booking
                // s'applique au catalogue CIBLE, les externes restent gérés par leur régie).
                if ($internalIds) {
                    Panel::whereIn('id', $internalIds)->lockForUpdate()->get();
                    $conflicts = $this->availability->getUnavailablePanelIds(
                        $internalIds,
                        $request->start_date,
                        $request->end_date
                    );
                    if (!empty($conflicts)) {
                        $refs = Panel::whereIn('id', $conflicts)->pluck('reference')->join(', ');
                        throw new \RuntimeException("CONFLICT:$refs");
                    }
                }

                $status = $request->type === 'ferme' ? ReservationStatus::CONFIRME : ReservationStatus::EN_ATTENTE;
                $reference = $this->generateUniqueReference();
                $months = $this->monthsBetween($request->start_date, $request->end_date);

                // Calcul du total — internes + externes (les deux ont monthly_rate)
                $autoTotal = 0;
                $attach = [];
                if ($internalIds) {
                    $panelData = Panel::whereIn('id', $internalIds)->get()->keyBy('id');
                    foreach ($internalIds as $panelId) {
                        $unit = (float) ($panelData[$panelId]->monthly_rate ?? 0);
                        $tot  = $unit * $months;
                        $autoTotal += $tot;
                        $attach[$panelId] = ['unit_price' => $unit, 'total_price' => $tot];
                    }
                }

                // Externes : on calcule le coût mais on les attache APRÈS création
                // de la réservation (besoin de reservation_id).
                $externalAttach = [];
                if ($externalIds) {
                    $extData = ExternalPanel::with('agency:id,name')
                        ->whereIn('id', $externalIds)->get();
                    foreach ($extData as $ext) {
                        $unit = (float) ($ext->monthly_rate ?? 0);
                        $tot  = $unit * $months;
                        $autoTotal += $tot;
                        $externalAttach[$ext->id] = [
                            'unit_price'  => $unit,
                            'total_price' => $tot,
                        ];
                    }
                }

                // Tâche 6.1 : montant personnalisé éventuel.
                $customAmount = (float) $request->input('amount', 0);
                $total        = $customAmount > 0 ? $customAmount : $autoTotal;

                if ($customAmount > 0 && abs($customAmount - $autoTotal) > 0.01) {
                    Log::info('reservation.custom_amount', [
                        'reference'    => $reference,
                        'auto'         => $autoTotal,
                        'custom'       => $customAmount,
                        'delta'        => round($customAmount - $autoTotal, 2),
                        'user_id'      => auth()->id(),
                    ]);
                }

                $reservation = Reservation::create([
                    'reference' => $reference,
                    'client_id' => $request->client_id,
                    'user_id' => auth()->id(),
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'status' => $status,
                    'type' => $request->type,
                    'notes' => (string) $request->notes,
                    'total_amount' => $total,
                    'confirmed_at' => $request->type === 'ferme' ? now() : null,
                ]);

                if ($attach) {
                    // Eloquent ne sait pas mettre 'source'='interne' via attach()
                    // car la pivot n'est pas dans withPivot par défaut → on
                    // insère directement les lignes pour garantir source.
                    $rows = [];
                    foreach ($attach as $pid => $cols) {
                        $rows[] = [
                            'reservation_id' => $reservation->id,
                            'panel_id'       => $pid,
                            'source'         => 'interne',
                            'unit_price'     => $cols['unit_price'],
                            'total_price'    => $cols['total_price'],
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ];
                    }
                    DB::table('reservation_panels')->insert($rows);
                    $this->availability->syncPanelStatuses($internalIds);
                }

                if ($externalAttach) {
                    $rows = [];
                    foreach ($externalAttach as $extId => $cols) {
                        $rows[] = [
                            'reservation_id'    => $reservation->id,
                            'external_panel_id' => $extId,
                            'source'            => 'externe',
                            'unit_price'        => $cols['unit_price'],
                            'total_price'       => $cols['total_price'],
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ];
                    }
                    DB::table('reservation_panels')->insert($rows);
                    $this->availability->syncExternalPanelStatuses(array_keys($externalAttach));
                }

                if ($request->type === 'ferme' && $request->filled('campaign_name')) {
                    if (Campaign::where('client_id', $request->client_id)->where('name', $request->campaign_name)->exists()) {
                        throw new \RuntimeException('CAMPAIGN_EXISTS:Une campagne avec ce nom existe déjà pour ce client.');
                    }

                    $campStatus = now()->startOfDay()->lt(
                        \Carbon\Carbon::parse($request->start_date)->startOfDay()
                    )
                        ? CampaignStatus::PLANIFIE->value
                        : CampaignStatus::ACTIF->value;

                    $campaign = Campaign::create([
                        'name'           => $request->campaign_name,
                        'client_id'      => $request->client_id,
                        'reservation_id' => $reservation->id,
                        'user_id'        => auth()->id(),
                        'start_date'     => $request->start_date,
                        'end_date'       => $request->end_date,
                        'status'         => $campStatus,
                        'total_panels'   => count($internalIds) + count($externalIds),
                        'total_amount'   => $total,
                        'notes'          => (string) $request->notes,
                    ]);

                    if ($attach) {
                        $campaign->panels()->sync(array_keys($attach));
                    }

                    // Le pivot campaign_panels supporte external_panel_id —
                    // on attache aussi les externes pour pouvoir les retrouver
                    // dans les vues campagne (pige, suivi, etc.).
                    if ($externalAttach) {
                        $campaignPanelsRows = [];
                        foreach (array_keys($externalAttach) as $extId) {
                            $campaignPanelsRows[] = [
                                'campaign_id'       => $campaign->id,
                                'external_panel_id' => $extId,
                                'type'              => 'externe',
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ];
                        }
                        DB::table('campaign_panels')->insert($campaignPanelsRows);
                    }

                    $createdCampaignId = $campaign->id;
                }

                Log::info('reservation.created', [
                    'reference' => $reference,
                    'type' => $request->type,
                    'panels' => count($internalIds),
                    'ext' => count($externalIds),
                    'user_id' => auth()->id(),
                ]);

                $totalCount = count($internalIds) + count($externalIds);
                AlertService::create(
                    'reservation',
                    $status === ReservationStatus::CONFIRME ? 'info' : 'warning',
                    '📋 Nouvelle réservation — ' . ($reservation->client?->name ?? ''),
                    auth()->user()->name . ' a créé la réservation ' . $reservation->reference . ' (' . $totalCount . ' panneau(x))',
                    $reservation
                );
            });

        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:'))
                return back()->withErrors(['panel_ids' => 'Conflit : ' . substr($e->getMessage(), 9)])->withInput();
            if (str_starts_with($e->getMessage(), 'CAMPAIGN_EXISTS:'))
                return back()->withErrors(['campaign_name' => substr($e->getMessage(), 16)])->withInput();
            throw $e;
        }

        if ($createdCampaignId) {
            return redirect()->route('admin.campaigns.show', $createdCampaignId)
                ->with('success', 'Réservation ferme créée et campagne lancée. ✅');
        }

        // Par — rediriger vers la réservation créée :
        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', $request->type === 'ferme'
                ? 'Réservation ferme créée. Panneaux confirmés. ✅'
                : 'Panneaux mis sous option. ⏳');
    }

    // ══════════════════════════════════════════════════════════════
    // GET PANELS — JSON pour modale "Voir les panneaux" depuis la liste
    // (tâche 5 — visualiser panneaux d'une réservation en option)
    // ══════════════════════════════════════════════════════════════
    public function getPanels(Reservation $reservation): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $reservation);

        $reservation->load([
            'panels:id,reference,name,commune_id,format_id,monthly_rate,is_lit',
            'panels.commune:id,name',
            'panels.format:id,name',
            'panels.photos' => fn($q) => $q->orderBy('ordre')->limit(1),
            'externalPanels:id,code_panneau,designation,commune_id,format_id,monthly_rate,is_lit,photo_path,agency_id',
            'externalPanels.commune:id,name',
            'externalPanels.format:id,name',
            'externalPanels.agency:id,name',
        ]);

        $internalPanels = $reservation->panels->map(fn($p) => [
            'id'           => $p->id,
            'source'       => 'internal',
            'reference'    => $p->reference,
            'name'         => $p->name,
            'commune'      => $p->commune?->name ?? '—',
            'format'       => $p->format?->name  ?? '—',
            'is_lit'       => (bool) $p->is_lit,
            'agency_name'  => null,
            'monthly_rate' => (float) ($p->pivot->unit_price ?? $p->monthly_rate ?? 0),
            'photo_url'    => $p->photos->first()
                ? asset('storage/' . $p->photos->first()->path)
                : null,
        ]);

        $externalPanels = $reservation->externalPanels->map(fn($p) => [
            'id'           => 'ext_' . $p->id,
            'source'       => 'external',
            'reference'    => $p->code_panneau,
            'name'         => $p->designation,
            'commune'      => $p->commune?->name ?? '—',
            'format'       => $p->format?->name  ?? '—',
            'is_lit'       => (bool) $p->is_lit,
            'agency_name'  => $p->agency?->name,
            'monthly_rate' => (float) ($p->pivot->unit_price ?? $p->monthly_rate ?? 0),
            'photo_url'    => $p->photo_path ? asset('storage/' . ltrim($p->photo_path, '/')) : null,
        ]);

        $totalCount = $reservation->panels->count() + $reservation->externalPanels->count();

        return response()->json([
            'reservation' => [
                'reference'  => $reservation->reference,
                'start_date' => $reservation->start_date->format('d/m/Y'),
                'end_date'   => $reservation->end_date->format('d/m/Y'),
                'status'     => $reservation->status->value,
                'count'      => $totalCount,
            ],
            'panels' => $internalPanels->concat($externalPanels)->values(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Reservation::with(['client', 'user'])
            ->withCount(['panels', 'externalPanels']);

        if ($request->search) {
            $query->where(
                fn($q) =>
                $q->where('reference', 'like', "%{$request->search}%")
                    ->orWhereHas('client', fn($q) => $q->withTrashed()->where('name', 'like', "%{$request->search}%"))
            );
        }
        if ($request->status)
            $query->where('status', $request->status);
        if ($request->type)
            $query->where('type', $request->type);
        if ($request->client_id)
            $query->where('client_id', $request->client_id);

        if ($request->periode) {
            match ($request->periode) {
                'this_month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                'last_month' => $query->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year),
                'this_quarter' => $query->whereBetween('created_at', [now()->startOfQuarter(), now()->endOfQuarter()]),
                'this_year' => $query->whereYear('created_at', now()->year),
                default => null,
            };
        }

        $reservations = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $rawCounts = Reservation::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [
            'total' => $rawCounts->sum(),
            'en_attente' => $rawCounts['en_attente'] ?? 0,
            'confirme' => $rawCounts['confirme'] ?? 0,
            'refuse' => $rawCounts['refuse'] ?? 0,
            'annule' => $rawCounts['annule'] ?? 0,
        ];

        $lastSeenAt = auth()->user()->reservations_last_seen_at;
        $newCount = $lastSeenAt ? Reservation::where('created_at', '>', $lastSeenAt)->count() : 0;
        $clients = Client::orderBy('name')->get();
        $statuses = ReservationStatus::cases();

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reservations.partials.table-rows', compact('reservations', 'lastSeenAt'))->render(),
                'pagination' => $reservations->links()->render(),
                'stats' => $counts,
                'has_more' => $reservations->hasMorePages(),
            ]);
        }

        return view(
            'admin.reservations.index',
            compact('reservations', 'clients', 'statuses', 'counts', 'lastSeenAt', 'newCount')
        );
    }

    public function markSeen()
    {
        auth()->user()->update(['reservations_last_seen_at' => now()]);
        return response()->json(['ok' => true]);
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW / EDIT / UPDATE / UPDATE STATUS / ANNULER / DESTROY
    // ══════════════════════════════════════════════════════════════
    public function show(Reservation $reservation)
    {
        $reservation->load([
            'client', 'user',
            'panels.commune', 'panels.format', 'panels.photos',
            'externalPanels.commune', 'externalPanels.format',
            'externalPanels.agency:id,name',
            'campaign',
        ]);
        $user = auth()->user();
        $can = [
            'update' => $reservation->isEditable() && $user->can('update', $reservation),
            'updateStatus' => $reservation->canChangeStatus() && $user->can('updateStatus', $reservation),
            'annuler' => $reservation->isCancellable() && $user->can('annuler', $reservation),
            'delete' => $reservation->isDeletable() && $user->can('delete', $reservation),
        ];

        // ─── Construction d'une vue unifiée des panneaux (internes + externes)
        // Permet à la vue d'avoir UNE SEULE boucle propre. Chaque ligne contient
        // tout ce dont la vue a besoin sans logique conditionnelle compliquée.
        $months = $this->monthsBetween(
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d')
        );
        $days = (int) abs($reservation->start_date->copy()->startOfDay()
            ->diffInDays($reservation->end_date->copy()->startOfDay()));

        $unifiedPanels = collect();

        foreach ($reservation->panels as $p) {
            $unifiedPanels->push([
                'id'             => $p->id,
                'source'         => 'internal',
                'reference'      => $p->reference,
                'name'           => $p->name,
                'commune'        => $p->commune?->name ?? '—',
                'format'         => $p->format?->name ?? '—',
                'format_dim'     => ($p->format?->width && $p->format?->height)
                    ? rtrim(rtrim(number_format($p->format->width, 2, '.', ''), '0'), '.')
                      . '×' . rtrim(rtrim(number_format($p->format->height, 2, '.', ''), '0'), '.') . 'm'
                    : null,
                'agency'         => null,
                'photo_url'      => $p->photos->sortBy('ordre')->first()
                    ? asset('storage/' . $p->photos->sortBy('ordre')->first()->path)
                    : null,
                'unit_price'     => (float) ($p->pivot->unit_price  ?? $p->monthly_rate ?? 0),
                'total_price'    => (float) ($p->pivot->total_price ?? 0),
                'catalog_price'  => (float) ($p->monthly_rate ?? 0),
                'edit_url'       => route('admin.reservations.panels.price', [$reservation, $p->id]),
                'reset_url'      => route('admin.reservations.panels.price.reset', [$reservation, $p->id]),
            ]);
        }

        foreach ($reservation->externalPanels as $p) {
            $unifiedPanels->push([
                'id'             => $p->id,
                'source'         => 'external',
                'reference'      => $p->code_panneau,
                'name'           => $p->designation,
                'commune'        => $p->commune?->name ?? '—',
                'format'         => $p->format?->name ?? '—',
                'format_dim'     => ($p->format?->width && $p->format?->height)
                    ? rtrim(rtrim(number_format($p->format->width, 2, '.', ''), '0'), '.')
                      . '×' . rtrim(rtrim(number_format($p->format->height, 2, '.', ''), '0'), '.') . 'm'
                    : null,
                'agency'         => $p->agency?->name,
                'photo_url'      => $p->photo_path ? asset('storage/' . ltrim($p->photo_path, '/')) : null,
                'unit_price'     => (float) ($p->pivot->unit_price  ?? $p->monthly_rate ?? 0),
                'total_price'    => (float) ($p->pivot->total_price ?? 0),
                'catalog_price'  => (float) ($p->monthly_rate ?? 0),
                'edit_url'       => route('admin.reservations.external-panels.price', [$reservation, $p->id]),
                'reset_url'      => route('admin.reservations.external-panels.price.reset', [$reservation, $p->id]),
            ]);
        }

        $totalCount = $unifiedPanels->count();

        return view('admin.reservations.show', compact(
            'reservation', 'can', 'unifiedPanels', 'months', 'days', 'totalCount'
        ));
    }

    public function edit(Reservation $reservation)
    {
        if (!$reservation->isEditable())
            abort(403, 'Cette réservation ne peut plus être modifiée (' . $reservation->status->label() . ').');
        $reservation->load('panels');
        $clients = Client::orderBy('name')->get();
        $communes = Commune::orderBy('name')->get();
        $formats = PanelFormat::orderBy('name')->get();
        $zones = Zone::orderBy('name')->get();
        $dimensions = PanelFormat::whereNotNull('width')->whereNotNull('height')->orderBy('width')->orderBy('height')->get()
            ->map(function ($f) {
                if (!$f->width || !$f->height)
                    return null;
                $w = rtrim(rtrim(number_format($f->width, 2, '.', ''), '0'), '.');
                $h = rtrim(rtrim(number_format($f->height, 2, '.', ''), '0'), '.');
                return "{$w}×{$h}m";
            })->filter()->unique()->values();
        $selectedPanelIds = $reservation->panels->pluck('id')->toArray();
        return view('admin.reservations.edit', compact('reservation', 'clients', 'communes', 'formats', 'zones', 'selectedPanelIds', 'dimensions'));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        if (!$reservation->isEditable())
            abort(403, 'Cette réservation ne peut plus être modifiée.');
        if ($reservation->client?->trashed())
            abort(403, 'Client supprimé — modification impossible.');
        if ((int) $request->last_updated_at !== $reservation->updated_at->timestamp) {
            return back()->with('error', 'Cette réservation a été modifiée par un autre utilisateur. Rechargez la page.');
        }
        $oldPanels = $reservation->panels->pluck('id')->toArray();
        try {
            $this->reservationService->updateReservation($reservation, $request->validated(), $oldPanels);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'CONFLICT:'))
                return back()->withInput()->withErrors(['panel_ids' => 'Conflit : ' . substr($e->getMessage(), 9)]);
            throw $e;
        }
        return redirect()->route('admin.reservations.show', $reservation)->with('success', 'Réservation mise à jour.');
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        if ($reservation->client?->trashed())
            return back()->with('error', 'Impossible : client supprimé.');
        
        $request->validate(['status' => 'required|in:' . implode(',', array_column(ReservationStatus::cases(), 'value'))]);
        
        if (!$reservation->canTransitionTo($request->status)) {
            return back()->with('error', "Transition interdite : {$reservation->status->value} → {$request->status}.");
        }
        
        // Si on passe à "annule", on peut aussi passer le motif
        if ($request->status === 'annule') {
            $cancelData = [
                'cancel_type' => $request->input('cancel_type', 'autre'),
                'cancel_reason' => $request->input('cancel_reason', ''),
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ];
            $this->reservationService->cancel($reservation, $cancelData);
            
            AlertService::create(
                'reservation',
                'danger',
                '🚫 Réservation annulée — ' . ($reservation->client?->name ?? ''),
                auth()->user()->name . ' a annulé la réservation ' . $reservation->reference,
                $reservation
            );
        } else {
            $this->reservationService->changeStatus($reservation, $request->status);
            
            $statusLabels = ['confirme' => 'confirmée', 'refuse' => 'refusée', 'en_attente' => 'en attente', 'termine' => 'terminée'];
            $label = $statusLabels[$request->status] ?? $request->status;
            $niveau = $request->status === 'confirme' ? 'info' : ($request->status === 'refuse' ? 'danger' : 'info');
            
            AlertService::create(
                'reservation',
                $niveau,
                '📋 Réservation ' . $label . ' — ' . ($reservation->client?->name ?? ''),
                auth()->user()->name . ' a mis à jour la réservation ' . $reservation->reference . ' → ' . $label . '.',
                $reservation
            );
        }
        
        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', "Statut mis à jour : {$request->status}.");
    }

    public function annuler(Request $request, Reservation $reservation)
    {
        if ($reservation->client?->trashed())
            abort(403, 'Impossible : client supprimé.');
        if (!$reservation->isCancellable())
            abort(403, 'Réservation non annulable.');
        
        $panelCount = $reservation->panels->count();
        
        // Extraire les données d'annulation
        $cancelData = [
            'cancel_type' => $request->input('cancel_type', 'autre'),
            'cancel_reason' => $request->input('cancel_reason', ''),
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ];
        
        $this->reservationService->cancel($reservation, $cancelData);
        
        // Alerte annulation
        AlertService::create(
            'reservation',
            'danger',
            '🚫 Réservation annulée — ' . ($reservation->client?->name ?? ''),
            auth()->user()->name . ' a annulé la réservation ' . $reservation->reference . ' (Motif: ' . ($cancelData['cancel_type'] ?? 'autre') . ')',
            $reservation
        );
        
        return redirect()->route('admin.reservations.index')
            ->with('success', "Réservation annulée. {$panelCount} panneau(x) libéré(s).");
    }

    public function destroy(Reservation $reservation)
    {
        if (!$reservation->isDeletable())
            abort(403, 'Impossible : réservation active ou liée à une campagne.');
        $panelCount = $reservation->panels()->count();
        $hasCampaign = $reservation->campaign !== null;
        try {
            $this->reservationService->delete($reservation);
            // Alerte suppression
            AlertService::create(
                'reservation',
                'danger',
                '🗑 Réservation supprimée — ' . ($reservation->client?->name ?? ''),
                auth()->user()->name . ' a supprimé la réservation ' . $reservation->reference,
                null
            );
        } catch (\Exception $e) {
            Log::error('reservation.deletion_failed', ['id' => $reservation->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
        return redirect()->route('admin.reservations.index')
            ->with('success', 'Réservation supprimée. ' . ($hasCampaign ? 'Campagne liée annulée. ' : '') . "{$panelCount} panneau(x) libéré(s).");
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — panneaux disponibles (page edit / modale "Ajouter")
    // Retourne internes + externes — discriminés via la clé 'source'.
    // L'ID des externes est préfixé "ext_" comme sur disponibilites.
    // ══════════════════════════════════════════════════════════════
    public function availablePanels(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'exclude_reservation_id' => 'nullable|integer|exists:reservations,id',
                'commune_id' => 'nullable|integer|exists:communes,id',
                'zone_id' => 'nullable|integer|exists:zones,id',
                'format_id' => 'nullable|integer|exists:panel_formats,id',
                'dimensions' => 'nullable|string|max:20',
                'is_lit' => 'nullable|in:0,1',
            ]);

            $excludeId = $request->exclude_reservation_id ? (int) $request->exclude_reservation_id : null;

            // ─── INTERNES ───────────────────────────────────────────
            $query = Panel::with(['commune:id,name', 'format:id,name,width,height', 'zone:id,name'])
                ->whereNull('deleted_at')
                ->where('status', '!=', PanelStatus::MAINTENANCE->value)
                ->select(['id', 'reference', 'name', 'commune_id', 'zone_id', 'format_id', 'is_lit', 'monthly_rate', 'daily_traffic', 'zone_description']);

            if ($request->filled('commune_id'))
                $query->where('commune_id', (int) $request->commune_id);
            if ($request->filled('zone_id'))
                $query->where('zone_id', (int) $request->zone_id);
            if ($request->filled('format_id'))
                $query->where('format_id', (int) $request->format_id);
            if ($request->filled('is_lit') && $request->is_lit !== '')
                $query->where('is_lit', $request->is_lit === '1');
            if ($request->filled('dimensions')) {
                [$w, $h] = self::parseDimensions($request->dimensions);
                if ($w !== null)
                    $query->whereHas('format', fn($q) => $q->whereBetween('width', [$w - 0.01, $w + 0.01])->whereBetween('height', [$h - 0.01, $h + 0.01]));
            }

            $panels = $query->orderBy('reference')->get();
            $panelIds = $panels->pluck('id')->toArray();

            $availabilityData = $this->availability->getPanelAvailabilityData(
                $panelIds,
                $request->start_date,
                $request->end_date,
                $excludeId
            );

            $internalRows = $panels->map(function ($p) use ($availabilityData) {
                $avail = $availabilityData->get($p->id, ['available' => true, 'release_date' => null, 'blocking_status' => null]);
                $releaseFmt = self::formatReleaseLabel($avail['release_date'] ?? null);
                return [
                    'id'              => $p->id,
                    'source'          => 'internal',
                    'reference'       => $p->reference,
                    'name'            => $p->name,
                    'commune'         => $p->commune?->name ?? '—',
                    'zone'            => $p->zone?->name ?? '—',
                    'format'          => $p->format?->name ?? '—',
                    'dimensions'      => self::buildDims($p->format),
                    'is_lit'          => (bool) $p->is_lit,
                    'monthly_rate'    => (float) ($p->monthly_rate ?? 0),
                    'daily_traffic'   => (int) ($p->daily_traffic ?? 0),
                    'agency_name'     => null,
                    'available'       => $avail['available'],
                    'release_date'    => $releaseFmt,
                    'blocking_status' => $avail['blocking_status'],
                ];
            });

            // ─── EXTERNES ───────────────────────────────────────────
            $extQuery = ExternalPanel::with([
                'commune:id,name', 'zone:id,name',
                'format:id,name,width,height', 'agency:id,name',
            ])->whereHas('agency', fn($q) => $q->where('is_active', true)->whereNull('deleted_at'))
              ->where(fn($q) => $q->whereNull('availability_status')->orWhere('availability_status', '!=', 'maintenance'));

            if ($request->filled('commune_id'))
                $extQuery->where('commune_id', (int) $request->commune_id);
            if ($request->filled('zone_id'))
                $extQuery->where('zone_id', (int) $request->zone_id);
            if ($request->filled('format_id'))
                $extQuery->where('format_id', (int) $request->format_id);
            if ($request->filled('is_lit') && $request->is_lit !== '')
                $extQuery->where('is_lit', $request->is_lit === '1');
            if ($request->filled('dimensions')) {
                [$w, $h] = self::parseDimensions($request->dimensions);
                if ($w !== null)
                    $extQuery->whereHas('format', fn($q) => $q->whereBetween('width', [$w - 0.01, $w + 0.01])->whereBetween('height', [$h - 0.01, $h + 0.01]));
            }

            $externals = $extQuery->orderBy('code_panneau')->get();
            $extBookings = $this->availability->getExternalPanelBookingMap(
                $externals->pluck('id')->all(),
                $request->start_date,
                $request->end_date,
                $excludeId
            );

            $externalRows = $externals->map(function ($p) use ($extBookings) {
                $b = $extBookings->get($p->id);
                $hasConfirmed = (bool) ($b->has_confirmed ?? false);
                $hasOption    = (bool) ($b->has_option ?? false);
                $blocking = $hasConfirmed ? 'confirme' : ($hasOption ? 'en_attente' : null);
                $releaseFmt = self::formatReleaseLabel($b->release_date ?? null);
                return [
                    'id'              => 'ext_' . $p->id,
                    'source'          => 'external',
                    'reference'       => $p->code_panneau,
                    'name'            => $p->designation,
                    'commune'         => $p->commune?->name ?? '—',
                    'zone'            => $p->zone?->name ?? '—',
                    'format'          => $p->format?->name ?? '—',
                    'dimensions'      => self::buildDims($p->format),
                    'is_lit'          => (bool) ($p->is_lit ?? false),
                    'monthly_rate'    => (float) ($p->monthly_rate ?? 0),
                    'daily_traffic'   => (int) ($p->daily_traffic ?? 0),
                    'agency_name'     => $p->agency?->name,
                    'available'       => !$blocking,
                    'release_date'    => $releaseFmt,
                    'blocking_status' => $blocking,
                ];
            });

            return response()->json($internalRows->concat($externalRows)->values());

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('availablePanels.error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Erreur serveur'], 500);
        }
    }

    private static function formatReleaseLabel($rdRaw): ?string
    {
        if (!$rdRaw) return null;
        $rd = Carbon::parse($rdRaw);
        $daysLeft = (int) now()->startOfDay()->diffInDays($rd->startOfDay(), false);
        return $daysLeft <= 0 ? 'Libre maintenant'
            : ($daysLeft === 1 ? 'Libre demain'
                : 'Libre le ' . $rd->format('d/m/Y') . " ({$daysLeft}j)");
    }

    // ══ UTILITAIRES ══════════════════════════════════════════════════

    private function generateUniqueReference(): string
    {
        $attempts = 0;
        do {
            $candidate = 'RES-' . strtoupper(Str::random(8));
            if (++$attempts > 20)
                throw new \RuntimeException('SYSTEM:Référence impossible à générer.');
        } while (Reservation::where('reference', $candidate)->exists());
        return $candidate;
    }

    private function monthsBetween($start, $end): float
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        // Nombre de jours réels
        $totalDays = (int) $s->diffInDays($e);

        if ($totalDays <= 0) return 0.5;

        // Mois entiers
        $fullMonths = (int) floor($totalDays / 30);

        // Jours restants après les mois entiers
        $remainDays = $totalDays % 30;

        // Règle CIBLE CI :
        // 1-15j restants  → + 0.5 mois
        // 16-30j restants → + 1 mois
        $fraction = 0;
        if ($remainDays >= 1 && $remainDays <= 15) {
            $fraction = 0.5;
        } elseif ($remainDays > 15) {
            $fraction = 1;
        }

        $result = $fullMonths + $fraction;

        // Minimum : 0.5 mois (demi-mois)
        return max($result, 0.5);
    }



    // Nouvelle méthode — modifier le prix d'un panneau dans une réservation
    public function updatePanelPrice(Request $request, Reservation $reservation, Panel $panel)
    {
        $request->validate([
            'unit_price' => 'required|numeric|min:0',
        ]);

        if (!$reservation->isEditable()) {
            abort(403, 'Réservation non modifiable.');
        }

        $months = $this->monthsBetween(
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d')
        );

        $reservation->panels()->updateExistingPivot($panel->id, [
            'unit_price' => $request->unit_price,
            'total_price' => $request->unit_price * $months,
        ]);

        // Recalculer le total de la réservation
        $newTotal = $reservation->panels()->sum(DB::raw('reservation_panels.total_price'));
        $reservation->update(['total_amount' => $newTotal]);

        return back()->with('success', 'Prix mis à jour.');
    }

    // Pour réinitialiser au prix catalogue :
    public function resetPanelPrice(Reservation $reservation, Panel $panel)
    {
        $months = $this->monthsBetween(
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d')
        );

        $reservation->panels()->updateExistingPivot($panel->id, [
            'unit_price' => $panel->monthly_rate,
            'total_price' => $panel->monthly_rate * $months,
        ]);

        $this->refreshReservationTotal($reservation);

        return back()->with('success', 'Prix remis au tarif catalogue.');
    }

    // ── EXTERNES : prix négocié + reset (mêmes endpoints que internes) ──
    public function updateExternalPanelPrice(Request $request, Reservation $reservation, ExternalPanel $panel)
    {
        $request->validate(['unit_price' => 'required|numeric|min:0']);
        if (!$reservation->isEditable()) abort(403, 'Réservation non modifiable.');

        $months = $this->monthsBetween(
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d')
        );

        DB::table('reservation_panels')
            ->where('reservation_id', $reservation->id)
            ->where('external_panel_id', $panel->id)
            ->where('source', 'externe')
            ->update([
                'unit_price'  => $request->unit_price,
                'total_price' => $request->unit_price * $months,
                'updated_at'  => now(),
            ]);

        $this->refreshReservationTotal($reservation);
        return back()->with('success', 'Prix du panneau externe mis à jour.');
    }

    public function resetExternalPanelPrice(Reservation $reservation, ExternalPanel $panel)
    {
        if (!$reservation->isEditable()) abort(403, 'Réservation non modifiable.');

        $months = $this->monthsBetween(
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d')
        );
        $catalogue = (float) ($panel->monthly_rate ?? 0);

        DB::table('reservation_panels')
            ->where('reservation_id', $reservation->id)
            ->where('external_panel_id', $panel->id)
            ->where('source', 'externe')
            ->update([
                'unit_price'  => $catalogue,
                'total_price' => $catalogue * $months,
                'updated_at'  => now(),
            ]);

        $this->refreshReservationTotal($reservation);
        return back()->with('success', 'Prix remis au tarif catalogue.');
    }

    /** Recalcule total_amount = somme(total_price) sur reservation_panels (internes + externes). */
    private function refreshReservationTotal(Reservation $reservation): void
    {
        $newTotal = (float) DB::table('reservation_panels')
            ->where('reservation_id', $reservation->id)
            ->sum('total_price');
        $reservation->update(['total_amount' => round($newTotal, 2)]);
    }

    /**
     * AJOUTER UN PANNEAU À UNE RÉSERVATION EXISTANTE
     * POST /admin/reservations/{reservation}/panels/add
     *
     * Anti double-booking : la totalité de l'opération est entourée d'une
     * DB::transaction + lockForUpdate sur le panneau cible. Le re-check de
     * disponibilité se fait APRÈS le verrou pour avoir la source de vérité.
     */
    public function addPanel(Request $request, Reservation $reservation)
    {
        if (!$reservation->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Réservation non modifiable.'], 403);
        }

        $request->validate([
            // Format accepté : entier (interne) OU "ext_<id>" (externe).
            'panel_id'   => 'required',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $rawId = $request->panel_id;
        $isExternal = is_string($rawId) && str_starts_with($rawId, 'ext_');
        $panelId = $isExternal ? (int) substr($rawId, 4) : (int) $rawId;

        if ($panelId <= 0) {
            return response()->json(['success' => false, 'message' => 'Identifiant panneau invalide.'], 422);
        }

        if ($isExternal) {
            return $this->addExternalPanel($reservation, $panelId, $request->unit_price !== null ? (float) $request->unit_price : null);
        }

        if (!Panel::whereKey($panelId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Panneau introuvable.'], 422);
        }

        $unitPrice = $request->unit_price !== null ? (float) $request->unit_price : null;

        // Pré-check rapide hors transaction (gagne du temps sur le cas évident)
        if ($reservation->panels()->where('panel_id', $panelId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ce panneau est déjà dans la réservation.'], 422);
        }

        try {
            DB::transaction(function () use ($reservation, $panelId, &$unitPrice) {
                // ── Verrou pessimiste ────────────────────────────────────────
                // SELECT ... FOR UPDATE bloque les autres transactions qui
                // tenteraient de prendre ce même panneau jusqu'au COMMIT/ROLLBACK.
                $panel = Panel::whereKey($panelId)->lockForUpdate()->first();
                if (!$panel) {
                    throw new \RuntimeException('NOT_FOUND:Panneau introuvable.');
                }

                // ── Re-check disponibilité APRÈS verrou = source de vérité ──
                $conflicts = $this->availability->getUnavailablePanelIds(
                    [$panelId],
                    $reservation->start_date->format('Y-m-d'),
                    $reservation->end_date->format('Y-m-d'),
                    $reservation->id
                );
                if (!empty($conflicts)) {
                    throw new \RuntimeException("CONFLICT:{$panel->reference}");
                }

                // Re-check duplicate dans la transaction (au cas où une autre
                // requête concurrente l'aurait ajouté entre le pré-check et ici)
                if ($reservation->panels()->where('panel_id', $panelId)->exists()) {
                    throw new \RuntimeException('DUPLICATE:Ce panneau a déjà été ajouté.');
                }

                $months    = $this->monthsBetween(
                    $reservation->start_date->format('Y-m-d'),
                    $reservation->end_date->format('Y-m-d')
                );
                if ($unitPrice === null) {
                    $unitPrice = (float) ($panel->monthly_rate ?? 0);
                }
                $totalPrice = $unitPrice * $months;

                $reservation->panels()->attach($panelId, [
                    'unit_price'  => $unitPrice,
                    'total_price' => $totalPrice,
                ]);

                // Recalcul total réservation (somme des total_price du pivot)
                $newTotal = (float) DB::table('reservation_panels')
                    ->where('reservation_id', $reservation->id)
                    ->sum('total_price');
                $reservation->update(['total_amount' => round($newTotal, 2)]);

                $this->availability->syncPanelStatuses([$panelId]);

                Log::info('reservation.panel_added', [
                    'reservation_id' => $reservation->id,
                    'panel_id'       => $panelId,
                    'panel_ref'      => $panel->reference,
                    'user_id'        => auth()->id(),
                ]);
            });
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'CONFLICT:')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflit : le panneau ' . substr($msg, 9) . ' n\'est plus disponible (réservation concurrente).',
                ], 409);
            }
            if (str_starts_with($msg, 'DUPLICATE:')) {
                return response()->json(['success' => false, 'message' => substr($msg, 10)], 422);
            }
            if (str_starts_with($msg, 'NOT_FOUND:')) {
                return response()->json(['success' => false, 'message' => substr($msg, 10)], 404);
            }
            throw $e;
        }

        AlertService::create(
            'reservation',
            'info',
            '➕ Panneau ajouté — ' . $reservation->reference,
            auth()->user()?->name . ' a ajouté un panneau à la réservation ' . $reservation->reference,
            $reservation
        );

        return response()->json(['success' => true, 'message' => 'Panneau ajouté avec succès.']);
    }

    /**
     * Variante de addPanel pour les panneaux externes — appelée par addPanel
     * quand l'ID est préfixé "ext_". Verrou pessimiste sur l'external_panels
     * + insertion dans reservation_panels (source='externe') + sync statut.
     */
    private function addExternalPanel(Reservation $reservation, int $externalPanelId, ?float $unitPrice)
    {
        // Pré-check rapide hors transaction
        $alreadyAttached = DB::table('reservation_panels')
            ->where('reservation_id', $reservation->id)
            ->where('external_panel_id', $externalPanelId)
            ->where('source', 'externe')
            ->exists();
        if ($alreadyAttached) {
            return response()->json(['success' => false, 'message' => 'Ce panneau externe est déjà dans la réservation.'], 422);
        }

        try {
            DB::transaction(function () use ($reservation, $externalPanelId, &$unitPrice) {
                $ext = ExternalPanel::with('agency:id,name')
                    ->whereKey($externalPanelId)
                    ->lockForUpdate()
                    ->first();
                if (!$ext) {
                    throw new \RuntimeException('NOT_FOUND:Panneau externe introuvable.');
                }

                // Re-check conflit (anti double-booking côté externe)
                $conflicts = $this->availability->getExternalPanelBookingMap(
                    [$externalPanelId],
                    $reservation->start_date->format('Y-m-d'),
                    $reservation->end_date->format('Y-m-d'),
                    $reservation->id
                );
                if ($conflicts->isNotEmpty()) {
                    throw new \RuntimeException("CONFLICT:{$ext->code_panneau}");
                }

                // Re-check duplicate après verrou
                $dup = DB::table('reservation_panels')
                    ->where('reservation_id', $reservation->id)
                    ->where('external_panel_id', $externalPanelId)
                    ->where('source', 'externe')
                    ->exists();
                if ($dup) {
                    throw new \RuntimeException('DUPLICATE:Ce panneau a déjà été ajouté.');
                }

                $months = $this->monthsBetween(
                    $reservation->start_date->format('Y-m-d'),
                    $reservation->end_date->format('Y-m-d')
                );
                if ($unitPrice === null) {
                    $unitPrice = (float) ($ext->monthly_rate ?? 0);
                }
                $totalPrice = $unitPrice * $months;

                DB::table('reservation_panels')->insert([
                    'reservation_id'    => $reservation->id,
                    'external_panel_id' => $externalPanelId,
                    'source'            => 'externe',
                    'unit_price'        => $unitPrice,
                    'total_price'       => $totalPrice,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                // Recalcul total réservation (somme internes + externes)
                $newTotal = (float) DB::table('reservation_panels')
                    ->where('reservation_id', $reservation->id)
                    ->sum('total_price');
                $reservation->update(['total_amount' => round($newTotal, 2)]);

                $this->availability->syncExternalPanelStatuses([$externalPanelId]);

                Log::info('reservation.external_panel_added', [
                    'reservation_id' => $reservation->id,
                    'ext_panel_id'   => $externalPanelId,
                    'ext_ref'        => $ext->code_panneau,
                    'user_id'        => auth()->id(),
                ]);
            });
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'CONFLICT:')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflit : le panneau externe ' . substr($msg, 9) . ' est déjà réservé sur cette période.',
                ], 409);
            }
            if (str_starts_with($msg, 'DUPLICATE:')) {
                return response()->json(['success' => false, 'message' => substr($msg, 10)], 422);
            }
            if (str_starts_with($msg, 'NOT_FOUND:')) {
                return response()->json(['success' => false, 'message' => substr($msg, 10)], 404);
            }
            throw $e;
        }

        AlertService::create(
            'reservation',
            'info',
            '➕ Panneau externe ajouté — ' . $reservation->reference,
            auth()->user()?->name . ' a ajouté un panneau externe à la réservation ' . $reservation->reference,
            $reservation
        );

        return response()->json(['success' => true, 'message' => 'Panneau externe ajouté avec succès.']);
    }

}
