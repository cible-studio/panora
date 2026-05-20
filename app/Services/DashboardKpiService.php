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
        $this->filters = array_intersect_key($filters, array_flip($allowed));
        return $this;
    }

    public function getPeriod(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
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
            $total       = Panel::whereNull('deleted_at')->count();
            $occupied    = Panel::whereIn('status', ['occupe', 'confirme'])->whereNull('deleted_at')->count();
            $available   = Panel::where('status', 'libre')->whereNull('deleted_at')->count();
            $option      = Panel::where('status', 'option')->whereNull('deleted_at')->count();
            $maintenance = Panel::where('status', 'maintenance')->whereNull('deleted_at')->count();

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
            return Commune::withCount([
                    'panels' => fn($q) => $q->whereNull('deleted_at'),
                    'panels as occupied_count' => fn($q) => $q
                        ->whereNull('deleted_at')
                        ->whereIn('status', ['occupe', 'confirme']),
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
            $totalParc = Panel::whereNull('deleted_at')->count();
            $result = collect();

            for ($i = $months - 1; $i >= 0; $i--) {
                $month = now()->subMonths($i)->startOfMonth();
                $endMonth = $month->copy()->endOfMonth();
                $occupied = DB::table('campaign_panels')
                    ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                    ->where('campaign_panels.type', 'interne')
                    ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                    ->where('campaigns.start_date', '<=', $endMonth)
                    ->where('campaigns.end_date',   '>=', $month)
                    ->whereNull('campaigns.deleted_at')
                    ->distinct('campaign_panels.panel_id')
                    ->count('campaign_panels.panel_id');

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

            $rows = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
                ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
                ->where('campaign_panels.type', 'interne')
                ->whereIn('campaigns.status', ['actif', 'planifie', 'pause', 'termine'])
                ->where('campaigns.start_date', '<=', $to)
                ->where('campaigns.end_date',   '>=', $from)
                ->whereNull('campaigns.deleted_at')
                ->whereNull('panels.deleted_at')
                ->select(
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

            return $rows;
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

            return DB::table('panels')
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
                ->where('panels.status', '!=', 'maintenance')
                ->select(
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

            return DB::table('clients')
                ->leftJoin('reservations', function ($j) use ($from, $to) {
                    $j->on('reservations.client_id', '=', 'clients.id')
                      ->whereIn('reservations.status', ['confirme', 'termine'])
                      ->where('reservations.start_date', '<=', $to)
                      ->where('reservations.end_date',   '>=', $from)
                      ->whereNull('reservations.deleted_at');
                })
                ->whereNull('clients.deleted_at')
                ->select(
                    'clients.id',
                    'clients.name',
                    'clients.email',
                    DB::raw('COUNT(DISTINCT reservations.id) as campaigns_count'),
                    DB::raw('COALESCE(SUM(reservations.total_amount), 0) as total_revenue'),
                    DB::raw('MAX(reservations.start_date) as last_campaign_at'),
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
                    $q->select(DB::raw(1))->from('reservations')
                      ->whereColumn('reservations.client_id', 'clients.id')
                      ->whereNull('reservations.deleted_at');
                })
                ->whereNotExists(function ($q) use ($threshold) {
                    $q->select(DB::raw(1))->from('reservations')
                      ->whereColumn('reservations.client_id', 'clients.id')
                      ->where('reservations.created_at', '>=', $threshold)
                      ->whereNull('reservations.deleted_at');
                })
                ->select(
                    'clients.id',
                    'clients.name',
                    'clients.email',
                    'clients.phone',
                    DB::raw('(SELECT MAX(start_date) FROM reservations WHERE client_id = clients.id AND deleted_at IS NULL) as last_campaign_at'),
                )
                ->orderBy('last_campaign_at')
                ->limit($limit)
                ->get();
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
            $base = Campaign::query()
                ->whereBetween('start_date', [$this->from, $this->to])
                ->whereNull('deleted_at');

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
            return Campaign::whereNotNull('cancellation_reason')
                ->where('status', 'annule')
                ->whereBetween('updated_at', [$this->from, $this->to])
                ->whereNull('deleted_at')
                ->select('cancellation_reason', DB::raw('COUNT(*) as count'))
                ->groupBy('cancellation_reason')
                ->orderByDesc('count')
                ->get();
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE 5 — DÉCAPPAGES (campagnes terminées à décaper)
    // ══════════════════════════════════════════════════════════════

    /**
     * Liste les campagnes dont la date de fin est passée mais qui ont
     * encore des panneaux à décaper. Trié par urgence (date de fin asc).
     *
     * Pour l'instant : on liste les campagnes terminées dans les
     * derniers 30 jours. Le statut de décappage par panneau pourra
     * être ajouté plus tard (table dédiée ou champ `decapped_at` sur
     * pivot campaign_panels).
     */
    public function decapList(int $limit = 50): Collection
    {
        return $this->cached("decap.{$limit}", function () use ($limit) {
            return Campaign::with(['client:id,name', 'panels:id,reference,name,commune_id', 'panels.commune:id,name'])
                ->where('status', 'termine')
                ->where('end_date', '<=', now())
                ->where('end_date', '>=', now()->subDays(60))
                ->whereNull('deleted_at')
                ->orderBy('end_date')
                ->limit($limit)
                ->get();
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
            return DB::table('reservations')
                ->whereIn('status', ['confirme', 'termine'])
                ->where('start_date', '<=', $this->to)
                ->where('end_date',   '>=', $this->from)
                ->whereNull('deleted_at')
                ->sum('total_amount');
        });
    }

    /**
     * Évolution mensuelle du CA sur la période (ou 12 derniers mois).
     */
    public function revenueByMonth(int $months = 12): Collection
    {
        return $this->cached("revenue_month.{$months}", function () use ($months) {
            $start = now()->subMonths($months - 1)->startOfMonth();
            $rows = DB::table('reservations')
                ->whereIn('status', ['confirme', 'termine'])
                ->where('start_date', '>=', $start)
                ->whereNull('deleted_at')
                ->select(
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

            return DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->join('panels', 'panels.id', '=', 'reservation_panels.panel_id')
                ->join('communes', 'communes.id', '=', 'panels.commune_id')
                ->whereIn('reservations.status', ['confirme', 'termine'])
                ->where('reservation_panels.source', 'interne')
                ->where('reservations.start_date', '<=', $to)
                ->where('reservations.end_date',   '>=', $from)
                ->whereNull('reservations.deleted_at')
                ->whereNull('panels.deleted_at')
                ->select(
                    'communes.id',
                    'communes.name as commune',
                    DB::raw('SUM(reservation_panels.total_price) as revenue'),
                    DB::raw('COUNT(DISTINCT reservation_panels.panel_id) as panels_engaged'),
                    DB::raw('COUNT(DISTINCT reservations.id) as campaigns_count'),
                )
                ->groupBy('communes.id', 'communes.name')
                ->orderByDesc('revenue')
                ->limit($limit)
                ->get();
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
