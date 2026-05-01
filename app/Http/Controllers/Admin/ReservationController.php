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
        if (in_array($source, ['external', 'all']) && !$dateError) {
            $extQuery = \App\Models\ExternalPanel::with(['agency:id,name', 'commune:id,name'])
                ->whereHas('agency', fn($q) => $q->where('is_active', true)->whereNull('deleted_at'));

            if (!empty($communeIds))
                $extQuery->whereIn('commune_id', $communeIds);
            // zone_id / format_id / is_lit / availability_status ne filtrent que si la colonne existe
            if (!empty($agencyIds))
                $extQuery->whereIn('agency_id', $agencyIds);

            if ($search !== '') {
                $like = '%' . $search . '%';
                $extQuery->where(
                    fn($q) =>
                    $q->where('code_panneau', 'like', $like)->orWhere('designation', 'like', $like)
                );
            }

            // Filtres statut uniquement si colonne availability_status existe
            try {
                if ($statut === 'libre')
                    $extQuery->where('availability_status', 'disponible');
                elseif ($statut === 'occupe')
                    $extQuery->where('availability_status', 'occupe');
                elseif (in_array($statut, ['maintenance', 'confirme', 'option']))
                    $extQuery->whereRaw('1=0');
            } catch (\Exception $e) {
                // colonne inexistante — on ignore le filtre statut pour les externes
            }

            $extPanels = $extQuery->orderBy('code_panneau')->get();
            $externalResult = $extPanels->map(fn($p) => self::formatExternalPanel($p, $startDate, $endDate));
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

    private static function formatExternalPanel($panel, $startDate, $endDate): array
    {
        // La table external_panels peut avoir des colonnes limitées
        // On utilise ?? pour éviter les erreurs sur colonnes manquantes
        $availStatus = 'disponible';
        if (method_exists($panel, 'getDisplayStatusForPeriod')) {
            $availStatus = $panel->getDisplayStatusForPeriod($startDate, $endDate);
        } elseif (isset($panel->availability_status)) {
            $availStatus = $panel->availability_status;
        }

        $displayStatus = match ($availStatus) {
            'disponible' => 'libre',
            'occupe' => 'occupe',
            default => 'a_verifier',
        };

        $releaseInfo = null;
        $availableFrom = $panel->available_from ?? null;
        if ($displayStatus === 'occupe' && $availableFrom) {
            $rd = Carbon::parse($availableFrom)->startOfDay();
            $daysLeft = (int) now()->startOfDay()->diffInDays($rd, false);
            $releaseInfo = [
                'date' => $rd->format('d/m/Y'),
                'days_left' => $daysLeft,
                'label' => $daysLeft <= 0 ? 'Disponible bientôt'
                    : ($daysLeft === 1 ? 'Libre demain'
                        : "Libre le {$rd->format('d/m/Y')} ({$daysLeft}j)"),
                'color' => $daysLeft <= 0 ? 'green' : ($daysLeft <= 7 ? 'orange' : 'default'),
            ];
        }

        // Relations optionnelles (zone et format peuvent ne pas exister)
        $zone = method_exists($panel, 'zone') && $panel->relationLoaded('zone') ? $panel->zone : null;
        $format = method_exists($panel, 'format') && $panel->relationLoaded('format') ? $panel->format : null;

        return [
            'id' => 'ext_' . $panel->id,
            'source' => 'external',
            'reference' => $panel->code_panneau,
            'name' => $panel->designation,
            'commune' => $panel->commune?->name ?? '—',
            'commune_id' => $panel->commune_id,
            'zone' => $zone?->name ?? '—',
            'zone_id' => $panel->zone_id ?? null,
            'format' => $format?->name ?? '—',
            'format_id' => $panel->format_id ?? null,
            'dimensions' => $format ? self::buildDims($format) : null,
            'category' => $panel->type ?? '—',
            'agency_name' => $panel->agency?->name ?? '—',
            'agency_id' => $panel->agency_id,
            'is_lit' => (bool) ($panel->is_lit ?? false),
            'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
            'daily_traffic' => (int) ($panel->daily_traffic ?? 0),
            'zone_description' => $panel->zone_description ?? '',
            'photo_url' => null,
            'status_db' => $panel->availability_status ?? 'disponible',
            'display_status' => $displayStatus,
            'is_selectable' => in_array($displayStatus, ['libre', 'a_verifier']),
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
    // PDF — images
    // ══════════════════════════════════════════════════════════════
    // Dans ReservationController.php
    // Version finale optimisée et fonctionnelle
    public function pdfImages(Request $request)
    {
        $request->validate([
            'panel_ids' => 'required|array|min:1|max:200',
            'panel_ids.*' => 'integer|exists:panels,id',
        ]);

        $reservationRef = $request->reservation_ref ?? null;
        $clientName = $request->client_name ?? null;

        $panelModels = Panel::with([
            'commune:id,name',
            'zone:id,name',
            'format:id,name,width,height',
            'category:id,name',
            'photos' => fn($q) => $q->orderBy('ordre'),
        ])
            ->whereIn('id', $request->panel_ids)
            ->orderByRaw('FIELD(id,' . implode(',', array_map('intval', $request->panel_ids)) . ')')
            ->get();

        $startDate = $request->start_date ?? null;
        $endDate = $request->end_date ?? null;

        $panels = $panelModels->map(function ($panel) {

            $photo = $panel->photos->first();
            $photoPath = null;
            $photoUrl = null;

            if ($photo) {
                $rel = ltrim($photo->path, '/');

                foreach ([
                    storage_path('app/public/' . $rel),
                    public_path('storage/' . $rel),
                ] as $candidate) {
                    if (file_exists($candidate)) {
                        $photoPath = $candidate;
                        break;
                    }
                }

                if (!$photoPath) {
                    $photoUrl = asset('storage/' . $rel);
                }
            }

            $dims = null;
            if ($panel->format?->width && $panel->format?->height) {
                $w = rtrim(rtrim(number_format($panel->format->width, 2, '.', ''), '0'), '.');
                $h = rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.');
                $dims = "{$w}x{$h}m";
            }

            return [
                'id' => $panel->id,
                'reference' => $panel->reference,
                'name' => $panel->name,
                'commune' => $panel->commune?->name ?? '—',
                'zone' => $panel->zone?->name ?? '—',
                'format' => $panel->format?->name ?? '—',
                'dimensions' => $dims,
                'category' => $panel->category?->name ?? '—',
                'is_lit' => (bool) $panel->is_lit,
                'monthly_rate' => (float) ($panel->monthly_rate ?? 0),
                'daily_traffic' => (int) ($panel->daily_traffic ?? 0),
                'zone_description' => $panel->zone_description ?? '',
                'display_status' => $panel->status->value,
                'source' => 'internal',
                'photo_path' => $photoPath,
                'photo_url' => $photoUrl,
            ];
        });

        $filename = 'panneaux-' . now()->format('Ymd_His');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'admin.reservations.pdf.disponibilites-images',
            [
                'panels' => $panels,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'generated' => now()->format('d/m/Y'),
                'reservation_ref' => $reservationRef,
                'client_name' => $clientName,
            ]
        )
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ]);

        return $pdf->download($filename . '.pdf');
    }


    public function pdfListe(Request $request)
    {
        $request->validate([
            'panel_ids'    => 'required|array|min:1',
            'panel_ids.*'  => 'integer|exists:panels,id',
            'hide_status'  => 'nullable|boolean',
        ]);

        $panels = Panel::with(['commune', 'format', 'category'])
            ->whereIn('id', $request->panel_ids)
            ->orderBy('reference')
            ->get();

        $startDate    = $request->start_date;
        $endDate      = $request->end_date;
        $dureeEnMois  = ($startDate && $endDate)
            ? max(1, (int) ceil(Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) / 30))
            : 1;
        $totalMensuel = (float) $panels->sum(fn($p) => (float) ($p->monthly_rate ?? 0));
        $totalPeriode = $totalMensuel * $dureeEnMois;
        $generated    = now()->format('d/m/Y à H:i');
        $hideStatus   = (bool) $request->boolean('hide_status');
        $logoSrc      = $this->getLogoPdf();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reservations.pdf.disponibilites-list', compact(
            'panels',
            'startDate',
            'endDate',
            'dureeEnMois',
            'totalMensuel',
            'totalPeriode',
            'generated',
            'hideStatus',
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
     * EXPORT EXCEL — Liste des panneaux disponibles
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'panel_ids' => 'required|array|min:1',
            'panel_ids.*' => 'integer|exists:panels,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'hide_status' => 'nullable|boolean',
        ]);

        $panels = Panel::with(['commune', 'zone', 'format', 'category'])
            ->whereIn('id', $request->panel_ids)
            ->orderBy('reference')
            ->get();

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $hideStatus = $request->boolean('hide_status', false);

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
        $rawIds = (array) $request->input('panel_ids', []);
        $internalIds = [];
        $externalIds = [];

        foreach ($rawIds as $id) {
            if (is_string($id) && str_starts_with($id, 'ext_')) {
                $externalIds[] = (int) substr($id, 4);
            } elseif (is_numeric($id)) {
                $internalIds[] = (int) $id;
            }
        }

        $request->merge(['panel_ids' => $internalIds]);

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
            'panel_ids' => 'required|array|min:1|max:50',
            'panel_ids.*' => 'required|integer|exists:panels,id',
            'type' => 'required|in:option,ferme',
            'campaign_name' => 'nullable|string|max:150',
        ]);

        $maintenancePanels = Panel::whereIn('id', $internalIds)
            ->where('status', PanelStatus::MAINTENANCE->value)->pluck('reference');

        if ($maintenancePanels->isNotEmpty()) {
            return back()->withErrors(['panel_ids' => 'Panneaux en maintenance : ' . $maintenancePanels->join(', ')])->withInput();
        }

        $createdCampaignId = null;

        $reservation = null;

        try {
            DB::transaction(function () use ($request, $internalIds, $externalIds, &$createdCampaignId, &$reservation) {
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

                $status = $request->type === 'ferme' ? ReservationStatus::CONFIRME : ReservationStatus::EN_ATTENTE;
                $reference = $this->generateUniqueReference();
                $panelData = Panel::whereIn('id', $internalIds)->get()->keyBy('id');
                $months = $this->monthsBetween($request->start_date, $request->end_date);
                $total = 0;
                $attach = [];

                foreach ($internalIds as $panelId) {
                    $unit = (float) ($panelData[$panelId]->monthly_rate ?? 0);
                    $tot = $unit * $months;
                    $total += $tot;
                    $attach[$panelId] = ['unit_price' => $unit, 'total_price' => $tot];
                }

                $reservation = Reservation::create([
                    'reference' => $reference,
                    'client_id' => $request->client_id,
                    'user_id' => auth()->id(),
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'status' => $status,
                    'type' => $request->type,
                    'notes' => $request->notes,
                    'total_amount' => $total,
                    'confirmed_at' => $request->type === 'ferme' ? now() : null,
                ]);



                $reservation->panels()->attach($attach);
                $this->availability->syncPanelStatuses($internalIds);

                if ($request->type === 'ferme' && $request->filled('campaign_name')) {
                    if (Campaign::where('client_id', $request->client_id)->where('name', $request->campaign_name)->exists()) {
                        throw new \RuntimeException('CAMPAIGN_EXISTS:Une campagne avec ce nom existe déjà pour ce client.');
                    }

                    // Statut correct selon la date de début
                    $campStatus = now()->startOfDay()->lt(
                        \Carbon\Carbon::parse($request->start_date)->startOfDay()
                    )
                        ? CampaignStatus::PLANIFIE->value  // commence dans le futur
                        : CampaignStatus::ACTIF->value;    // commence aujourd'hui ou avant

                    $campaign = Campaign::create([
                        'name'           => $request->campaign_name,
                        'client_id'      => $request->client_id,
                        'reservation_id' => $reservation->id,
                        'user_id'        => auth()->id(),
                        'start_date'     => $request->start_date,
                        'end_date'       => $request->end_date,
                        'status'         => $campStatus,
                        'total_panels'   => count($internalIds),
                        'total_amount'   => $total,
                        'notes'          => $request->notes,
                    ]);


                    $campaign->panels()->sync(array_keys($attach));
                    $createdCampaignId = $campaign->id;
                }

                Log::info('reservation.created', [
                    'reference' => $reference,
                    'type' => $request->type,
                    'panels' => count($internalIds),
                    'ext' => count($externalIds),
                    'user_id' => auth()->id(),
                ]);

                // Alerte création réservation
                AlertService::create(
                    'reservation',
                    $status === ReservationStatus::CONFIRME ? 'info' : 'warning',
                    '📋 Nouvelle réservation — ' . ($reservation->client?->name ?? ''),
                    auth()->user()->name . ' a créé la réservation ' . $reservation->reference . ' (' . count($internalIds) . ' panneau(x))',
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
        ]);

        return response()->json([
            'reservation' => [
                'reference'  => $reservation->reference,
                'start_date' => $reservation->start_date->format('d/m/Y'),
                'end_date'   => $reservation->end_date->format('d/m/Y'),
                'status'     => $reservation->status->value,
                'count'      => $reservation->panels->count(),
            ],
            'panels' => $reservation->panels->map(fn($p) => [
                'id'           => $p->id,
                'reference'    => $p->reference,
                'name'         => $p->name,
                'commune'      => $p->commune?->name ?? '—',
                'format'       => $p->format?->name  ?? '—',
                'is_lit'       => (bool) $p->is_lit,
                'monthly_rate' => (float) ($p->pivot->unit_price ?? $p->monthly_rate ?? 0),
                'photo_url'    => $p->photos->first()
                    ? asset('storage/' . $p->photos->first()->path)
                    : null,
            ])->values(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Reservation::with(['client', 'user'])->withCount('panels');

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
        $reservation->load(['client', 'user', 'panels.commune', 'panels.format', 'panels.photos', 'campaign']);
        $user = auth()->user();
        $can = [
            'update' => $reservation->isEditable() && $user->can('update', $reservation),
            'updateStatus' => $reservation->canChangeStatus() && $user->can('updateStatus', $reservation),
            'annuler' => $reservation->isCancellable() && $user->can('annuler', $reservation),
            'delete' => $reservation->isDeletable() && $user->can('delete', $reservation),
        ];
        return view('admin.reservations.show', compact('reservation', 'can'));
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
    // AJAX — panneaux disponibles (page edit)
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

            return response()->json($panels->map(function ($p) use ($availabilityData) {
                $avail = $availabilityData->get($p->id, ['available' => true, 'release_date' => null, 'blocking_status' => null]);
                $releaseFmt = null;
                if ($avail['release_date']) {
                    $rd = Carbon::parse($avail['release_date']);
                    $daysLeft = (int) now()->startOfDay()->diffInDays($rd->startOfDay(), false);
                    $releaseFmt = $daysLeft <= 0 ? 'Libre maintenant'
                        : ($daysLeft === 1 ? 'Libre demain'
                            : 'Libre le ' . $rd->format('d/m/Y') . " ({$daysLeft}j)");
                }
                return [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'name' => $p->name,
                    'commune' => $p->commune?->name ?? '—',
                    'zone' => $p->zone?->name ?? '—',
                    'format' => $p->format?->name ?? '—',
                    'dimensions' => self::buildDims($p->format),
                    'is_lit' => (bool) $p->is_lit,
                    'monthly_rate' => (float) ($p->monthly_rate ?? 0),
                    'daily_traffic' => (int) ($p->daily_traffic ?? 0),
                    'available' => $avail['available'],
                    'release_date' => $releaseFmt,
                    'blocking_status' => $avail['blocking_status'],
                ];
            }));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('availablePanels.error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Erreur serveur'], 500);
        }
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

        $newTotal = $reservation->panels()->get()
            ->sum(fn($p) => (float) ($p->pivot->total_price ?? 0));
        $reservation->update(['total_amount' => $newTotal]);

        return back()->with('success', 'Prix remis au tarif catalogue.');
    }

    /**
     * AJOUTER UN PANNEAU À UNE RÉSERVATION EXISTANTE
     * POST /admin/reservations/{reservation}/panels/add
     */
    public function addPanel(Request $request, Reservation $reservation)
    {
        if (!$reservation->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Réservation non modifiable.'], 403);
        }

        $request->validate([
            'panel_id'   => 'required|integer|exists:panels,id',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $panelId = $request->panel_id;
        $unitPrice = $request->unit_price ?? null;

        // Vérifier que le panneau n'est pas déjà dans la réservation
        if ($reservation->panels()->where('panel_id', $panelId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ce panneau est déjà dans la réservation.'], 422);
        }

        // Vérifier la disponibilité du panneau sur la période
        $conflicts = $this->availability->getUnavailablePanelIds(
            [$panelId],
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d'),
            $reservation->id
        );

        if (!empty($conflicts)) {
            return response()->json(['success' => false, 'message' => 'Panneau non disponible sur cette période.'], 422);
        }

        $months = $this->monthsBetween(
            $reservation->start_date->format('Y-m-d'),
            $reservation->end_date->format('Y-m-d')
        );

        // Récupérer le prix catalogue si unit_price non fourni
        if ($unitPrice === null) {
            $panel = Panel::find($panelId);
            $unitPrice = $panel->monthly_rate ?? 0;
        }

        $totalPrice = $unitPrice * $months;

        // Ajouter le panneau
        $reservation->panels()->attach($panelId, [
            'unit_price'  => $unitPrice,
            'total_price' => $totalPrice,
        ]);

        // Recalculer le total de la réservation
        $newTotal = $reservation->panels()->sum(DB::raw('reservation_panels.total_price'));
        $reservation->update(['total_amount' => $newTotal]);

        // Mettre à jour le statut du panneau
        $this->availability->syncPanelStatuses([$panelId]);

        // Alerte d'ajout
        AlertService::create(
            'reservation',
            'info',
            '➕ Panneau ajouté — ' . $reservation->reference,
            auth()->user()->name . ' a ajouté un panneau à la réservation ' . $reservation->reference,
            $reservation
        );

        return response()->json(['success' => true, 'message' => 'Panneau ajouté avec succès.']);
    }

}
