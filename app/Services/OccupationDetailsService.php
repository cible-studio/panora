<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Commune;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * OccupationDetailsService — Vue « qui a occupé quel panneau, quand, pour qui »
 *
 * Alimente l'onglet "Occupation détaillée" de /admin/rapports.
 *
 * Une ligne par couple (panneau × campagne) dont la campagne CHEVAUCHE la
 * période demandée [from, to] : campaign.start_date <= to
 * ET campaign.end_date >= from.
 *
 * Statuts campagne inclus : brouillon, active, terminee, en_retard (pas les annulées).
 * Décapages remontés dans le champ 'decapped_at' (info seulement, la période
 * affichée reste celle de la campagne).
 */
class OccupationDetailsService
{
    /** Statuts campagne qui « occupent » un panneau (exclut annulée). */
    private const ACTIVE_STATUSES = [
        CampaignStatus::PLANIFIE->value,
        CampaignStatus::ACTIF->value,
        CampaignStatus::PAUSE->value,
        CampaignStatus::TERMINE->value,
    ];

    /**
     * Construit la liste des occupations sur la période.
     *
     * @param  Carbon  $from   Début période
     * @param  Carbon  $to     Fin période
     * @param  array   $filters (commune_id, client_id, campaign_id, zone, category_id)
     * @return Collection<int, array>
     */
    public function build(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $rows = collect();

        // ── Panneaux internes ─────────────────────────────────────
        $q = Campaign::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date',   '>=', $from->toDateString())
            ->with([
                'client:id,name,sector',
                'panels' => function ($q) {
                    $q->select('panels.id', 'reference', 'name', 'commune_id', 'category_id', 'format_id')
                      ->with([
                          'commune:id,name,city',
                          'format:id,name,width,height,surface',
                          'category:id,name',
                      ]);
                },
            ]);

        if (!empty($filters['client_id'])) {
            $q->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['campaign_id'])) {
            $q->where('id', $filters['campaign_id']);
        }

        $campaigns = $q->orderBy('start_date')->get();

        foreach ($campaigns as $camp) {
            foreach ($camp->panels as $panel) {
                if (!$this->panelPassesFilters($panel, $filters)) {
                    continue;
                }

                $rows->push($this->buildRow($camp, $panel, $from, $to, false));
            }
        }

        // ── Panneaux externes (pige) ──────────────────────────────
        // On les remonte aussi si la campagne les utilise ; utile pour audit.
        $campExt = Campaign::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date',   '>=', $from->toDateString())
            ->with([
                'client:id,name,sector',
                'externalPanels' => function ($q) {
                    $q->with(['commune:id,name,city']);
                },
            ]);

