<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DashboardKpiService — calcul centralisé des KPIs analytics OOH.
 *
 * Architecture :
 *   - 1 instance par requête, configurée via setPeriod() + setFilters()
 *   - Toutes les méthodes sont CACHÉES (5 min) avec une clé qui dépend
 *     de la période + filtres. Permet d'appeler la même méthode plusieurs
 *     fois sans recalculer.
 *   - Les méthodes retournent des Collection ou des arrays structurés
 *     prêts à être consommés par les vues / exports.
 *
 * Modules couverts :
 *   1. Parc — total / occupé / dispo / par commune
 *   2. Performance panneaux — top loués / sous-performants
 *   3. Clients — top CA / inactifs (3/6/12 mois)
 *   4. Campagnes — statuts / motifs d'annulation
 *   5. Décappages — campagnes terminées à décaper
 *   6. Taxes — délégation à TaxReportService (déjà optimisé)
 *   7. Financier — CA global / mensuel / par commune
 *   8. Insights — détection auto d'anomalies + recommandations
 *
 * Performance : sur un parc 1k panneaux + 10k campagnes, l'ensemble
 * des méthodes calcule en < 500ms (cache à froid). Avec cache chaud,
 * < 50ms. La majorité du temps est consommée par 1-2 queries lourdes
 * (topPanels, revenueByCommune) qui utilisent des CTEs ou GROUP BY
 * agrégés en SQL natif.
 */
class DashboardKpiService
{
    protected Carbon $from;
    protected Carbon $to;
    protected array $filters = [];

    /** TTL cache par défaut (5 min). */
    public const CACHE_TTL = 300;

    public function __construct(?Carbon $from = null, ?Carbon $to = null)
    {
        $this->from = $from ?? now()->startOfYear();
        $this->to   = $to   ?? now()->endOfDay();
    }

