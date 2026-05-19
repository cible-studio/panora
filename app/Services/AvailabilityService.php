<?php
namespace App\Services;

use App\Enums\PanelStatus;
use App\Enums\ReservationStatus;
use App\Models\ExternalPanel;
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
 *
 * ─── ANTI DOUBLE-BOOKING (pattern d'usage) ──────────────────────────────
 * À CHAQUE création/ajout de panneau qui pourrait entrer en conflit, le
 * code appelant DOIT respecter ce pattern (verrouillage pessimiste) :
 *
 *   DB::transaction(function () use ($panelIds, ...) {
 *       Panel::whereIn('id', $panelIds)->lockForUpdate()->get();    // ① verrou
 *       $conflicts = $availability->getUnavailablePanelIds(...);     // ② re-check
 *       if (!empty($conflicts)) { throw new RuntimeException(...); } // ③ rollback
 *       // ... attach + create
 *   });
 *
 * Le verrou pessimiste (SELECT ... FOR UPDATE) bloque toute autre transaction
 * concurrente qui tenterait `lockForUpdate()` sur les mêmes panneaux jusqu'au
 * COMMIT/ROLLBACK. Combiné au re-check après verrou, on a une garantie forte :
 *   - Avec 2 utilisateurs simultanés sur le même panneau, 1 seul peut commiter.
 *   - Le second voit `getUnavailablePanelIds()` détecter le conflit et rollback.
 *
 * Niveau d'isolation requis : REPEATABLE READ (défaut MySQL InnoDB) ou supérieur.
 * Sous READ COMMITTED, le re-check pourrait passer entre 2 transactions concurrentes.
 *
 * Index DB optimal pour la requête de chevauchement (cf. migration
 * 2026_05_01_120000_add_overlap_index_for_anti_double_booking) :
 *   `idx_reservations_overlap (status, start_date, end_date)`
 * ────────────────────────────────────────────────────────────────────────
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

    // Statuts de CAMPAGNE qui bloquent un panneau. Une fois la résa
    // confirmée et la campagne créée, c'est cette dernière qui porte
    // l'engagement sur la période. Sans inclure ce check, on perd la
    // trace après confirmation (bug critique de double-booking).
    public const BLOCKING_CAMPAIGN_STATUSES = [
        'planifie',
        'actif',
        'pause',  // pause = engagement maintenu, retour possible
    ];

    // ── 2. IDs bloqués parmi une liste — requête optimisée ────────
    public function getUnavailablePanelIds(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null
    ): array {
        if (empty($panelIds)) return [];

        // Check 1 — Réservations actives (en_attente / confirme) qui chevauchent
        $fromReservations = ReservationPanel::select('reservation_panels.panel_id')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $panelIds)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            // Chevauchement INCLUSIF : end_date est compté comme journée occupée
            // (cohérent avec la facturation jusqu'au end_date inclus). Deux
            // réservations 1→5 et 5→10 chevauchent sur le 5.
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->distinct()
            ->pluck('reservation_panels.panel_id')
            ->toArray();

        // Check 2 — Campagnes (planifiée / active / pause) qui chevauchent
        // Une résa peut être confirmée mais sa campagne pas encore active —
        // ou la résa peut transiter (ex: TERMINE) tandis que la campagne
        // reste engagée. Sans ce 2e check, le panneau apparaît libre alors
        // qu'il est déjà attribué à une campagne contractuellement actée.
        $fromCampaigns = DB::table('campaign_panels')
            ->select('campaign_panels.panel_id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $panelIds)
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $endDate)
            ->where('campaigns.end_date',   '>=', $startDate)
            ->whereNull('campaigns.deleted_at')
            ->when($excludeCampaignId, fn($q) =>
                $q->where('campaigns.id', '!=', $excludeCampaignId)
            )
            ->distinct()
            ->pluck('campaign_panels.panel_id')
            ->toArray();

        return array_values(array_unique(array_merge($fromReservations, $fromCampaigns)));
    }

    // ── 2.b — Map panel_id → ['end_date'=>Carbon, 'source'=>'reservation|campaign']
    // Utilisé pour suggérer "Libre à partir du XX/XX" en UI quand un
    // panneau est partiellement occupé sur la période demandée.
    public function getOccupationDetails(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null
    ): array {
        if (empty($panelIds)) return [];

        $details = [];

        // Réservations qui occupent
        $rResults = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $panelIds)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select('reservation_panels.panel_id',
                     'reservations.id as res_id',
                     'reservations.reference',
                     'reservations.start_date',
                     'reservations.end_date',
                     'reservations.status')
            ->get();

        foreach ($rResults as $r) {
            $pid = (int) $r->panel_id;
            $end = \Carbon\Carbon::parse($r->end_date);
            if (!isset($details[$pid]) || $end->gt($details[$pid]['end_date'])) {
                $details[$pid] = [
                    'end_date'  => $end,
                    'next_free' => $end->copy()->addDay(),
                    'source'    => 'reservation',
                    'reference' => $r->reference,
                    'status'    => $r->status,
                ];
            }
        }

        // Campagnes qui occupent
        $cResults = DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $panelIds)
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $endDate)
            ->where('campaigns.end_date',   '>=', $startDate)
            ->whereNull('campaigns.deleted_at')
            ->when($excludeCampaignId, fn($q) =>
                $q->where('campaigns.id', '!=', $excludeCampaignId)
            )
            ->select('campaign_panels.panel_id',
                     'campaigns.id as camp_id',
                     'campaigns.name',
                     'campaigns.start_date',
                     'campaigns.end_date',
                     'campaigns.status')
            ->get();

        foreach ($cResults as $c) {
            $pid = (int) $c->panel_id;
            $end = \Carbon\Carbon::parse($c->end_date);
            if (!isset($details[$pid]) || $end->gt($details[$pid]['end_date'])) {
                $details[$pid] = [
                    'end_date'  => $end,
                    'next_free' => $end->copy()->addDay(),
                    'source'    => 'campaign',
                    'reference' => $c->name,
                    'status'    => $c->status,
                ];
            }
        }

        return $details;
    }

    // ── 3. Panneaux disponibles avec données de libération ────────
    public function getAvailablePanels(
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        array  $filters = []
    ): Collection {
        // Sous-requête : IDs bloqués via RÉSERVATIONS (overlap inclusif)
        $blockedByReservations = ReservationPanel::select('panel_id')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            );

        // Sous-requête : IDs bloqués via CAMPAGNES — indispensable car
        // après confirmation d'une résa et création de campagne, c'est
        // cette dernière qui porte l'engagement actif. Sans ce filtre,
        // le panneau réapparaît "disponible" sur la dispo.
        $blockedByCampaigns = DB::table('campaign_panels')
            ->select('panel_id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $endDate)
            ->where('campaigns.end_date',   '>=', $startDate)
            ->whereNull('campaigns.deleted_at');

        $query = Panel::whereNotIn('id', $blockedByReservations)
            ->whereNotIn('id', $blockedByCampaigns)
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

    // ── 5. Sync statuts — transaction atomique, 4 UPDATE max ──────
    public function syncPanelStatuses(array $panelIds): void
    {
        if (empty($panelIds)) return;

        $today = now()->toDateString();

        // Réservations actives (en_attente / confirme) qui couvrent today
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

        // Lot 6 : campagnes actuellement EN COURS (actif / pose) couvrant today.
        // Un panneau réellement affiché = statut "occupé" (≠ "confirme" qui
        // signifie booké mais pas encore en pose). Cette nouvelle requête
        // permet de propager l'occupation effective dans l'inventaire.
        $occupiedByCampaign = DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->whereIn('campaign_panels.panel_id', $panelIds)
            ->where('campaign_panels.type', 'interne')
            ->where('campaigns.status', 'actif')
            ->where('campaigns.start_date', '<=', $today)
            ->where('campaigns.end_date',   '>=', $today)
            ->pluck('campaign_panels.panel_id')
            ->flip();

        // Maintenance → intouchable par la sync (l'admin contrôle manuellement)
        $maintenanceIds = Panel::whereIn('id', $panelIds)
            ->where('status', PanelStatus::MAINTENANCE->value)
            ->pluck('id')
            ->flip();

        $toOccupe   = [];
        $toConfirme = [];
        $toOption   = [];
        $toLibre    = [];

        // Priorité : maintenance > occupe (campagne en cours) > confirme
        // (réservation confirmée pour plus tard) > option > libre.
        foreach ($panelIds as $id) {
            if (isset($maintenanceIds[$id])) continue;

            if (isset($occupiedByCampaign[$id])) {
                $toOccupe[] = $id;
                continue;
            }

            $booking = $activeBookings->get($id);
            if ($booking?->has_confirmed) {
                $toConfirme[] = $id;
            } elseif ($booking?->has_option) {
                $toOption[]   = $id;
            } else {
                $toLibre[]    = $id;
            }
        }

        // 4 UPDATE groupés max — atomique
        DB::transaction(function () use ($toOccupe, $toConfirme, $toOption, $toLibre) {
            if (!empty($toOccupe)) {
                Panel::whereIn('id', $toOccupe)
                    ->update(['status' => PanelStatus::OCCUPE->value]);
            }
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
            'occupe'   => count($toOccupe),
            'confirme' => count($toConfirme),
            'option'   => count($toOption),
            'libre'    => count($toLibre),
        ]);
    }

    // ── 5b. Sync statuts EXTERNES — même règle, table external_panels ──────
    public function syncExternalPanelStatuses(array $externalPanelIds): void
    {
        if (empty($externalPanelIds)) return;

        $today = now()->toDateString();

        $activeBookings = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.external_panel_id', $externalPanelIds)
            ->where('reservation_panels.source', 'externe')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.end_date', '>=', $today)
            ->select(
                'reservation_panels.external_panel_id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option')
            )
            ->groupBy('reservation_panels.external_panel_id')
            ->get()
            ->keyBy('external_panel_id');

        // Maintenance → intouchable
        $maintenanceIds = ExternalPanel::whereIn('id', $externalPanelIds)
            ->where('availability_status', 'maintenance')
            ->pluck('id')
            ->flip();

        $toConfirme = [];
        $toOption   = [];
        $toLibre    = [];

        foreach ($externalPanelIds as $id) {
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

        DB::transaction(function () use ($toConfirme, $toOption, $toLibre) {
            if (!empty($toConfirme)) {
                ExternalPanel::whereIn('id', $toConfirme)
                    ->update(['availability_status' => 'confirme']);
            }
            if (!empty($toOption)) {
                ExternalPanel::whereIn('id', $toOption)
                    ->update(['availability_status' => 'option']);
            }
            if (!empty($toLibre)) {
                ExternalPanel::whereIn('id', $toLibre)
                    ->update(['availability_status' => 'disponible']);
            }
        });

        Log::debug('availability.sync_external_done', [
            'confirme' => count($toConfirme),
            'option'   => count($toOption),
            'libre'    => count($toLibre),
        ]);
    }

    /**
     * Panneaux externes occupés sur la période (par leurs propres réservations).
     * Renvoie l'ID externe → état dominant ('confirme' / 'en_attente').
     */
    public function getExternalPanelBookingMap(
        array  $externalPanelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($externalPanelIds)) return collect();

        return DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.external_panel_id', $externalPanelIds)
            ->where('reservation_panels.source', 'externe')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select(
                'reservation_panels.external_panel_id as id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option'),
                DB::raw('MAX(reservations.end_date) as release_date')
            )
            ->groupBy('reservation_panels.external_panel_id')
            ->get()
            ->keyBy('id');
    }

    /**
     * Pendant interne de getExternalPanelBookingMap — renvoie pour chaque
     * panel_id : has_confirmed, has_option, release_date (dernière fin de
     * réservation bloquante). Utilisé par les vues d'export pour rétablir
     * le `display_status` réel sur la période demandée (sinon on perd
     * l'info "En option" entre la sélection et le PDF).
     */
    public function getInternalPanelBookingMap(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($panelIds)) return collect();

        return DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservation_panels.panel_id', $panelIds)
            ->where('reservation_panels.source', 'interne')
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) =>
                $q->where('reservations.id', '!=', $excludeReservationId)
            )
            ->select(
                'reservation_panels.panel_id',
                DB::raw('MAX(CASE WHEN reservations.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(CASE WHEN reservations.status = "en_attente" THEN 1 ELSE 0 END) as has_option'),
                DB::raw('MAX(reservations.end_date) as release_date')
            )
            ->groupBy('reservation_panels.panel_id')
            ->get()
            ->keyBy('panel_id');
    }

    // ── 6. Cartes de conflits avec référence — pour sauvegarde partielle ──

    /**
     * Retourne pour chaque panneau interne bloqué : statut bloquant,
     * référence de la réservation conflictuelle, date de libération.
     * Utilisé par confirmerSelection() pour identifier les panneaux à exclure
     * avec le motif précis (confirme vs en_attente + quelle réservation bloque).
     *
     * Keyed by panel_id. Priorité : confirme > en_attente.
     */
    public function getInternalConflictMap(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        ?int   $excludeCampaignId    = null
    ): Collection {
        if (empty($panelIds)) return collect();

        // ── Source 1 : RÉSERVATIONS en_attente / confirme ─────────
        $fromReservations = DB::table('reservation_panels as rp')
            ->join('reservations as r', 'r.id', '=', 'rp.reservation_id')
            ->whereIn('rp.panel_id', $panelIds)
            ->whereIn('r.status', self::BLOCKING_STATUSES)
            ->where('r.start_date', '<=', $endDate)
            ->where('r.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) => $q->where('r.id', '!=', $excludeReservationId))
            ->select(
                'rp.panel_id',
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN r.reference ELSE NULL END) as confirme_ref'),
                DB::raw('MAX(CASE WHEN r.status = "en_attente" THEN r.reference ELSE NULL END) as option_ref'),
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(r.end_date) as release_date'),
            )
            ->groupBy('rp.panel_id')
            ->get()
            ->keyBy('panel_id');

        // ── Source 2 : CAMPAGNES planifie / actif / pause ─────────
        // Indispensable : une résa confirmée crée une campagne, et
        // c'est CETTE campagne qui porte ensuite l'engagement. Sans
        // ce check, le panneau "disparaît" après confirmation et le
        // double-booking devient possible (bug critique terrain).
        $fromCampaigns = DB::table('campaign_panels as cp')
            ->join('campaigns as c', 'c.id', '=', 'cp.campaign_id')
            ->whereIn('cp.panel_id', $panelIds)
            ->where('cp.type', 'interne')
            ->whereIn('c.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('c.start_date', '<=', $endDate)
            ->where('c.end_date',   '>=', $startDate)
            ->whereNull('c.deleted_at')
            ->when($excludeCampaignId, fn($q) => $q->where('c.id', '!=', $excludeCampaignId))
            ->select(
                'cp.panel_id',
                DB::raw('MAX(c.reference) as camp_ref'),
                DB::raw('MAX(c.name) as camp_name'),
                DB::raw('MAX(c.end_date) as release_date'),
            )
            ->groupBy('cp.panel_id')
            ->get()
            ->keyBy('panel_id');

        // ── Merge : union des deux sources, plus tardif gagne ──
        $merged = collect();
        $allKeys = $fromReservations->keys()->merge($fromCampaigns->keys())->unique();
        foreach ($allKeys as $pid) {
            $r = $fromReservations->get($pid);
            $c = $fromCampaigns->get($pid);
            // Campagne = engagement signé → priorité sur réservation
            if ($c) {
                $merged->put($pid, (object)[
                    'blocking_status' => 'campagne_active',
                    'conflicting_ref' => $c->camp_ref ?: $c->camp_name,
                    'release_date'    => $r && $r->release_date > $c->release_date
                        ? $r->release_date
                        : $c->release_date,
                ]);
            } else {
                $merged->put($pid, (object)[
                    'blocking_status' => (bool) $r->has_confirmed ? 'confirme' : 'en_attente',
                    'conflicting_ref' => (bool) $r->has_confirmed ? $r->confirme_ref : $r->option_ref,
                    'release_date'    => $r->release_date,
                ]);
            }
        }
        return $merged;
    }

    /**
     * Pendant externe de getInternalConflictMap.
     * Keyed by external_panel_id. Priorité : confirme > en_attente.
     */
    public function getExternalConflictMap(
        array  $externalPanelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): Collection {
        if (empty($externalPanelIds)) return collect();

        return DB::table('reservation_panels as rp')
            ->join('reservations as r', 'r.id', '=', 'rp.reservation_id')
            ->whereIn('rp.external_panel_id', $externalPanelIds)
            ->where('rp.source', 'externe')
            ->whereIn('r.status', self::BLOCKING_STATUSES)
            ->where('r.start_date', '<=', $endDate)
            ->where('r.end_date',   '>=', $startDate)
            ->when($excludeReservationId, fn($q) => $q->where('r.id', '!=', $excludeReservationId))
            ->select(
                'rp.external_panel_id',
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN r.reference ELSE NULL END) as confirme_ref'),
                DB::raw('MAX(CASE WHEN r.status = "en_attente" THEN r.reference ELSE NULL END) as option_ref'),
                DB::raw('MAX(CASE WHEN r.status = "confirme"   THEN 1 ELSE 0 END) as has_confirmed'),
                DB::raw('MAX(r.end_date) as release_date'),
            )
            ->groupBy('rp.external_panel_id')
            ->get()
            ->keyBy('external_panel_id')
            ->map(fn($row) => (object)[
                'blocking_status' => (bool) $row->has_confirmed ? 'confirme' : 'en_attente',
                'conflicting_ref' => (bool) $row->has_confirmed ? $row->confirme_ref : $row->option_ref,
                'release_date'    => $row->release_date,
            ]);
    }

    // ── 7. Vérification rapide sans chargement modèle ────────────
    public function quickCheck(int $panelId, string $start, string $end): bool
    {
        $blockedByResa = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->where('reservation_panels.panel_id', $panelId)
            ->whereIn('reservations.status', self::BLOCKING_STATUSES)
            ->where('reservations.start_date', '<=', $end)
            ->where('reservations.end_date',   '>=', $start)
            ->exists();
        if ($blockedByResa) return false;

        // 2e source : campagnes actives (planifie/actif/pause)
        $blockedByCamp = DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->where('campaign_panels.panel_id', $panelId)
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaigns.status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->where('campaigns.start_date', '<=', $end)
            ->where('campaigns.end_date',   '>=', $start)
            ->whereNull('campaigns.deleted_at')
            ->exists();

        return !$blockedByCamp;
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
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.end_date',   '>=', $startDate)
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