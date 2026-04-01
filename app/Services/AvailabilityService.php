<?php
namespace App\Services;

use App\Enums\PanelStatus;
use App\Enums\ReservationStatus;
use App\Models\Panel;
use App\Models\ReservationPanel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AvailabilityService — Source de vérité unique pour la disponibilité des panneaux.
 *
 * RÈGLE FONDAMENTALE :
 * Un panneau est BLOQUÉ si reservation_panels contient une ligne
 * liée à une réservation (en_attente OU confirme) dont la période
 * chevauche la période demandée.
 *
 * panels.status = cache de lecture — JAMAIS utilisé pour décider
 * de la disponibilité réelle.
 */
class AvailabilityService
{
    // Statuts qui bloquent un panneau — source unique
    public const BLOCKING_STATUSES = [
        ReservationStatus::EN_ATTENTE->value,
        ReservationStatus::CONFIRME->value,
    ];

    // ── 1. Vérifier UN panneau ────────────────────────────────────
    public function isPanelAvailable(
        int    $panelId,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): bool {
        return empty($this->getUnavailablePanelIds(
            [$panelId], $startDate, $endDate, $excludeReservationId
        ));
    }

    // ── 2. IDs bloqués parmi une liste — requête optimisée ────────
    public function getUnavailablePanelIds(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): array {
        if (empty($panelIds)) return [];

        return ReservationPanel::select('reservation_panels.panel_id')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $panelIds)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<', $endDate)   // chevauchement strict
            ->where('reservations.end_date',   '>', $startDate) // chevauchement strict
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->distinct()
            ->pluck('reservation_panels.panel_id')
            ->toArray();
    }

    // ── 3. Panneaux disponibles avec données de libération ────────
    public function getAvailablePanels(
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        array  $filters = []
    ): Collection {
        // Sous-requête : IDs des panneaux bloqués sur la période
        $blockedSubQuery = ReservationPanel::select('panel_id')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<', $endDate)
            ->where('reservations.end_date',   '>', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            );

        $query = Panel::whereNotIn('id', $blockedSubQuery)
            ->where('status', '!=', PanelStatus::MAINTENANCE->value)
            ->whereNull('deleted_at')
            ->with(['commune:id,name', 'format:id,name,width,height', 'zone:id,name']);

        if ($filters['commune_id'] ?? null) {
            $query->where('commune_id', (int)$filters['commune_id']);
        }
        if ($filters['zone_id'] ?? null) {
            $query->where('zone_id', (int)$filters['zone_id']);
        }
        if ($filters['format_id'] ?? null) {
            $query->where('format_id', (int)$filters['format_id']);
        }
        if (($filters['format_width'] ?? null) && ($filters['format_height'] ?? null)) {
            $query->whereHas('format', fn($q) =>
                $q->whereBetween('width',  [(float)$filters['format_width']  - 0.01, (float)$filters['format_width']  + 0.01])
                  ->whereBetween('height', [(float)$filters['format_height'] - 0.01, (float)$filters['format_height'] + 0.01])
            );
        }

        return $query->orderBy('reference')->get();
    }    

    // ── 5. Sync statuts — transaction atomique, 3 UPDATE max ──────
    public function syncPanelStatuses(array $panelIds): void
    {
        if (empty($panelIds)) return;

        $today = now()->toDateString();

        // Une requête pour tous les statuts actifs
        $activeBookings = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $panelIds)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.end_date', '>=', $today)
            ->select(
                'reservation_panels.panel_id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option')
            )
            ->groupBy('reservation_panels.panel_id')
            ->get()
            ->keyBy('panel_id');

        // Panneau maintenance → intouchable
        $maintenanceIds = Panel::whereIn('id', $panelIds)
            ->where('status', PanelStatus::MAINTENANCE->value)
            ->pluck('id')
            ->flip();

        $toConfirme = [];
        $toOption   = [];
        $toLibre    = [];

        foreach ($panelIds as $id) {
            if (isset($maintenanceIds[$id])) continue;

            $booking = $activeBookings->get($id);
            if ($booking?->has_confirmed) {
                $toConfirme[] = $id;
            } elseif ($booking?->has_option) {
                $toOption[]   = $id;
            } else {
                $toLibre[]    = $id;
            }
        }

        // 3 UPDATE groupés max — atomique
        DB::transaction(function () use ($toConfirme, $toOption, $toLibre) {
            if (!empty($toConfirme)) {
                Panel::whereIn('id', $toConfirme)
                    ->update(['status' => PanelStatus::CONFIRME->value]);
            }
            if (!empty($toOption)) {
                Panel::whereIn('id', $toOption)
                    ->update(['status' => PanelStatus::OPTION->value]);
            }
            if (!empty($toLibre)) {
                Panel::whereIn('id', $toLibre)
                    ->update(['status' => PanelStatus::LIBRE->value]);
            }
        });

        Log::debug('availability.sync_done', [
            'confirme' => count($toConfirme),
            'option'   => count($toOption),
            'libre'    => count($toLibre),
        ]);
    }

    // ── 6. Vérification rapide sans chargement modèle ────────────
    public function quickCheck(int $panelId, string $start, string $end): bool
    {
        return !DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->where('reservation_panels.panel_id', $panelId)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<', $end)
            ->where('reservations.end_date',   '>', $start)
            ->exists();
    }

    /**
     * Récupère les données de disponibilité complètes pour une liste de panneaux
     * Retourne pour chaque panneau : disponible, date de libération, statut bloquant
     */
    public function getPanelAvailabilityData(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($panelIds)) return collect();

        $bookings = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->join('clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('campaigns', 'campaigns.reservation_id', '=', 'reservations.id')
            ->whereIn('reservation_panels.panel_id', $panelIds)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<', $endDate)
            ->where('reservations.end_date',   '>', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select(
                'reservation_panels.panel_id',
                'reservations.status as res_status',
                'reservations.start_date',
                'reservations.end_date',
                'reservations.reference as reservation_ref',
                'clients.name as client_name',
                'campaigns.name as campaign_name',
                DB::raw('MAX(reservations.end_date) as release_date')
            )
            ->groupBy(
                'reservation_panels.panel_id',
                'reservations.status',
                'reservations.start_date',
                'reservations.end_date',
                'reservations.reference',
                'clients.name',
                'campaigns.name'
            )
            ->get()
            ->groupBy('panel_id');

        return collect($panelIds)->mapWithKeys(function ($id) use ($bookings) {
            $panelBookings = $bookings->get($id);
            if (!$panelBookings || $panelBookings->isEmpty()) {
                return [$id => [
                    'available'       => true,
                    'release_date'    => null,
                    'blocking_status' => null,
                    'occupations'     => [],
                ]];
            }

            // Prioriser confirme sur en_attente pour la date de libération principale
            $confirmed = $panelBookings->firstWhere('res_status', ReservationStatus::CONFIRME->value);
            $blocking = $confirmed ?? $panelBookings->first();

            // Récupérer toutes les occupations pour affichage
            $occupations = $panelBookings->map(function ($booking) {
                return [
                    'client_name'     => $booking->client_name,
                    'campaign_name'   => $booking->campaign_name,
                    'reservation_ref' => $booking->reservation_ref,
                    'start_date'      => \Carbon\Carbon::parse($booking->start_date)->format('d/m/Y'),
                    'end_date'        => \Carbon\Carbon::parse($booking->end_date)->format('d/m/Y'),
                    'status'          => $booking->res_status,
                ];
            })->values();

            return [$id => [
                'available'       => false,
                'release_date'    => $blocking->release_date,
                'blocking_status' => $blocking->res_status,
                'occupations'     => $occupations,
            ]];
        });
    }
}