    /**
     * Résolution rapide via preset : 'today', 'week', 'month',
     * 'quarter', 'year', 'all'. Pratique pour les filtres UI.
     */
    public function setPreset(string $preset): self
    {
        [$from, $to] = match ($preset) {
            'today'   => [now()->startOfDay(),     now()->endOfDay()],
            'week'    => [now()->startOfWeek(),    now()->endOfWeek()],
            'month'   => [now()->startOfMonth(),   now()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year'    => [now()->startOfYear(),    now()->endOfYear()],
            'all'     => [Carbon::create(2020, 1, 1), now()->endOfDay()],
            default   => [$this->from, $this->to],
        };
        return $this->setPeriod($from, $to);
    }

    public function setPeriod(Carbon $from, Carbon $to): self
    {
        $this->from = $from;
        $this->to   = $to;
        return $this;
    }

    public function setFilters(array $filters): self
    {
        // Garde seulement les filtres connus pour stabiliser la clé cache
        $allowed = ['commune_id', 'city', 'client_id', 'category_id'];
        $this->filters = array_filter(array_intersect_key($filters, array_flip($allowed)));
        return $this;
    }

    public function getPeriod(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Helper — applique les filtres dimensionnels à une query qui
     * référence (ou peut référencer) la table `panels` ET/OU `campaigns`.
     *
     * @param  \Illuminate\Database\Query\Builder $q
     * @param  array  $options
     *   - panel_alias    : alias de panels dans la query (défaut 'panels')
     *   - campaign_alias : alias de campaigns (défaut 'campaigns')
     *   - join_communes  : true si on doit joindre communes pour filtrer par city
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applyFilters($q, array $options = [])
    {
        if (empty($this->filters)) return $q;

        $panelAlias    = $options['panel_alias']    ?? 'panels';
        $campaignAlias = $options['campaign_alias'] ?? 'campaigns';

        if (!empty($this->filters['commune_id']) && in_array('panel', $options['targets'] ?? ['panel'], true)) {
            $q->where("{$panelAlias}.commune_id", $this->filters['commune_id']);
        }
        if (!empty($this->filters['category_id']) && in_array('panel', $options['targets'] ?? ['panel'], true)) {
            $q->where("{$panelAlias}.category_id", $this->filters['category_id']);
        }
        if (!empty($this->filters['city']) && in_array('panel', $options['targets'] ?? ['panel'], true)) {
            // Sous-requête pour ne pas obliger un join communes
            $q->whereIn("{$panelAlias}.commune_id", function ($sub) {
                $sub->select('id')->from('communes')->where('city', $this->filters['city']);
            });
        }
        if (!empty($this->filters['client_id']) && in_array('campaign', $options['targets'] ?? ['campaign'], true)) {
            $q->where("{$campaignAlias}.client_id", $this->filters['client_id']);
        }
        return $q;
    }

    /** Helper Eloquent — applique les filtres à un Builder Eloquent sur Panel */
    protected function applyPanelEloquentFilters($query)
    {
        if (!empty($this->filters['commune_id'])) {
            $query->where('commune_id', $this->filters['commune_id']);
        }
        if (!empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }
        if (!empty($this->filters['city'])) {
            $query->whereHas('commune', fn($c) => $c->where('city', $this->filters['city']));
        }
        return $query;
    }

    /** Helper Eloquent — applique les filtres à un Builder Eloquent sur Campaign */
    protected function applyCampaignEloquentFilters($query)
    {
        if (!empty($this->filters['client_id'])) {
            $query->where('client_id', $this->filters['client_id']);
        }
        if (!empty($this->filters['commune_id']) || !empty($this->filters['city']) || !empty($this->filters['category_id'])) {
            $query->whereHas('panels', function ($p) {
                $this->applyPanelEloquentFilters($p);
            });
        }
        return $query;
    }

    /**
     * Clé cache unique par méthode + période + filtres + version (bumpée
     * via APP_VERSION ou la migration la plus récente pour invalider
     * automatiquement les caches après déploiement).
     */
    protected function cacheKey(string $suffix): string
    {
        return 'dashboard_kpi.' . $suffix . '.' . md5(
            $this->from->toDateString() .
            '|' . $this->to->toDateString() .
            '|' . json_encode($this->filters) .
            '|' . config('app.version', '1')
        );
    }

    protected function cached(string $suffix, callable $callback, int $ttl = self::CACHE_TTL)
    {
        return Cache::remember($this->cacheKey($suffix), $ttl, $callback);
    }

    /**
     * Invalide tout le cache du dashboard (utile après import / sync).
     * Cache::tags non utilisé car file driver ne le supporte pas — on
     * compte sur le TTL de 5 min pour la cohérence éventuelle.
     */
    public static function invalidateAll(): void
    {
        Cache::flush(); // À éviter en prod si autre cache critique. TODO: passer en cache tags si Redis.
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 1 — VUE GLOBALE DU PARC
    // ══════════════════════════════════════════════════════════════

    /**
     * Stats globales du parc : total / occupé / disponible / maintenance
     * + taux d'occupation actuel (snapshot, pas sur la période).
     */
    public function parcOverview(): array
    {
        return $this->cached('parc_overview', function () {
            $base = fn() => $this->applyPanelEloquentFilters(Panel::whereNull('deleted_at'));

            $total       = $base()->count();
            $occupied    = $base()->whereIn('status', ['occupe', 'confirme'])->count();
            $available   = $base()->where('status', 'libre')->count();
            $option      = $base()->where('status', 'option')->count();
            $maintenance = $base()->where('status', 'maintenance')->count();

            return [
                'total'           => $total,
                'occupied'        => $occupied,
                'available'       => $available,
                'option'          => $option,
                'maintenance'     => $maintenance,
                'occupation_rate' => $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Répartition du parc par commune avec taux d'occupation.
     * Retourne une Collection triée par nombre de panneaux desc.
     */
    public function parcByCommune(): Collection
    {
        return $this->cached('parc_commune', function () {
            $q = Commune::query();
            // Filtre par commune ou ville depuis les filtres dashboard
            if (!empty($this->filters['commune_id'])) {
                $q->where('id', $this->filters['commune_id']);
            }
            if (!empty($this->filters['city'])) {
                $q->where('city', $this->filters['city']);
            }
            $categoryId = $this->filters['category_id'] ?? null;

            return $q->withCount([
                    'panels' => function ($q) use ($categoryId) {
                        $q->whereNull('deleted_at');
                        if ($categoryId) $q->where('category_id', $categoryId);
                    },
                    'panels as occupied_count' => function ($q) use ($categoryId) {
                        $q->whereNull('deleted_at')->whereIn('status', ['occupe', 'confirme']);
                        if ($categoryId) $q->where('category_id', $categoryId);
                    },
                ])
                ->having('panels_count', '>', 0)
                ->orderByDesc('panels_count')
                ->get()
                ->map(fn($c) => [
                    'id'       => $c->id,
                    'commune'  => $c->name,
                    'city'     => $c->city,
                    'total'    => $c->panels_count,
                    'occupied' => $c->occupied_count,
                    'free'     => $c->panels_count - $c->occupied_count,
                    'rate'     => $c->panels_count > 0
                        ? round(($c->occupied_count / $c->panels_count) * 100, 1)
                        : 0,
                ]);
        });
    }

    /**
     * Évolution mensuelle du taux d'occupation sur les 12 derniers mois.
     * Pour chaque mois, calcule : panneaux engagés / panneaux totaux du parc.
     */
    public function occupationTrend(int $months = 12): Collection
    {
        return $this->cached("occupation_trend.{$months}", function () use ($months) {
            $totalParc = $this->applyPanelEloquentFilters(Panel::whereNull('deleted_at'))->count();
            $result = collect();

            for ($i = $months - 1; $i >= 0; $i--) {
                $month = now()->subMonths($i)->startOfMonth();
                $endMonth = $month->copy()->endOfMonth();

                $q = DB::table('campaign_panels')
                    ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                    ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                    ->where('campaign_panels.type', 'interne')
                    ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                    ->where('campaigns.start_date', '<=', $endMonth)
                    ->where('campaigns.end_date',   '>=', $month)
                    ->whereNull('campaigns.deleted_at')
                    ->whereNull('panels.deleted_at');

                $this->applyFilters($q, ['targets' => ['panel', 'campaign']]);
                $occupied = $q->distinct('campaign_panels.panel_id')->count('campaign_panels.panel_id');

                $result->push([
                    'label' => $month->translatedFormat('M Y'),
                    'rate'  => $totalParc > 0 ? round(($occupied / $totalParc) * 100, 1) : 0,
                ]);
            }
            return $result;
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 2 — PERFORMANCE PANNEAUX
    // ══════════════════════════════════════════════════════════════

    /**
     * Top panneaux les plus loués (= plus de jours occupés sur la période).
     * Inclut le nombre de campagnes distinctes et le total des jours occupés.
     */
    public function topPanels(int $limit = 20): Collection
    {
        return $this->cached("top_panels.{$limit}", function () use ($limit) {
            $from = $this->from->toDateString();
            $to   = $this->to->toDateString();

            $q = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
                ->where('campaign_panels.type', 'interne')
                ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('campaigns.start_date', '<=', $to)
                ->where('campaigns.end_date',   '>=', $from)
                ->whereNull('campaigns.deleted_at')
                ->whereNull('panels.deleted_at');

            $this->applyFilters($q, ['targets' => ['panel', 'campaign']]);

            return $q->select(
                    'panels.id',
                    'panels.reference',
                    'panels.name',
                    'communes.name as commune_name',
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                    DB::raw('SUM(DATEDIFF(LEAST(campaigns.end_date, "' . $to . '"), GREATEST(campaigns.start_date, "' . $from . '")) + 1) as days_occupied'),
                    DB::raw('SUM(campaigns.total_amount / GREATEST(DATEDIFF(campaigns.end_date, campaigns.start_date) + 1, 1) * (DATEDIFF(LEAST(campaigns.end_date, "' . $to . '"), GREATEST(campaigns.start_date, "' . $from . '")) + 1)) as estimated_revenue'),
                )
                ->groupBy('panels.id', 'panels.reference', 'panels.name', 'communes.name')
                ->orderByDesc('days_occupied')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Panneaux sous-performants : 0 ou très peu de campagnes sur la période.
     * Trié par nombre de campagnes croissant puis par référence.
     */
    public function lowPanels(int $limit = 20): Collection
    {
        return $this->cached("low_panels.{$limit}", function () use ($limit) {
            $from = $this->from->toDateString();
            $to   = $this->to->toDateString();

            $q = DB::table('panels')
                ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
                ->leftJoin('campaign_panels', function ($j) {
                    $j->on('campaign_panels.panel_id', '=', 'panels.id')
                      ->where('campaign_panels.type', '=', 'interne');
                })
                ->leftJoin('campaigns', function ($j) use ($from, $to) {
                    $j->on('campaigns.id', '=', 'campaign_panels.campaign_id')
                      ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                      ->where('campaigns.start_date', '<=', $to)
                      ->where('campaigns.end_date',   '>=', $from)
                      ->whereNull('campaigns.deleted_at');
                })
                ->whereNull('panels.deleted_at')
                ->where('panels.status', '!=', 'maintenance');

            $this->applyFilters($q, ['targets' => ['panel', 'campaign']]);

            return $q->select(
                    'panels.id',
                    'panels.reference',
                    'panels.name',
                    'panels.monthly_rate',
                    'communes.name as commune_name',
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                )
                ->groupBy('panels.id', 'panels.reference', 'panels.name', 'panels.monthly_rate', 'communes.name')
                ->orderBy('campaigns_count')
                ->orderBy('panels.reference')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Détail complet d'un panneau (drill-down) — historique des occupations.
     * Retourne :
     *   - info de base (référence, nom, commune, statut, tarif)
     *   - synthèse (jours occupés / période, taux, CA généré, nb campagnes, top clients)
     *   - timeline campagnes (toutes celles passées sur le panneau)
     *   - série mensuelle 12 mois (jours occupés par mois)
     *   - longue plage d'inactivité (plus long gap entre 2 campagnes)
     */
    public function panelDetail(int $panelId): ?array
    {
        return $this->cached("panel_detail.{$panelId}", function () use ($panelId) {
            $panel = Panel::with('commune:id,name,city', 'category:id,name')
                ->whereNull('deleted_at')
                ->find($panelId);
            if (!$panel) return null;

            $from = $this->from->copy();
            $to   = $this->to->copy();

            // Campagnes ayant utilisé ce panneau
            $campaigns = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
                ->where('campaign_panels.panel_id', $panelId)
                ->where('campaign_panels.type', 'interne')
                ->whereNull('campaigns.deleted_at')
                ->select(
                    'campaigns.id',
                    'campaigns.name',
                    'campaigns.status',
                    'campaigns.start_date',
                    'campaigns.end_date',
                    'campaigns.total_amount',
                    'clients.id as client_id',
                    'clients.name as client_name',
                    'campaign_panels.decapped_at',
                )
                ->orderByDesc('campaigns.start_date')
                ->get();

            // Top clients pour ce panneau
            $topClients = $campaigns
                ->filter(fn($c) => $c->status !== 'annule' && $c->client_id)
                ->groupBy('client_id')
                ->map(fn($rows) => [
                    'client_id' => $rows->first()->client_id,
                    'name'      => $rows->first()->client_name,
                    'count'     => $rows->count(),
                    'revenue'   => (float) $rows->sum('total_amount'),
                ])
                ->sortByDesc('revenue')
                ->take(5)
                ->values();

            // Série mensuelle (12 mois) — jours occupés
            $monthly = collect();
            for ($i = 11; $i >= 0; $i--) {
                $mStart = now()->subMonths($i)->startOfMonth();
                $mEnd   = $mStart->copy()->endOfMonth();
                $days = 0;
                foreach ($campaigns as $c) {
                    if (in_array($c->status, ['annule'], true)) continue;
                    $cs = \Carbon\Carbon::parse($c->start_date);
                    $ce = \Carbon\Carbon::parse($c->end_date);
                    $start = $cs->lt($mStart) ? $mStart : $cs;
                    $end   = $ce->gt($mEnd)   ? $mEnd   : $ce;
                    if ($start->lte($end)) $days += (int) $start->diffInDays($end) + 1;
                }
                $totalDays = (int) $mStart->diffInDays($mEnd) + 1;
                $days = min($days, $totalDays);
                $monthly->push([
                    'label' => $mStart->translatedFormat('M Y'),
                    'days_occupied' => $days,
                    'total_days'    => $totalDays,
                    'rate'          => $totalDays > 0 ? round(($days / $totalDays) * 100, 1) : 0,
                ]);
            }

            // Sur la période active : jours occupés / total
            $periodDays = (int) $from->diffInDays($to) + 1;
            $busyDays   = 0;
            $periodRevenue = 0;
            foreach ($campaigns as $c) {
                if (in_array($c->status, ['annule'], true)) continue;
                $cs = \Carbon\Carbon::parse($c->start_date);
                $ce = \Carbon\Carbon::parse($c->end_date);
                $start = $cs->lt($from) ? $from->copy() : $cs;
                $end   = $ce->gt($to)   ? $to->copy()   : $ce;
                if ($start->lte($end)) {
                    $d = (int) $start->diffInDays($end) + 1;
                    $busyDays += $d;
                    // CA prorata (jours dans période / durée totale campagne)
                    $totalCamp = max(1, (int) $cs->diffInDays($ce) + 1);
                    $periodRevenue += ($c->total_amount * $d) / $totalCamp;
                }
            }
            $busyDays = min($busyDays, $periodDays);
            $rate = $periodDays > 0 ? round(($busyDays / $periodDays) * 100, 1) : 0;

            // Plus longue plage d'inactivité (gap entre 2 campagnes)
            $longestGap = 0;
            $sorted = $campaigns->filter(fn($c) => $c->status !== 'annule')
                ->sortBy('start_date')->values();
            $lastEnd = null;
            $gapStart = null; $gapEnd = null;
            foreach ($sorted as $c) {
                $cs = \Carbon\Carbon::parse($c->start_date);
                if ($lastEnd && $cs->gt($lastEnd)) {
                    $gap = (int) $lastEnd->diffInDays($cs);
                    if ($gap > $longestGap) {
                        $longestGap = $gap;
                        $gapStart   = $lastEnd->copy();
                        $gapEnd     = $cs->copy();
                    }
                }
                $ce = \Carbon\Carbon::parse($c->end_date);
                if (!$lastEnd || $ce->gt($lastEnd)) $lastEnd = $ce;
            }
            // Inactivité depuis la dernière campagne jusqu'à aujourd'hui
            $sinceLast = $lastEnd ? (int) $lastEnd->diffInDays(now(), false) : null;

            return [
                'panel' => [
                    'id'        => $panel->id,
                    'reference' => $panel->reference,
                    'name'      => $panel->name,
                    'commune'   => $panel->commune?->name,
                    'city'      => $panel->commune?->city,
                    'category'  => $panel->category?->name,
                    'status'    => $panel->status,
                    'rate'      => (float) ($panel->monthly_rate ?? 0),
                    'url'       => route('admin.panels.show', $panel->id),
                ],
                'summary' => [
                    'campaigns_total'    => $campaigns->count(),
                    'campaigns_active'   => $campaigns->whereIn('status', ['actif','planifie'])->count(),
                    'campaigns_done'     => $campaigns->where('status', 'termine')->count(),
                    'campaigns_cancelled'=> $campaigns->where('status', 'annule')->count(),
                    'period_days'        => $periodDays,
                    'busy_days'          => $busyDays,
                    'rate'               => $rate,
                    'revenue_period'     => round($periodRevenue),
                    'revenue_total'      => (float) $campaigns->where('status', '!=', 'annule')->sum('total_amount'),
                    'days_since_last'    => $sinceLast !== null ? max(0, $sinceLast) : null,
                    'longest_gap_days'   => $longestGap,
                    'longest_gap_start'  => $gapStart?->format('d/m/Y'),
                    'longest_gap_end'    => $gapEnd?->format('d/m/Y'),
                ],
                'monthly'     => $monthly,
                'top_clients' => $topClients,
                'campaigns'   => $campaigns->map(fn($c) => [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'client'       => $c->client_name ?? '—',
                    'status'       => $c->status,
                    'start_date'   => \Carbon\Carbon::parse($c->start_date)->format('d/m/Y'),
                    'end_date'     => \Carbon\Carbon::parse($c->end_date)->format('d/m/Y'),
                    'amount'       => (float) $c->total_amount,
                    'decapped_at'  => $c->decapped_at,
                    'url'          => route('admin.campaigns.show', $c->id),
                ])->values(),
            ];
        });
    }

    /**
     * Panneaux avec une longue période d'inactivité (>60 jours sans
     * campagne). Aide à identifier les "périodes creuses" sur le parc.
     */
    public function inactivePanels(int $thresholdDays = 60, int $limit = 30): Collection
    {
        return $this->cached("inactive_panels.{$thresholdDays}.{$limit}", function () use ($thresholdDays, $limit) {
            $q = DB::table('panels')
                ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
                ->leftJoinSub(
                    DB::table('campaign_panels')
                        ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                        ->where('campaign_panels.type', 'interne')
                        ->whereNotIn('campaigns.status', ['annule'])
                        ->whereNull('campaigns.deleted_at')
                        ->select('campaign_panels.panel_id', DB::raw('MAX(campaigns.end_date) as last_end'))
                        ->groupBy('campaign_panels.panel_id'),
                    'last_use', 'last_use.panel_id', '=', 'panels.id'
                )
                ->whereNull('panels.deleted_at')
                ->where('panels.status', '!=', 'maintenance')
                ->where(function ($q) use ($thresholdDays) {
                    $q->whereNull('last_use.last_end')
                      ->orWhere('last_use.last_end', '<', now()->subDays($thresholdDays));
                });

            $this->applyFilters($q, ['targets' => ['panel']]);

            return $q->select(
                    'panels.id',
                    'panels.reference',
                    'panels.name',
                    'panels.monthly_rate',
                    'panels.status',
                    'communes.name as commune_name',
                    'last_use.last_end',
                    DB::raw('DATEDIFF(NOW(), last_use.last_end) as days_inactive'),
                )
                ->orderByRaw('CASE WHEN last_use.last_end IS NULL THEN 99999 ELSE DATEDIFF(NOW(), last_use.last_end) END DESC')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Alertes automatiques spécifiques aux panneaux : sous-tarification
     * détectée, longue inactivité, ratio CA/tarif suspect.
     */
    public function panelAlerts(): Collection
    {
        return $this->cached('panel_alerts', function () {
            $alerts = collect();

            // 1. Panneaux jamais loués alors qu'ils ne sont pas en maintenance
            $never = $this->lowPanels(200)->where('campaigns_count', 0);
            if ($never->count() > 0) {
                $alerts->push([
                    'severity' => 'danger',
                    'icon'     => '🔴',
                    'title'    => $never->count() . ' panneau(x) jamais loué(s) sur la période',
                    'detail'   => 'Action recommandée : promotion ciblée, baisse tarifaire ou ré-évaluation visuelle.',
                    'count'    => $never->count(),
                ]);
            }

            // 2. Panneaux avec longue inactivité (>60 jours)
            $longInactive = $this->inactivePanels(60, 200);
            if ($longInactive->count() > 0) {
                $alerts->push([
                    'severity' => 'warning',
                    'icon'     => '🟠',
                    'title'    => $longInactive->count() . ' panneau(x) sans campagne depuis > 60 jours',
                    'detail'   => 'Vérifiez l\'attractivité de ces emplacements ou révisez la grille tarifaire.',
                    'count'    => $longInactive->count(),
                ]);
            }

            // 3. Détection sous-tarification : top 10 panneaux les plus loués
            // ayant un tarif anormalement bas (< 70% de la médiane des autres panneaux)
            $top = $this->topPanels(20);
            if ($top->isNotEmpty()) {
                $allRates = Panel::whereNull('deleted_at')
                    ->where('monthly_rate', '>', 0)
                    ->pluck('monthly_rate')->sort()->values();
                $median = $allRates->isNotEmpty()
                    ? ($allRates[(int) floor($allRates->count() / 2)] ?? 0)
                    : 0;
                $underpriced = collect();
                foreach ($top as $p) {
                    $rate = Panel::find($p->id)?->monthly_rate ?? 0;
                    if ($rate > 0 && $median > 0 && $rate < $median * 0.7 && $p->days_occupied > 30) {
                        $underpriced->push($p->reference);
                    }
                }
                if ($underpriced->isNotEmpty()) {
                    $alerts->push([
                        'severity' => 'info',
                        'icon'     => '💡',
                        'title'    => $underpriced->count() . ' panneau(x) potentiellement sous-tarifés',
                        'detail'   => 'Tarif < 70% médiane mais > 30 jours loués : opportunité de revalorisation. Ex : ' . $underpriced->take(5)->join(', '),
                        'count'    => $underpriced->count(),
                    ]);
                }
            }

            return $alerts;
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 3 — ANALYSE CLIENTS
    // ══════════════════════════════════════════════════════════════

    /**
     * Top clients par chiffre d'affaires sur la période (réservations
     * confirmées + campagnes actives/terminées).
     */
    public function topClients(int $limit = 10): Collection
    {
        return $this->cached("top_clients.{$limit}", function () use ($limit) {
            $from = $this->from->toDateString();
            $to   = $this->to->toDateString();

            $q = DB::table('clients')
                ->leftJoin('campaigns', function ($j) use ($from, $to) {
                    $j->on('campaigns.client_id', '=', 'clients.id')
                      ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                      ->where('campaigns.start_date', '<=', $to)
                      ->where('campaigns.end_date',   '>=', $from)
                      ->whereNull('campaigns.deleted_at');
                })
                ->whereNull('clients.deleted_at');

            if (!empty($this->filters['client_id'])) {
                $q->where('clients.id', $this->filters['client_id']);
            }
            // Filtres par commune/ville/category s'appliquent aux panneaux loués
            if (!empty($this->filters['commune_id']) || !empty($this->filters['city']) || !empty($this->filters['category_id'])) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('campaign_panels')
                        ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                        ->whereColumn('campaign_panels.campaign_id', 'campaigns.id');
                    if (!empty($this->filters['commune_id'])) $sub->where('panels.commune_id', $this->filters['commune_id']);
                    if (!empty($this->filters['category_id'])) $sub->where('panels.category_id', $this->filters['category_id']);
                    if (!empty($this->filters['city'])) {
                        $sub->whereIn('panels.commune_id', fn($s) => $s->select('id')->from('communes')->where('city', $this->filters['city']));
                    }
                });
            }

            return $q->select(
                    'clients.id',
                    'clients.name',
                    'clients.email',
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                    DB::raw('COALESCE(SUM(campaigns.total_amount), 0) as total_revenue'),
                    DB::raw('MAX(campaigns.start_date) as last_campaign_at'),
                )
                ->groupBy('clients.id', 'clients.name', 'clients.email')
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Clients inactifs depuis N mois (aucune réservation récente).
     * Filtre les clients qui ont déjà eu au moins une résa (pour exclure
     * les prospects jamais convertis).
     */
    public function inactiveClients(int $months = 3, int $limit = 50): Collection
    {
        return $this->cached("inactive_clients.{$months}.{$limit}", function () use ($months, $limit) {
            $threshold = now()->subMonths($months);

            return DB::table('clients')
                ->whereNull('clients.deleted_at')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))->from('campaigns')
                      ->whereColumn('campaigns.client_id', 'clients.id')
                      ->whereNull('campaigns.deleted_at');
                })
                ->whereNotExists(function ($q) use ($threshold) {
                    $q->select(DB::raw(1))->from('campaigns')
                      ->whereColumn('campaigns.client_id', 'clients.id')
                      ->where('campaigns.created_at', '>=', $threshold)
                      ->whereNull('campaigns.deleted_at');
                })
                ->select(
                    'clients.id',
                    'clients.name',
                    'clients.email',
                    'clients.phone',
                    DB::raw('(SELECT MAX(start_date) FROM campaigns WHERE client_id = clients.id AND deleted_at IS NULL) as last_campaign_at'),
                )
                ->orderBy('last_campaign_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Top clients selon 3 critères : 'revenue' (CA), 'volume' (nb campagnes),
     * 'frequency' (campagnes / mois d'activité). Utilisé pour comparer les
     * clients sous différents angles dans l'onglet Clients.
     */
    public function topClientsByCriteria(string $criteria = 'revenue', int $limit = 10): Collection
    {
        return $this->cached("top_clients_by.{$criteria}.{$limit}", function () use ($criteria, $limit) {
            $from = $this->from->toDateString();
            $to   = $this->to->toDateString();

            $q = DB::table('clients')
                ->leftJoin('campaigns', function ($j) use ($from, $to) {
                    $j->on('campaigns.client_id', '=', 'clients.id')
                      ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                      ->where('campaigns.start_date', '<=', $to)
                      ->where('campaigns.end_date',   '>=', $from)
                      ->whereNull('campaigns.deleted_at');
                })
                ->whereNull('clients.deleted_at')
                ->groupBy('clients.id', 'clients.name', 'clients.email');

            if (!empty($this->filters['client_id'])) {
                $q->where('clients.id', $this->filters['client_id']);
            }

            // Calcul fréquence : nb campagnes / nb mois entre 1ʳᵉ et dernière
            $q->select(
                'clients.id',
                'clients.name',
                'clients.email',
                DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                DB::raw('COALESCE(SUM(campaigns.total_amount), 0) as total_revenue'),
                DB::raw('MIN(campaigns.start_date) as first_campaign_at'),
                DB::raw('MAX(campaigns.start_date) as last_campaign_at'),
                DB::raw('TIMESTAMPDIFF(MONTH, MIN(campaigns.start_date), MAX(campaigns.start_date)) + 1 as active_months'),
            );

            // Tri selon critère
            $q->havingRaw('COUNT(DISTINCT campaigns.id) > 0');
            switch ($criteria) {
                case 'volume':
                    $q->orderByDesc('campaigns_count');
                    break;
                case 'frequency':
                    $q->orderByRaw('(COUNT(DISTINCT campaigns.id) / GREATEST(TIMESTAMPDIFF(MONTH, MIN(campaigns.start_date), MAX(campaigns.start_date)) + 1, 1)) DESC');
                    break;
                default:
                    $q->orderByDesc('total_revenue');
            }

            $rows = $q->limit($limit)->get();
            // Calcule la fréquence en PHP (campagnes / mois actifs)
            return $rows->map(function ($r) {
                $months = max(1, (int) ($r->active_months ?? 1));
                $r->frequency = round($r->campaigns_count / $months, 2);
                return $r;
            });
        });
    }

    /**
     * Répartition des revenus par client (top N + autres) — pour doughnut.
     */
    public function clientRevenueDistribution(int $topN = 8): array
    {
        return $this->cached("client_revenue_dist.{$topN}", function () use ($topN) {
            $top = $this->topClientsByCriteria('revenue', $topN);
            $totalAll = (float) $this->totalRevenue();
            $topSum   = (float) $top->sum('total_revenue');
            $others   = max(0, $totalAll - $topSum);

            return [
                'top'    => $top->map(fn($r) => [
                    'id'      => $r->id,
                    'name'    => $r->name,
                    'revenue' => (float) $r->total_revenue,
                    'share'   => $totalAll > 0 ? round(($r->total_revenue / $totalAll) * 100, 1) : 0,
                ])->values(),
                'others' => $others,
                'others_share' => $totalAll > 0 ? round(($others / $totalAll) * 100, 1) : 0,
                'total'  => $totalAll,
            ];
        });
    }

    /**
     * Comptage des clients inactifs par tranche (3-6 / 6-12 / 12+ mois).
     */
    public function inactivityBuckets(): array
    {
        return $this->cached('inactivity_buckets', function () {
            $inactive3  = $this->inactiveClients(3, 99999)->count();
            $inactive6  = $this->inactiveClients(6, 99999)->count();
            $inactive12 = $this->inactiveClients(12, 99999)->count();
            return [
                '3_to_6'  => max(0, $inactive3 - $inactive6),
                '6_to_12' => max(0, $inactive6 - $inactive12),
                '12_plus' => $inactive12,
            ];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 4 — ANALYSE CAMPAGNES
    // ══════════════════════════════════════════════════════════════

    public function campaignStats(): array
    {
        return $this->cached('campaign_stats', function () {
            $base = $this->applyCampaignEloquentFilters(
                Campaign::query()
                    ->whereBetween('start_date', [$this->from, $this->to])
                    ->whereNull('deleted_at')
            );

            $total     = (clone $base)->count();
            $active    = (clone $base)->where('status', 'actif')->count();
            $done      = (clone $base)->where('status', 'termine')->count();
            $cancelled = (clone $base)->where('status', 'annule')->count();
            $planned   = (clone $base)->where('status', 'planifie')->count();
            $paused    = (clone $base)->where('status', 'pause')->count();

            return [
                'total'       => $total,
                'active'      => $active,
                'done'        => $done,
                'cancelled'   => $cancelled,
                'planned'     => $planned,
                'paused'      => $paused,
                'cancel_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Répartition des motifs d'annulation. Utile pour identifier les
     * causes récurrentes et améliorer le processus commercial.
     */
    public function cancelReasons(): Collection
    {
        return $this->cached('cancel_reasons', function () {
            $q = Campaign::whereNotNull('cancellation_reason')
                ->where('status', 'annule')
                ->whereBetween('updated_at', [$this->from, $this->to])
                ->whereNull('deleted_at');
            $this->applyCampaignEloquentFilters($q);

            return $q->select('cancellation_reason', DB::raw('COUNT(*) as count'))
                ->groupBy('cancellation_reason')
                ->orderByDesc('count')
                ->get();
        });
    }

    /**
     * Évolution mensuelle des annulations (12 derniers mois).
     * Pour chaque mois : nb total campagnes créées + nb annulées + taux %.
     * Permet de détecter une tendance à la hausse ou un pic ponctuel.
     */
    public function cancellationTrend(int $months = 12): Collection
    {
        return $this->cached("cancel_trend.{$months}", function () use ($months) {
            $start = now()->subMonths($months - 1)->startOfMonth();
            $rows = DB::table('campaigns')
                ->where('created_at', '>=', $start)
                ->whereNull('deleted_at')
                ->select(
                    DB::raw('YEAR(created_at) as y'),
                    DB::raw('MONTH(created_at) as m'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'annule' THEN 1 ELSE 0 END) as cancelled"),
                )
                ->groupBy('y', 'm')
                ->get()
                ->keyBy(fn($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

            $result = collect();
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $k = $date->format('Y-m');
                $row = $rows->get($k);
                $total     = (int) ($row->total ?? 0);
                $cancelled = (int) ($row->cancelled ?? 0);
                $result->push([
                    'label'      => $date->translatedFormat('M Y'),
                    'total'      => $total,
                    'cancelled'  => $cancelled,
                    'rate'       => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0,
                ]);
            }
            return $result;
        });
    }

    /**
     * Patterns d'annulation détectés automatiquement :
     *   - dominant_reason : motif le plus fréquent
     *   - trend_direction : up/down/stable sur 3 derniers mois vs 9 précédents
     *   - repeat_offenders : clients qui ont annulé > 1 campagne
     *   - peak_months : mois avec annulations > 2× la moyenne
     */
    public function cancellationPatterns(): array
    {
        return $this->cached('cancel_patterns', function () {
            $reasons = $this->cancelReasons();
            $totalCanc = $reasons->sum('count');

            $dominant = $reasons->first();
            $dominantPct = $totalCanc > 0 && $dominant
                ? round(($dominant->count / $totalCanc) * 100, 1)
                : 0;

            // Tendance : compare moyenne 3 derniers mois vs 9 précédents
            $trend = $this->cancellationTrend(12);
            $recent3   = $trend->slice(-3)->avg('cancelled');
            $previous9 = $trend->slice(0, 9)->avg('cancelled');
            $trendDir  = 'stable';
            $trendPct  = 0;
            if ($previous9 > 0) {
                $delta = (($recent3 - $previous9) / $previous9) * 100;
                $trendPct = round($delta, 1);
                if ($delta > 15) $trendDir = 'up';
                elseif ($delta < -15) $trendDir = 'down';
            } elseif ($recent3 > 0) {
                $trendDir = 'up';
            }

            // Clients récidivistes (>1 annulation)
            $repeatOffenders = DB::table('campaigns')
                ->join('clients', 'clients.id', '=', 'campaigns.client_id')
                ->where('campaigns.status', 'annule')
                ->whereBetween('campaigns.updated_at', [$this->from, $this->to])
                ->whereNull('campaigns.deleted_at')
                ->select(
                    'clients.id', 'clients.name',
                    DB::raw('COUNT(*) as cancellations'),
                    DB::raw("SUM(campaigns.total_amount) as lost_revenue"),
                )
                ->groupBy('clients.id', 'clients.name')
                ->having('cancellations', '>', 1)
                ->orderByDesc('cancellations')
                ->limit(10)
                ->get();

            // Mois de pic (>2× moyenne)
            $avgMonthly = $trend->avg('cancelled');
            $peakMonths = $trend->filter(fn($m) => $avgMonthly > 0 && $m['cancelled'] > $avgMonthly * 2)->values();

            // CA perdu total (sommé sur la période)
            $lostRevenue = (float) DB::table('campaigns')
                ->where('status', 'annule')
                ->whereBetween('updated_at', [$this->from, $this->to])
                ->whereNull('deleted_at')
                ->sum('total_amount');

            return [
                'dominant_reason'      => $dominant ? [
                    'code'  => $dominant->cancellation_reason,
                    'count' => $dominant->count,
                    'pct'   => $dominantPct,
                ] : null,
                'trend_direction'      => $trendDir,
                'trend_pct'            => $trendPct,
                'recent_avg'           => round($recent3 ?? 0, 1),
                'previous_avg'         => round($previous9 ?? 0, 1),
                'repeat_offenders'     => $repeatOffenders,
                'peak_months'          => $peakMonths,
                'lost_revenue'         => $lostRevenue,
                'total_cancellations'  => $totalCanc,
            ];
        });
    }

    /**
     * Recommandations actionnables générées à partir des patterns détectés.
     * Chaque reco a : titre, action concrète, motif détecté, sévérité.
     */
    public function cancellationRecommendations(): Collection
    {
        $patterns = $this->cancellationPatterns();
        $recos = collect();

        $reasonLabels = [
            'budget' => 'budget client',
            'zone' => 'choix de zone',
            'strategie' => 'changement stratégique',
            'report' => 'report client',
            'concurrent' => 'choix concurrent',
            'autre' => 'motif divers',
        ];

        // Reco basée sur motif dominant
        if ($patterns['dominant_reason'] && $patterns['dominant_reason']['pct'] >= 30) {
            $code = $patterns['dominant_reason']['code'];
            $pct  = $patterns['dominant_reason']['pct'];
            $action = match($code) {
                'budget'     => "Proposer un échelonnement de paiement ou des forfaits plus accessibles. Discuter des budgets en début de cycle commercial pour aligner les attentes.",
                'zone'       => "Étoffer le catalogue : présenter systématiquement 2-3 zones alternatives avec mockups visuels. Refaire un audit terrain des emplacements à forte demande.",
                'strategie'  => "Demander un brief stratégique plus poussé en amont (objectifs, cible, période). Adapter la proposition à l'évolution business du client.",
                'report'     => "Sécuriser des acomptes plus élevés en proposition. Adapter la fenêtre de validité de l'offre (J+30 max). Relances anticipées avant échéance.",
                'concurrent' => "Cartographier les offres concurrentes par zone. Ajuster la grille tarifaire ou ajouter des avantages (gratuits, exclusivités) pour les comptes à risque.",
                'autre'      => "Analyser au cas par cas les annulations 'autres' pour identifier de nouveaux motifs à formaliser dans le système.",
                default      => "Approfondir l'analyse de ce motif récurrent en interview client.",
            };
            $recos->push([
                'severity' => 'danger',
                'icon'     => '🎯',
                'title'    => "Motif dominant : " . ($reasonLabels[$code] ?? $code) . " ({$pct}%)",
                'pattern'  => "Plus du tiers des annulations sont liées à ce seul motif. C'est le levier le plus rentable à activer.",
                'action'   => $action,
            ]);
        }

        // Reco basée sur tendance
        if ($patterns['trend_direction'] === 'up' && abs($patterns['trend_pct']) > 15) {
            $recos->push([
                'severity' => 'danger',
                'icon'     => '📈',
                'title'    => "Annulations en hausse (+{$patterns['trend_pct']}%)",
                'pattern'  => "Sur les 3 derniers mois, la moyenne d'annulations a augmenté de {$patterns['trend_pct']}% vs les 9 mois précédents.",
                'action'   => "Réunion de revue commerciale urgente : passer en revue les pipelines à risque, identifier les comptes vulnérables, recadrer le pitch.",
            ]);
        } elseif ($patterns['trend_direction'] === 'down') {
            $recos->push([
                'severity' => 'success',
                'icon'     => '✅',
                'title'    => "Annulations en baisse ({$patterns['trend_pct']}%)",
                'pattern'  => "Les pratiques actuelles fonctionnent : la moyenne d'annulations baisse de " . abs($patterns['trend_pct']) . "% sur les 3 derniers mois.",
                'action'   => "Documenter ce qui a changé récemment (process, équipe, offre) pour le standardiser.",
            ]);
        }

        // Reco clients récidivistes
        if ($patterns['repeat_offenders']->count() > 0) {
            $top = $patterns['repeat_offenders']->first();
            $recos->push([
                'severity' => 'warning',
                'icon'     => '⚠️',
                'title'    => "{$patterns['repeat_offenders']->count()} client(s) récidiviste(s) — top : {$top->name} ({$top->cancellations} annul.)",
                'pattern'  => "Certains clients annulent récurremment. Ce comportement signale un problème de qualification en amont.",
                'action'   => "Ajouter un score de risque par client. Mettre en pause les propositions sans pré-acompte pour les comptes >2 annulations dans l'année.",
            ]);
        }

        // Reco CA perdu
        if ($patterns['lost_revenue'] > 0) {
            $recos->push([
                'severity' => 'info',
                'icon'     => '💸',
                'title'    => "CA perdu sur la période : " . number_format($patterns['lost_revenue'], 0, ',', ' ') . " FCFA",
                'pattern'  => "Montant cumulé des campagnes annulées sur la fenêtre d'analyse.",
                'action'   => "Inclure ce montant dans le reporting mensuel commercial. Chaque % de taux d'annulation réduit = " . number_format($patterns['lost_revenue'] * 0.01, 0, ',', ' ') . " FCFA récupérables.",
            ]);
        }

        // Pic mensuel
        if ($patterns['peak_months']->count() > 0) {
            $month = $patterns['peak_months']->first();
            $recos->push([
                'severity' => 'warning',
                'icon'     => '🔥',
                'title'    => "Pic d'annulations en {$month['label']} ({$month['cancelled']} annul.)",
                'pattern'  => "Plus de 2× la moyenne mensuelle — anomalie à investiguer.",
                'action'   => "Réunion post-mortem sur ce mois précis : événement externe, changement interne, motif récurrent.",
            ]);
        }

        // Si rien à signaler
        if ($recos->isEmpty()) {
            $recos->push([
                'severity' => 'success',
                'icon'     => '✅',
                'title'    => "Aucun pattern d'annulation préoccupant détecté",
                'pattern'  => "Les annulations sont rares et dispersées sur la période.",
                'action'   => "Continuer le suivi mensuel pour détecter rapidement toute déviation.",
            ]);
        }

        return $recos;
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 5 — DÉCAPPAGES (campagnes terminées à décaper)
    // ══════════════════════════════════════════════════════════════

    /**
     * Liste les campagnes dont la date de fin est passée mais qui ont
     * encore des panneaux à décaper. Trié par urgence (date de fin asc).
     *
     * Depuis COMMIT C, on track le statut décappage panneau par panneau
     * via la colonne `decapped_at` sur le pivot `campaign_panels`.
     * Pour chaque campagne, on calcule :
     *   - decapped_count    : panneaux marqués comme décappés
     *   - pending_count     : panneaux encore en attente
     *   - is_overdue        : > 7 jours après end_date sans décappage complet
     */
    public function decapList(int $limit = 50): Collection
    {
        return $this->cached("decap.v2.{$limit}", function () use ($limit) {
            $q = Campaign::with([
                    'client:id,name',
                    'panels:id,reference,name,commune_id',
                    'panels.commune:id,name',
                ])
                ->where('status', 'termine')
                ->where('end_date', '<=', now())
                ->where('end_date', '>=', now()->subDays(60))
                ->whereNull('deleted_at')
                ->orderBy('end_date');

            $this->applyCampaignEloquentFilters($q);

            $campaigns = $q->limit($limit)->get();

            // Chargement du statut décappage en une seule query
            $campaignIds = $campaigns->pluck('id');
            $decapStatus = DB::table('campaign_panels')
                ->whereIn('campaign_id', $campaignIds)
                ->select('campaign_id', 'panel_id', 'decapped_at', 'decapped_by_user_id', 'decap_notes')
                ->get()
                ->groupBy('campaign_id');

            return $campaigns->map(function ($campaign) use ($decapStatus) {
                $statuses = $decapStatus->get($campaign->id, collect());
                $byPanel  = $statuses->keyBy('panel_id');
                $decapped = $statuses->filter(fn($s) => $s->decapped_at !== null)->count();
                $total    = $campaign->panels->count();

                // Enrichit chaque panneau avec son statut de décappage
                $campaign->panels->each(function ($p) use ($byPanel) {
                    $row = $byPanel->get($p->id);
                    $p->decapped_at = $row?->decapped_at;
                    $p->decap_notes = $row?->decap_notes;
                });

                $campaign->decapped_count = $decapped;
                $campaign->pending_count  = $total - $decapped;
                $campaign->total_panels   = $total;
                $campaign->is_overdue     = $campaign->pending_count > 0
                    && $campaign->end_date->diffInDays(now(), false) > 7;
                $campaign->decap_progress = $total > 0 ? round(($decapped / $total) * 100) : 0;

                return $campaign;
            });
        });
    }

    /**
     * Marque un panneau comme décappé pour une campagne donnée.
     * Met à jour decapped_at, decapped_by_user_id, decap_notes sur le
     * pivot campaign_panels. Invalide les caches associés.
     *
     * @return bool true si succès, false si le pivot n'existe pas
     */
    public function markDecapped(int $campaignId, int $panelId, int $userId, ?string $notes = null): bool
    {
        $affected = DB::table('campaign_panels')
            ->where('campaign_id', $campaignId)
            ->where('panel_id', $panelId)
            ->update([
                'decapped_at'         => now(),
                'decapped_by_user_id' => $userId,
                'decap_notes'         => $notes,
                'updated_at'          => now(),
            ]);

        if ($affected > 0) {
            // ⚠ Purger TOUS les caches dérivés du pivot decapped_at — sinon
            // la bannière du haut (PANNEAUX CONCERNÉS / DÉCAPPÉS / EN ATTENTE
            // / EN RETARD) et la pastille onglet "Décappages" restent
            // bloquées sur les vieux compteurs après reload.
            // Avant : seuls decap.v2.* étaient purgés → bug "le décompte
            // ne diminue pas" rapporté.
            Cache::forget($this->cacheKey('decap.v2.50'));
            Cache::forget($this->cacheKey('decap.v2.99999'));
            Cache::forget($this->cacheKey('decap_stats'));
        }

        return $affected > 0;
    }

    /**
     * Marque TOUS les panneaux d'une campagne comme décappés en une seule
     * opération (bulk action). Retourne le nombre de pivots affectés.
     */
    public function markAllDecapped(int $campaignId, int $userId, ?string $notes = null): int
    {
        $affected = DB::table('campaign_panels')
            ->where('campaign_id', $campaignId)
            ->whereNull('decapped_at')
            ->update([
                'decapped_at'         => now(),
                'decapped_by_user_id' => $userId,
                'decap_notes'         => $notes,
                'updated_at'          => now(),
            ]);

        if ($affected > 0) {
            Cache::forget($this->cacheKey('decap.v2.50'));
            Cache::forget($this->cacheKey('decap.v2.99999'));
            Cache::forget($this->cacheKey('decap_stats'));
        }
        return $affected;
    }

    /**
     * Annule le décappage d'un panneau (en cas d'erreur de saisie).
     */
    public function unmarkDecapped(int $campaignId, int $panelId): bool
    {
        $affected = DB::table('campaign_panels')
            ->where('campaign_id', $campaignId)
            ->where('panel_id', $panelId)
            ->update([
                'decapped_at'         => null,
                'decapped_by_user_id' => null,
                'decap_notes'         => null,
                'updated_at'          => now(),
            ]);

        if ($affected > 0) {
            // Idem markDecapped : purge complète sinon les KPI restent
            // sur les vieux compteurs après l'action.
            Cache::forget($this->cacheKey('decap.v2.50'));
            Cache::forget($this->cacheKey('decap.v2.99999'));
            Cache::forget($this->cacheKey('decap_stats'));
        }

        return $affected > 0;
    }

    /**
     * Synthèse globale du décappage : combien faits / en attente / en retard.
     */
    public function decapStats(): array
    {
        return $this->cached('decap_stats', function () {
            $base = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->where('campaigns.status', 'termine')
                ->whereNull('campaigns.deleted_at')
                ->whereNull('panels.deleted_at')
                ->where('campaigns.end_date', '>=', now()->subDays(90))
                ->where('campaigns.end_date', '<=', now());

            $this->applyFilters($base, ['targets' => ['panel', 'campaign']]);

            $total     = (clone $base)->count();
            $decapped  = (clone $base)->whereNotNull('campaign_panels.decapped_at')->count();
            $pending   = $total - $decapped;
            $overdue   = (clone $base)
                ->whereNull('campaign_panels.decapped_at')
                ->where('campaigns.end_date', '<', now()->subDays(7))
                ->count();

            return [
                'total'    => $total,
                'decapped' => $decapped,
                'pending'  => $pending,
                'overdue'  => $overdue,
                'rate'     => $total > 0 ? round(($decapped / $total) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Campagnes qui se terminent bientôt (J+14) — opportunité de planifier
     * le décappage à l'avance.
     */
    public function upcomingEndings(int $daysAhead = 14): Collection
    {
        return $this->cached("upcoming_endings.{$daysAhead}", function () use ($daysAhead) {
            return Campaign::with(['client:id,name'])
                ->where('status', 'actif')
                ->whereBetween('end_date', [now(), now()->addDays($daysAhead)])
                ->whereNull('deleted_at')
                ->orderBy('end_date')
                ->get();
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 6 — TAXES (délégation à TaxReportService)
    // ══════════════════════════════════════════════════════════════

    /**
     * Cumul taxes ODP + TM par commune sur la période. Utilise la table
     * `taxes` (paiements effectifs).
     */
    public function taxesByCommune(): Collection
    {
        return $this->cached('taxes_commune', function () {
            return DB::table('taxes')
                ->join('communes', 'communes.id', '=', 'taxes.commune_id')
                ->whereBetween('taxes.paid_at', [$this->from, $this->to])
                ->select(
                    'communes.id',
                    'communes.name as commune',
                    DB::raw("SUM(CASE WHEN taxes.type = 'tm'  THEN taxes.amount ELSE 0 END) as tm_total"),
                    DB::raw("SUM(CASE WHEN taxes.type = 'odp' THEN taxes.amount ELSE 0 END) as odp_total"),
                    DB::raw("SUM(taxes.amount) as total"),
                )
                ->groupBy('communes.id', 'communes.name')
                ->orderByDesc('total')
                ->get();
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 7 — ANALYSE FINANCIÈRE
    // ══════════════════════════════════════════════════════════════

    /**
     * CA global sur la période. Inclut tous les types de réservations
     * confirmées + terminées.
     */
    public function totalRevenue(): float
    {
        return (float) $this->cached('total_revenue', function () {
            $q = DB::table('campaigns')
                ->whereIn('status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('start_date', '<=', $this->to)
                ->where('end_date',   '>=', $this->from)
                ->whereNull('deleted_at');

            if (!empty($this->filters['client_id'])) {
                $q->where('client_id', $this->filters['client_id']);
            }
            if (!empty($this->filters['commune_id']) || !empty($this->filters['city']) || !empty($this->filters['category_id'])) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('campaign_panels')
                        ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                        ->whereColumn('campaign_panels.campaign_id', 'campaigns.id');
                    if (!empty($this->filters['commune_id']))  $sub->where('panels.commune_id', $this->filters['commune_id']);
                    if (!empty($this->filters['category_id'])) $sub->where('panels.category_id', $this->filters['category_id']);
                    if (!empty($this->filters['city'])) {
                        $sub->whereIn('panels.commune_id', fn($s) => $s->select('id')->from('communes')->where('city', $this->filters['city']));
                    }
                });
            }
            return $q->sum('total_amount');
        });
    }

    /**
     * Évolution mensuelle du CA sur la période (ou 12 derniers mois).
     */
    public function revenueByMonth(int $months = 12): Collection
    {
        return $this->cached("revenue_month.{$months}", function () use ($months) {
            $start = now()->subMonths($months - 1)->startOfMonth();
            $q = DB::table('campaigns')
                ->whereIn('status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('start_date', '>=', $start)
                ->whereNull('deleted_at');

            if (!empty($this->filters['client_id'])) {
                $q->where('client_id', $this->filters['client_id']);
            }
            if (!empty($this->filters['commune_id']) || !empty($this->filters['city']) || !empty($this->filters['category_id'])) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('campaign_panels')
                        ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                        ->whereColumn('campaign_panels.campaign_id', 'campaigns.id');
                    if (!empty($this->filters['commune_id']))  $sub->where('panels.commune_id', $this->filters['commune_id']);
                    if (!empty($this->filters['category_id'])) $sub->where('panels.category_id', $this->filters['category_id']);
                    if (!empty($this->filters['city'])) {
                        $sub->whereIn('panels.commune_id', fn($s) => $s->select('id')->from('communes')->where('city', $this->filters['city']));
                    }
                });
            }

            $rows = $q->select(
                    DB::raw('YEAR(start_date) as y'),
                    DB::raw('MONTH(start_date) as m'),
                    DB::raw('SUM(total_amount) as total'),
                )
                ->groupBy('y', 'm')
                ->orderBy('y')->orderBy('m')
                ->get()
                ->keyBy(fn($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

            // Remplit les mois manquants à 0 pour un graphique propre
            $result = collect();
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $k = $date->format('Y-m');
                $result->push([
                    'label' => $date->translatedFormat('M Y'),
                    'total' => (float) ($rows->get($k)?->total ?? 0),
                ]);
            }
            return $result;
        });
    }

    /**
     * CA par commune (revenus générés par les panneaux d'une commune).
     */
    public function revenueByCommune(int $limit = 20): Collection
    {
        return $this->cached("revenue_commune.{$limit}", function () use ($limit) {
            $from = $this->from->toDateString();
            $to   = $this->to->toDateString();

            $q = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->join('communes', 'communes.id', '=', 'panels.commune_id')
                ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('campaign_panels.type', 'interne')
                ->where('campaigns.start_date', '<=', $to)
                ->where('campaigns.end_date',   '>=', $from)
                ->whereNull('campaigns.deleted_at')
                ->whereNull('panels.deleted_at');

            if (!empty($this->filters['commune_id']))  $q->where('panels.commune_id', $this->filters['commune_id']);
            if (!empty($this->filters['category_id'])) $q->where('panels.category_id', $this->filters['category_id']);
            if (!empty($this->filters['city']))        $q->where('communes.city', $this->filters['city']);
            if (!empty($this->filters['client_id']))   $q->where('campaigns.client_id', $this->filters['client_id']);

            return $q->select(
                    'communes.id',
                    'communes.name as commune',
                    // CA proraté par panneau : montant_camp / nb_panneaux_camp
                    DB::raw('SUM(campaigns.total_amount / GREATEST((SELECT COUNT(*) FROM campaign_panels cp2 WHERE cp2.campaign_id = campaigns.id), 1)) as revenue'),
                    DB::raw('COUNT(DISTINCT campaign_panels.panel_id) as panels_engaged'),
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                )
                ->groupBy('communes.id', 'communes.name')
                ->orderByDesc('revenue')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * CA agrégé par ville (un niveau au-dessus de la commune). Permet
     * d'identifier rapidement les marchés moteurs (Abidjan, Bouaké, …).
     */
    public function revenueByCity(int $limit = 20): Collection
    {
        return $this->cached("revenue_city.{$limit}", function () use ($limit) {
            $from = $this->from->toDateString();
            $to   = $this->to->toDateString();

            $q = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->join('communes', 'communes.id', '=', 'panels.commune_id')
                ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('campaign_panels.type', 'interne')
                ->where('campaigns.start_date', '<=', $to)
                ->where('campaigns.end_date',   '>=', $from)
                ->whereNotNull('communes.city')
                ->whereNull('campaigns.deleted_at')
                ->whereNull('panels.deleted_at');

            if (!empty($this->filters['commune_id']))  $q->where('panels.commune_id', $this->filters['commune_id']);
            if (!empty($this->filters['category_id'])) $q->where('panels.category_id', $this->filters['category_id']);
            if (!empty($this->filters['city']))        $q->where('communes.city', $this->filters['city']);
            if (!empty($this->filters['client_id']))   $q->where('campaigns.client_id', $this->filters['client_id']);

            return $q->select(
                    'communes.city as city',
                    DB::raw('SUM(campaigns.total_amount / GREATEST((SELECT COUNT(*) FROM campaign_panels cp2 WHERE cp2.campaign_id = campaigns.id), 1)) as revenue'),
                    DB::raw('COUNT(DISTINCT campaign_panels.panel_id) as panels_engaged'),
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                    DB::raw('COUNT(DISTINCT communes.id) as communes_count'),
                )
                ->groupBy('communes.city')
                ->orderByDesc('revenue')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Corrélation occupation × revenus par commune. Pour chaque commune
     * actif sur la période, retourne (rate, revenue, total, panels_engaged).
     * Le scatter chart en sortie aide à détecter :
     *   - Quadrant "rouge" : occupation haute, CA faible → tarif sous-évalué
     *   - Quadrant "vert"  : occupation haute, CA fort → zone à scaler
     *   - Quadrant "gris"  : occupation faible, CA faible → à dynamiser
     */
    public function occupationVsRevenue(): Collection
    {
        return $this->cached('occ_vs_revenue', function () {
            $parc    = $this->parcByCommune()->keyBy('id');
            $revenue = $this->revenueByCommune(99999)->keyBy('id');

            return $parc->map(fn($p) => [
                'id'             => $p['id'],
                'commune'        => $p['commune'],
                'city'           => $p['city'],
                'rate'           => (float) $p['rate'],
                'total'          => (int) $p['total'],
                'occupied'       => (int) $p['occupied'],
                'revenue'        => (float) ($revenue->get($p['id'])->revenue ?? 0),
                'panels_engaged' => (int)   ($revenue->get($p['id'])->panels_engaged ?? 0),
                'campaigns'      => (int)   ($revenue->get($p['id'])->campaigns_count ?? 0),
            ])->filter(fn($r) => $r['total'] > 0)->values();
        });
    }

    /**
     * Détail complet d'un client (drill-down).
     * Retourne : info de base, historique des campagnes, top panneaux loués,
     * communes les plus utilisées, CA par mois sur 12 mois.
     */
    public function clientDetail(int $clientId): ?array
    {
        return $this->cached("client_detail.{$clientId}", function () use ($clientId) {
            $client = Client::find($clientId);
            if (!$client) return null;

            // Historique campagnes (table campaigns — système legacy actif)
            $campaigns = DB::table('campaigns')
                ->leftJoin('campaign_panels', 'campaign_panels.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.client_id', $clientId)
                ->whereNull('campaigns.deleted_at')
                ->select(
                    'campaigns.id',
                    'campaigns.name',
                    'campaigns.status',
                    'campaigns.start_date',
                    'campaigns.end_date',
                    'campaigns.total_amount',
                    'campaigns.cancellation_reason',
                    DB::raw('COUNT(DISTINCT campaign_panels.panel_id) as panels_count'),
                )
                ->groupBy(
                    'campaigns.id', 'campaigns.name', 'campaigns.status',
                    'campaigns.start_date', 'campaigns.end_date',
                    'campaigns.total_amount', 'campaigns.cancellation_reason',
                )
                ->orderByDesc('campaigns.start_date')
                ->limit(100)
                ->get();

            // Top panneaux les plus loués par ce client
            $topPanels = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
                ->where('campaigns.client_id', $clientId)
                ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                ->whereNull('campaigns.deleted_at')
                ->whereNull('panels.deleted_at')
                ->select(
                    'panels.id', 'panels.reference', 'panels.name',
                    'communes.name as commune',
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                    DB::raw('SUM(campaigns.total_amount / GREATEST(DATEDIFF(campaigns.end_date, campaigns.start_date) + 1, 1) * (DATEDIFF(campaigns.end_date, campaigns.start_date) + 1)) as revenue'),
                )
                ->groupBy('panels.id', 'panels.reference', 'panels.name', 'communes.name')
                ->orderByDesc('campaigns_count')
                ->limit(10)
                ->get();

            // Communes les plus exploitées
            $topCommunes = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->join('communes', 'communes.id', '=', 'panels.commune_id')
                ->where('campaigns.client_id', $clientId)
                ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                ->whereNull('campaigns.deleted_at')
                ->select(
                    'communes.name as commune',
                    DB::raw('COUNT(DISTINCT panels.id) as panels_count'),
                    DB::raw('COUNT(DISTINCT campaigns.id) as campaigns_count'),
                    DB::raw('SUM(campaigns.total_amount / GREATEST(DATEDIFF(campaigns.end_date, campaigns.start_date) + 1, 1) * (DATEDIFF(campaigns.end_date, campaigns.start_date) + 1)) as revenue'),
                )
                ->groupBy('communes.name')
                ->orderByDesc('revenue')
                ->limit(8)
                ->get();

            // CA par mois sur 12 mois glissants (depuis campaigns)
            $revenueMonth = collect();
            $start = now()->subMonths(11)->startOfMonth();
            $rows = DB::table('campaigns')
                ->where('client_id', $clientId)
                ->whereIn('status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('start_date', '>=', $start)
                ->whereNull('deleted_at')
                ->select(
                    DB::raw('YEAR(start_date) as y'),
                    DB::raw('MONTH(start_date) as m'),
                    DB::raw('SUM(total_amount) as total'),
                )
                ->groupBy('y', 'm')
                ->get()
                ->keyBy(fn($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

            for ($i = 11; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $k = $date->format('Y-m');
                $revenueMonth->push([
                    'label' => $date->translatedFormat('M Y'),
                    'total' => (float) ($rows->get($k)?->total ?? 0),
                ]);
            }

            // Synthèse
            $totalCampaigns = $campaigns->count();
            $totalRevenue   = (float) $campaigns->where('status', '!=', 'annule')->sum('total_amount');
            $cancelled      = $campaigns->where('status', 'annule')->count();
            $lastCampaign   = $campaigns->first()?->start_date;
            $monthsInactive = $lastCampaign ? (int) Carbon::parse($lastCampaign)->diffInMonths(now()) : null;

            return [
                'client' => [
                    'id'    => $client->id,
                    'name'  => $client->name,
                    'ncc'   => $client->ncc,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'url'   => route('admin.clients.show', $client->id),
                ],
                'summary' => [
                    'total_campaigns'  => $totalCampaigns,
                    'total_revenue'    => $totalRevenue,
                    'cancelled'        => $cancelled,
                    'cancel_rate'      => $totalCampaigns > 0 ? round(($cancelled / $totalCampaigns) * 100, 1) : 0,
                    'avg_ticket'       => $totalCampaigns > 0 ? round($totalRevenue / max($totalCampaigns - $cancelled, 1)) : 0,
                    'last_campaign'    => $lastCampaign,
                    'months_inactive'  => $monthsInactive,
                ],
                'campaigns'     => $campaigns,
                'top_panels'    => $topPanels,
                'top_communes'  => $topCommunes,
                'revenue_month' => $revenueMonth,
            ];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 7b — BENCHMARKS SECTORIELS (données marché)
    // ══════════════════════════════════════════════════════════════

    /**
     * Charge les benchmarks sectoriels depuis config/market_benchmarks.php
     * et compare la performance Panora aux moyennes du marché OOH CI/Afrique.
     */
    public function marketBenchmarks(): array
    {
        return $this->cached('market_benchmarks', function () {
            $bench = config('market_benchmarks');
            $parc  = $this->parcOverview();
            $stats = $this->campaignStats();

            // Comparaison occupation Panora vs marché
            $ourOcc       = (float) $parc['occupation_rate'];
            $marketOccCI  = (float) ($bench['occupation']['ci_average'] ?? 55);
            $marketOccTop = (float) ($bench['occupation']['ci_top_performers'] ?? 75);
            $occDeltaCI   = $ourOcc - $marketOccCI;
            $occPosition  = $ourOcc >= $marketOccTop ? 'leader'
                          : ($ourOcc >= $marketOccCI ? 'above_average' : 'below_average');

            // Comparaison taux annulation
            $ourCancel    = (float) ($stats['cancel_rate'] ?? 0);
            $marketCancel = (float) ($bench['cancel_rate']['industry_average'] ?? 12);
            $cancelDelta  = $ourCancel - $marketCancel;
            $cancelPosition = $ourCancel <= ($bench['cancel_rate']['industry_healthy'] ?? 8) ? 'healthy'
                            : ($ourCancel <= ($bench['cancel_rate']['industry_warning'] ?? 18) ? 'average' : 'critical');

            return [
                'meta'  => [
                    'last_updated' => $bench['last_updated']  ?? null,
                    'notes'        => $bench['source_notes']  ?? null,
                ],
                'occupation'  => [
                    'our_value'      => round($ourOcc, 1),
                    'market_ci'      => $marketOccCI,
                    'market_top'     => $marketOccTop,
                    'market_africa'  => $bench['occupation']['africa_average'] ?? null,
                    'delta_vs_ci'    => round($occDeltaCI, 1),
                    'position'       => $occPosition,
                    'note'           => $bench['occupation']['note'] ?? '',
                ],
                'cancel_rate' => [
                    'our_value'        => round($ourCancel, 1),
                    'industry_healthy' => $bench['cancel_rate']['industry_healthy'] ?? 8,
                    'industry_average' => $marketCancel,
                    'industry_warning' => $bench['cancel_rate']['industry_warning'] ?? 18,
                    'delta_vs_market'  => round($cancelDelta, 1),
                    'position'         => $cancelPosition,
                ],
                'growth'      => $bench['growth']        ?? [],
                'pricing'     => $bench['pricing']       ?? [],
                'industry_mix'=> $bench['industry_mix']  ?? [],
                'competitors' => $bench['competitors']   ?? [],
                'trends'      => $bench['trends']        ?? [],
            ];
        });
    }

    /**
     * Synthèse exécutive (pour la direction) — agrège les KPIs et alertes
     * les plus stratégiques en 4 blocs : performance / risques / opportunités /
     * actions prioritaires.
     */
    public function executiveSummary(): array
    {
        return $this->cached('executive_summary', function () {
            $parc       = $this->parcOverview();
            $revenue    = $this->totalRevenue();
            $stats      = $this->campaignStats();
            $patterns   = $this->cancellationPatterns();
            $decapStats = $this->decapStats();
            $inactivityBucket = $this->inactivityBuckets();
            $bench      = $this->marketBenchmarks();
            $forecast   = (new KpiForecastService($this))->revenueForecast(3);

            // Performance globale (note /10)
            $score = 5;
            if ($bench['occupation']['position'] === 'leader')        $score += 2;
            elseif ($bench['occupation']['position'] === 'above_average') $score += 1;
            elseif ($bench['occupation']['position'] === 'below_average') $score -= 1;
            if ($bench['cancel_rate']['position'] === 'healthy')      $score += 1;
            elseif ($bench['cancel_rate']['position'] === 'critical') $score -= 2;
            if ($forecast['trend_direction'] === 'up')                $score += 1;
            elseif ($forecast['trend_direction'] === 'down')          $score -= 1;
            if ($decapStats['overdue'] === 0)                         $score += 1;
            elseif ($decapStats['overdue'] > 5)                       $score -= 1;
            $score = max(0, min(10, $score));

            // Risques majeurs
            $risks = collect();
            if ($decapStats['overdue'] > 0) {
                $risks->push("⚠️ {$decapStats['overdue']} décappage(s) en retard — risque amende municipale + plainte client");
            }
            if ($patterns['trend_direction'] === 'up' && abs($patterns['trend_pct']) > 15) {
                $risks->push("📉 Annulations en hausse de {$patterns['trend_pct']}% — revue commerciale urgente");
            }
            if ($inactivityBucket['12_plus'] > 5) {
                $risks->push("👥 {$inactivityBucket['12_plus']} clients inactifs > 12 mois — risque churn élevé");
            }
            if ($stats['cancel_rate'] > 18) {
                $risks->push("❌ Taux d'annulation à {$stats['cancel_rate']}% (seuil critique 18%)");
            }
            if ($risks->isEmpty()) {
                $risks->push("✅ Aucun risque majeur identifié sur la période");
            }

            // Opportunités stratégiques
            $opportunities = collect();
            if ($bench['occupation']['delta_vs_ci'] < 0) {
                $gap = abs($bench['occupation']['delta_vs_ci']);
                $opportunities->push("📈 Occupation {$gap} pts sous la moyenne marché — potentiel de gain via campagne de prospection");
            }
            if ($bench['occupation']['position'] === 'leader') {
                $opportunities->push("🏆 Position de leader sur l'occupation — opportunité de revaloriser les tarifs (+10-15 %)");
            }
            $opportunities->push("💼 " . ($bench['growth']['ci_yoy_2025_2026'] ?? 11) . "% de croissance projetée sur le secteur OOH CI — fenêtre de rattrapage si en retard");
            if (($inactivityBucket['6_to_12'] ?? 0) > 0) {
                $opportunities->push("🎯 " . ($inactivityBucket['6_to_12'] + $inactivityBucket['12_plus']) . " clients à reconquérir — templates mail/appel prêts dans l'onglet Insights");
            }

            // Actions prioritaires (top 3)
            $actions = collect();
            if ($decapStats['overdue'] > 0) {
                $actions->push(['priority' => 'high', 'action' => "Planifier décappages en retard (J+7)"]);
            }
            if ($patterns['dominant_reason'] && $patterns['dominant_reason']['pct'] >= 30) {
                $code = $patterns['dominant_reason']['code'];
                $actions->push(['priority' => 'high', 'action' => "Traiter motif annulation dominant : {$code} ({$patterns['dominant_reason']['pct']}%)"]);
            }
            if (($inactivityBucket['12_plus'] ?? 0) > 0) {
                $actions->push(['priority' => 'medium', 'action' => "Lancer campagne reconquête sur {$inactivityBucket['12_plus']} clients inactifs > 12 mois"]);
            }
            if ($actions->count() < 3) {
                $actions->push(['priority' => 'low', 'action' => "Continuer le suivi mensuel et documenter les bonnes pratiques actuelles"]);
            }

            return [
                'score'         => $score,
                'score_label'   => $score >= 8 ? 'Excellent' : ($score >= 6 ? 'Bon' : ($score >= 4 ? 'Moyen' : 'À redresser')),
                'score_color'   => $score >= 8 ? '#16a34a'   : ($score >= 6 ? '#3b82f6' : ($score >= 4 ? '#f59e0b' : '#dc2626')),
                'kpis' => [
                    'revenue'         => $revenue,
                    'occupation_rate' => $parc['occupation_rate'],
                    'cancel_rate'     => $stats['cancel_rate'],
                    'campaigns_total' => $stats['total'],
                ],
                'forecast_3m_revenue' => collect($forecast['forecast'] ?? [])->sum('value'),
                'forecast_confidence' => $forecast['confidence'] ?? 0,
                'risks'         => $risks->take(4)->values(),
                'opportunities' => $opportunities->take(4)->values(),
                'actions'       => $actions->take(3)->values(),
            ];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 8 — INSIGHTS & RECOMMANDATIONS
    // ══════════════════════════════════════════════════════════════

    /**
     * Génère des insights actionnables à partir des KPIs calculés.
     * Détecte automatiquement les signaux faibles + recommande des
     * actions concrètes.
     */
    public function insights(): Collection
    {
        $insights = collect();

        // ── Panneaux jamais loués ──────────────────────────────────
        $zeroPanels = $this->lowPanels(100)->where('campaigns_count', 0);
        if ($zeroPanels->count() > 0) {
            $insights->push([
                'severity' => 'warning',
                'icon'     => '🟠',
                'title'    => "{$zeroPanels->count()} panneau(x) jamais loués sur la période",
                'message'  => 'Considérez une promotion ciblée, une révision tarifaire ou un changement de visuel commercial.',
                'cta_label'=> 'Voir les panneaux libres',
                'cta_url'  => route('admin.panels.index', ['status' => 'libre']),
            ]);
        }

        // ── Clients inactifs critiques (>12 mois) ─────────────────
        $inactive12 = $this->inactiveClients(12, 99999);
        if ($inactive12->count() > 0) {
            $insights->push([
                'severity' => 'danger',
                'icon'     => '🔴',
                'title'    => "{$inactive12->count()} client(s) inactif(s) depuis plus de 12 mois",
                'message'  => 'Risque de churn élevé. Lancez une campagne de reconquête (mail + appel commercial dédié).',
                'cta_label'=> 'Voir clients inactifs',
                'cta_url'  => '#tab-clients',
            ]);
        }

        // ── Clients en zone de risque (6-12 mois) ─────────────────
        $bucket = $this->inactivityBuckets();
        if ($bucket['6_to_12'] > 0) {
            $insights->push([
                'severity' => 'warning',
                'icon'     => '🟡',
                'title'    => "{$bucket['6_to_12']} client(s) inactif(s) entre 6 et 12 mois",
                'message'  => 'Phase critique avant churn — relance commerciale prioritaire (offre fidélité).',
            ]);
        }

        // ── Taux d'annulation élevé ───────────────────────────────
        $stats = $this->campaignStats();
        if ($stats['cancel_rate'] > 15 && $stats['total'] >= 5) {
            $insights->push([
                'severity' => 'danger',
                'icon'     => '🔴',
                'title'    => "Taux d'annulation élevé : {$stats['cancel_rate']}%",
                'message'  => 'Au-dessus du seuil sain (15%). Analysez les motifs ci-dessous et ajustez le processus de proposition.',
                'cta_label'=> 'Voir motifs',
                'cta_url'  => '#tab-cancel-reasons',
            ]);
        }

        // ── Communes à faible occupation (>5 panneaux mais <10%) ──
        $weakCommunes = $this->parcByCommune()
            ->filter(fn($c) => $c['rate'] < 10 && $c['total'] >= 5);
        if ($weakCommunes->count() > 0) {
            $details = $weakCommunes->take(5)->pluck('commune')->join(', ');
            $insights->push([
                'severity' => 'info',
                'icon'     => 'ℹ️',
                'title'    => "{$weakCommunes->count()} commune(s) avec faible occupation (<10%)",
                'message'  => "Opportunité de prospection commerciale : {$details}.",
            ]);
        }

        // ── Campagnes en retard de décappage ──────────────────────
        $decap = $this->decapList(99999);
        $overdue = $decap->filter(fn($c) => $c->end_date->diffInDays(now(), false) > 7);
        if ($overdue->count() > 0) {
            $insights->push([
                'severity' => 'warning',
                'icon'     => '🟠',
                'title'    => "{$overdue->count()} campagne(s) terminée(s) depuis plus de 7 jours",
                'message'  => 'Vérifiez que les panneaux ont bien été décappés — risque d\'affichage périmé sur le terrain.',
                'cta_label'=> 'Voir décappages',
                'cta_url'  => '#tab-decap',
            ]);
        }

        // ── Campagne arrive bientôt à échéance ────────────────────
        $upcoming = $this->upcomingEndings(7);
        if ($upcoming->count() > 0) {
            $insights->push([
                'severity' => 'info',
                'icon'     => '📅',
                'title'    => "{$upcoming->count()} campagne(s) se termine(nt) dans les 7 jours",
                'message'  => 'Planifiez les décappages et anticipez les relances clients pour renouvellement.',
            ]);
        }

        // ── Aucun insight : signal "tout va bien" ─────────────────
        if ($insights->isEmpty()) {
            $insights->push([
                'severity' => 'success',
                'icon'     => '✅',
                'title'    => 'Aucune alerte critique sur cette période',
                'message'  => 'Les KPIs sont dans les seuils sains. Continuez sur cette dynamique !',
            ]);
        }

        return $insights;
    }
}
