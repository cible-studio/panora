<?php
namespace App\Services;

use App\Enums\PanelStatus;
use App\Enums\ReservationStatus;
use App\Models\Panel;
use App\Models\ReservationPanel;
use Illuminate\Support\Collection;

class AvailabilityService
{
    // Statuts qui bloquent un panneau
    private const BLOCKING = [
        ReservationStatus::EN_ATTENTE->value,
        ReservationStatus::CONFIRME->value,
    ];

    // ── Vérifier un seul panneau ───────────────────────────
    public function isPanelAvailable(
        int    $panelId,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): bool {
        return ! $this->conflictSubquery($startDate, $endDate, $excludeReservationId)
            ->where('panel_id', $panelId)
            ->exists();
    }

    // ── IDs en conflit parmi une liste ─────────────────────
    public function getUnavailablePanelIds(
        array  $panelIds,
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null
    ): array {
        return $this->conflictSubquery($startDate, $endDate, $excludeReservationId)
            ->whereIn('panel_id', $panelIds)
            ->pluck('panel_id')
            ->unique()
            ->values()
            ->toArray();
    }

    // ── Panneaux disponibles pour une période ──────────────
    // Utilise une sous-requête SQL — pas de chargement en mémoire
    public function getAvailablePanels(
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId = null,
        array  $filters = []
        ): Collection {
    $blockedIds = ReservationPanel::select('panel_id')
        ->whereHas('reservation', fn($q) =>
            $q->whereIn('status', self::BLOCKING)
              ->where('start_date', '<=', $endDate)
              ->where('end_date',   '>=', $startDate)
              ->when($excludeReservationId,
                  fn($q) => $q->where('id', '!=', $excludeReservationId)
              )
        );
 
    return Panel::whereNotIn('id', $blockedIds)
        ->where('status', '!=', PanelStatus::MAINTENANCE->value)
        ->whereNull('deleted_at')
        ->when($filters['commune_id'] ?? null, fn($q, $id) => $q->where('commune_id', $id))
        ->when($filters['zone_id']    ?? null, fn($q, $id) => $q->where('zone_id',    $id))
        ->when($filters['format_id']  ?? null, fn($q, $id) => $q->where('format_id',  $id))
 
        // ── Filtres par dimensions (via la relation format) ──────────────
        // ex: format_width=4, format_height=3 → panneaux 4×3m uniquement
        ->when(
            ($filters['format_width'] ?? null) || ($filters['format_height'] ?? null),
            fn($q) => $q->whereHas('format', function ($fq) use ($filters) {
                if ($filters['format_width'] ?? null) {
                    // Tolérance ±0.01m pour éviter les problèmes de float
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

    // ── Synchronise le statut des panneaux ─────────────────
    // À appeler après chaque création / modification / annulation
    public function syncPanelStatuses(array $panelIds): void
    {
        if (empty($panelIds)) return;

        $panels = Panel::whereIn('id', $panelIds)->get();

        foreach ($panels as $panel) {
            // Jamais toucher un panneau en maintenance
            if ($panel->status->value === PanelStatus::MAINTENANCE->value) {
                continue;
            }

            // Chercher la réservation active la plus prioritaire sur ce panneau
            // (on regarde toutes les périodes, pas seulement today,
            //  pour refléter l'état futur aussi)
            $hasConfirmed = ReservationPanel::where('panel_id', $panel->id)
                ->whereHas('reservation', fn($q) =>
                    $q->where('status', ReservationStatus::CONFIRME->value)
                      ->where('end_date', '>=', now()->toDateString())
                )
                ->exists();

            if ($hasConfirmed) {
                $panel->update(['status' => PanelStatus::CONFIRME->value]);
                continue;
            }

            $hasOption = ReservationPanel::where('panel_id', $panel->id)
                ->whereHas('reservation', fn($q) =>
                    $q->where('status', ReservationStatus::EN_ATTENTE->value)
                      ->where('end_date', '>=', now()->toDateString())
                )
                ->exists();

            $panel->update([
                'status' => $hasOption
                    ? PanelStatus::OPTION->value
                    : PanelStatus::LIBRE->value,
            ]);
        }
    }

    // ── Query de base conflits (réutilisable) ──────────────
    private function conflictSubquery(
        string $startDate,
        string $endDate,
        ?int   $excludeReservationId
    ) {
        return ReservationPanel::whereHas('reservation', fn($q) =>
            $q->whereIn('status', self::BLOCKING)
              ->where('start_date', '<=', $endDate)
              ->where('end_date',   '>=', $startDate)
              ->when($excludeReservationId,
                  fn($q) => $q->where('id', '!=', $excludeReservationId)
              )
        );
    }
}