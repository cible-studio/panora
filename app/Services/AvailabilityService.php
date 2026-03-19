<?php
namespace App\Services;

use App\Enums\PanelStatus;
use App\Enums\ReservationStatus;
use App\Models\Panel;
use App\Models\ReservationPanel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    private const BLOCKING = [
        ReservationStatus::EN_ATTENTE->value,
        ReservationStatus::CONFIRME->value,
    ];

    // ── Vérifier un seul panneau ──────────────────────────────────
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

    // ── IDs en conflit — condition stricte ────────────────────────
    public function getUnavailablePanelIds(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): array {
        if (empty($panelIds)) return [];

        return ReservationPanel::whereIn('panel_id', $panelIds)
            ->whereHas('reservation', function ($q) use (
                $startDate, $endDate, $excludeReservationId
            ) {
                $q->whereIn('status', self::BLOCKING)
                  ->where('start_date', '<', $endDate)
                  ->where('end_date',   '>', $startDate);

                if ($excludeReservationId) {
                    $q->where('id', '!=', $excludeReservationId);
                }
            })
            ->pluck('panel_id')
            ->unique()
            ->toArray();
    }

    // ── Panneaux disponibles — sous-requête optimisée ─────────────
    public function getAvailablePanels(
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        array  $filters = []
    ): Collection {
        $blockedIds = ReservationPanel::select('panel_id')
            ->whereHas('reservation', fn($q) =>
                $q->whereIn('status', self::BLOCKING)
                  ->where('start_date', '<', $endDate)
                  ->where('end_date',   '>', $startDate)
                  ->when($excludeReservationId,
                      fn($q) => $q->where('id', '!=', $excludeReservationId)
                  )
            );

        return Panel::whereNotIn('id', $blockedIds)
            ->where('status', '!=', PanelStatus::MAINTENANCE->value)
            ->whereNull('deleted_at')
            ->when($filters['commune_id'] ?? null,
                fn($q, $id) => $q->where('commune_id', $id))
            ->when($filters['zone_id'] ?? null,
                fn($q, $id) => $q->where('zone_id', $id))
            ->when($filters['format_id'] ?? null,
                fn($q, $id) => $q->where('format_id', $id))
            ->when(
                ($filters['format_width'] ?? null) || ($filters['format_height'] ?? null),
                fn($q) => $q->whereHas('format', function ($fq) use ($filters) {
                    if ($filters['format_width'] ?? null) {
                        $fq->whereBetween('width', [
                            (float) $filters['format_width'] - 0.01,
                            (float) $filters['format_width'] + 0.01,
                        ]);
                    }
                    if ($filters['format_height'] ?? null) {
                        $fq->whereBetween('height', [
                            (float) $filters['format_height'] - 0.01,
                            (float) $filters['format_height'] + 0.01,
                        ]);
                    }
                })
            )
            ->with(['commune', 'format', 'zone'])
            ->orderBy('reference')
            ->get();
    }

    // ── Sync statuts — VERSION OPTIMISÉE (5 requêtes max) ────────
    public function syncPanelStatuses(array $panelIds): void
    {
        if (empty($panelIds)) return;

        $today = now()->toDateString();

        $panels = Panel::whereIn('id', $panelIds)->get()->keyBy('id');

        // Panneaux avec au moins une réservation confirmée active
        $confirmedPanelIds = ReservationPanel::whereIn('panel_id', $panelIds)
            ->whereHas('reservation', fn($q) =>
                $q->where('status', ReservationStatus::CONFIRME->value)
                  ->where('end_date', '>=', $today)
            )
            ->pluck('panel_id')
            ->unique()
            ->flip();

        // Panneaux avec au moins une option active
        $optionPanelIds = ReservationPanel::whereIn('panel_id', $panelIds)
            ->whereHas('reservation', fn($q) =>
                $q->where('status', ReservationStatus::EN_ATTENTE->value)
                  ->where('end_date', '>=', $today)
            )
            ->pluck('panel_id')
            ->unique()
            ->flip();

        $toConfirme = [];
        $toOption   = [];
        $toLibre    = [];

        foreach ($panels as $panel) {
            // Maintenance — intouchable
            if ($panel->status === PanelStatus::MAINTENANCE) continue;

            if (isset($confirmedPanelIds[$panel->id])) {
                $toConfirme[] = $panel->id;
            } elseif (isset($optionPanelIds[$panel->id])) {
                $toOption[]   = $panel->id;
            } else {
                $toLibre[]    = $panel->id;
            }
        }

        // 3 UPDATE groupés max
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
    }

    // ── Vérification rapide sans chargement modèle ────────────────
    public function quickCheck(int $panelId, string $start, string $end): bool
    {
        return ! DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->where('reservation_panels.panel_id', $panelId)
            ->whereIn('reservations.status', self::BLOCKING)
            ->where('reservations.start_date', '<', $end)
            ->where('reservations.end_date',   '>', $start)
            ->exists();
    }
}