        if (!empty($filters['client_id'])) {
            $campExt->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['campaign_id'])) {
            $campExt->where('id', $filters['campaign_id']);
        }

        foreach ($campExt->get() as $camp) {
            foreach ($camp->externalPanels as $ep) {
                if (!empty($filters['commune_id']) && (int) $ep->commune_id !== (int) $filters['commune_id']) {
                    continue;
                }
                if (!empty($filters['zone']) && !$this->matchZone($ep->commune?->city, $filters['zone'])) {
                    continue;
                }
                $rows->push($this->buildRowExternal($camp, $ep, $from, $to));
            }
        }

        return $rows->sortBy([
            ['commune', 'asc'],
            ['panel_ref', 'asc'],
            ['campaign_start', 'asc'],
        ])->values();
    }

    private function panelPassesFilters($panel, array $filters): bool
    {
        if (!empty($filters['commune_id']) && (int) $panel->commune_id !== (int) $filters['commune_id']) {
            return false;
        }
        if (!empty($filters['category_id']) && (int) $panel->category_id !== (int) $filters['category_id']) {
            return false;
        }
        if (!empty($filters['zone']) && !$this->matchZone($panel->commune?->city, $filters['zone'])) {
            return false;
        }
        return true;
    }

    private function matchZone(?string $city, string $zone): bool
    {
        $isAbidjan = in_array($city, Commune::ABIDJAN_COMMUNES ?? ['Abidjan'], true);
        return $zone === 'abidjan' ? $isAbidjan : !$isAbidjan;
    }

    private function buildRow(Campaign $camp, $panel, Carbon $from, Carbon $to, bool $isExternal): array
    {
        $format  = $panel->format;
        $surface = $format?->surface;
        $dims    = $format
            ? trim(($format->width ?? '') . 'x' . ($format->height ?? '')) . ($surface ? ' (' . number_format((float) $surface, 2, ',', ' ') . ' m²)' : '')
            : '—';

        // Décapage : pivot campaign_panels.decapped_at
        $decapAt = $panel->pivot->decapped_at ?? null;

        return [
            'commune'        => $panel->commune?->name ?? '—',
            'city'           => $panel->commune?->city ?? '—',
            'panel_id'       => $panel->id,
            'panel_ref'      => $panel->reference,
            'panel_name'     => $panel->name,
            'panel_type'     => $panel->category?->name ?? '—',
            'panel_dims'     => $dims,
            'panel_surface'  => $surface,
            'is_external'    => $isExternal,
            'campaign_id'    => $camp->id,
            'campaign_name'  => $camp->name,
            'campaign_status' => $camp->status instanceof \App\Enums\CampaignStatus ? $camp->status->value : (string) $camp->status,
            'campaign_start' => $camp->start_date,
            'campaign_end'   => $camp->end_date,
            'client_id'      => $camp->client?->id,
            'client_name'    => $camp->client?->name ?? '—',
            'client_sector'  => $camp->client?->sector ?? '—',
            'duration_label' => $this->formatDuration($camp->start_date, $camp->end_date),
            'decapped_at'    => $decapAt,
        ];
    }

    private function buildRowExternal(Campaign $camp, $ep, Carbon $from, Carbon $to): array
    {
        return [
            'commune'        => $ep->commune?->name ?? '—',
            'city'           => $ep->commune?->city ?? '—',
            'panel_id'       => $ep->id,
            'panel_ref'      => 'EXT-' . ($ep->id ?? '?'),
            'panel_name'     => $ep->name ?? ($ep->description ?? 'Panneau externe'),
            'panel_type'     => 'Externe (pige)',
            'panel_dims'     => '—',
            'panel_surface'  => null,
            'is_external'    => true,
            'campaign_id'    => $camp->id,
            'campaign_name'  => $camp->name,
            'campaign_status' => $camp->status instanceof \App\Enums\CampaignStatus ? $camp->status->value : (string) $camp->status,
            'campaign_start' => $camp->start_date,
            'campaign_end'   => $camp->end_date,
            'client_id'      => $camp->client?->id,
            'client_name'    => $camp->client?->name ?? '—',
            'client_sector'  => $camp->client?->sector ?? '—',
            'duration_label' => $this->formatDuration($camp->start_date, $camp->end_date),
            'decapped_at'    => null,
        ];
    }

    /**
     * Format humain « X mois Y jours » à partir des bornes campagne.
     * NOTE : durée BRUTE de la campagne, pas la durée de facturation (celle-ci
     * dépend de la formule métier centralisée Campaign::computeBillableMonths()).
     */
    private function formatDuration(?Carbon $start, ?Carbon $end): string
    {
        if (!$start || !$end) return '—';
        $s = $start->copy()->startOfDay();
        $e = $end->copy()->startOfDay();
        if ($e->lt($s)) return '—';

        $diff = $s->diff($e->copy()->addDay());
        $months = ($diff->y * 12) + $diff->m;
        $days   = $diff->d;

        $parts = [];
        if ($months > 0) $parts[] = $months . ' mois';
        if ($days > 0)   $parts[] = $days . ' j';
        if (empty($parts)) $parts[] = '1 j';

        return implode(' ', $parts);
    }

    /**
     * Statistiques d'entête pour affichage rapide sur l'onglet.
     */
    public function summary(Collection $rows): array
    {
        return [
            'total_rows'    => $rows->count(),
            'nb_panels'     => $rows->pluck('panel_id')->unique()->count(),
            'nb_campaigns'  => $rows->pluck('campaign_id')->unique()->count(),
            'nb_clients'    => $rows->pluck('client_id')->filter()->unique()->count(),
            'nb_communes'   => $rows->pluck('commune')->filter()->unique()->count(),
            'nb_externals'  => $rows->where('is_external', true)->count(),
        ];
    }
}
