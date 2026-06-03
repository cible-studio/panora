<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Commune;
use App\Models\Panel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * TaxCalculationService — calcul des taxes communales TM / ODP / DB
 * selon les règles d'éligibilité spécifiées (Évolution 4).
 *
 * Règles d'éligibilité (synthèse spec) :
 *
 *   | Statut panneau                  | TM | ODP |
 *   | ──────────────────────────────  | ── | ─── |
 *   | Exploité avec campagne active   | ✓  |  ✓  |
 *   | Interne/maint. AVEC campagne    | ✓  |  ✓  |
 *   | Interne/maint. SANS campagne    | ✗  |  ✓  |
 *   | Vacant                          | ✗  |  ✓  |
 *
 * → ODP : tous les panneaux internes (catalogue), peu importe le
 *   statut ou l'occupation.
 * → TM : uniquement si une campagne ACTIVE existe sur la période,
 *   couvre le panneau (interne ou maintenance peu importe).
 *
 * Les tarifs sont résolus via Commune::ratesAt(date) — utilise
 * l'historique tarifaire pour garantir la cohérence rétroactive.
 */
class TaxCalculationService
{
    public const TYPE_TM  = 'tm';
    public const TYPE_ODP = 'odp';
    public const TYPES    = [self::TYPE_TM, self::TYPE_ODP];

    public const PERIOD_MONTHLY    = 'mensuel';
    public const PERIOD_QUARTERLY  = 'trimestriel';
    public const PERIOD_ANNUAL     = 'annuel';

    /**
     * Produit la liste détaillée des lignes de taxes pour une période
     * donnée — une ligne par (panneau × type taxe éligible). Source
     * unique de vérité pour la vue détaillée, les rapports et les
     * exports.
     *
     * @param  string $periodType    'mensuel' | 'trimestriel' | 'annuel'
     * @param  int    $periodValue   1-12 (mois) | 1-4 (trim) | 0 (annuel)
     * @param  int    $year
     * @param  array  $filters       ['commune_id'=>?, 'client_id'=>?, 'campaign_id'=>?, 'type'=>?]
     * @return Collection            Collection de lignes :
     *      {commune, commune_id, panel_id, reference, name, dimensions,
     *       surface, type, statut, client_name, client_id, campaign_name,
     *       campaign_id, period_start, period_end, months, rate, amount}
     */
    public function generateLines(string $periodType, int $periodValue, int $year, array $filters = []): Collection
    {
        [$periodStart, $periodEnd, $months] = $this->resolvePeriod($periodType, $periodValue, $year);

        // Pré-charge toutes les communes (pour leur historique tarifaire)
        // — peu nombreuses (~30) donc OK en mémoire.
        $communes = Commune::all()->keyBy('id');

        // Pré-charge les rates au début de la période pour chaque commune.
        // Choix design : on utilise les tarifs du début de période. Si on
        // change de tarif au milieu d'un mois, le calcul reste sur l'ancien
        // — cohérent avec la pratique fiscale (date d'émission de la base).
        $ratesByCommune = $communes->mapWithKeys(
            fn($c) => [$c->id => $c->ratesAt($periodStart)]
        );

        // Charge panneaux avec leur commune, format, dimensions.
        // On ne prend PAS les soft-deleted (parc actuel uniquement).
        $panelsQuery = Panel::with(['commune:id,name', 'format:id,name,width,height,surface'])
            ->whereNull('deleted_at');
        if (!empty($filters['commune_id'])) {
            $panelsQuery->where('commune_id', $filters['commune_id']);
        }
        $panels = $panelsQuery->get();

        // Pour la règle TM : on a besoin de savoir si chaque panneau a
        // une campagne active sur la période. Une seule requête pour tous.
        $campaignAssignmentMap = $this->buildCampaignAssignmentMap(
            $panels->pluck('id')->all(),
            $periodStart,
            $periodEnd,
            $filters['campaign_id'] ?? null,
            $filters['client_id']   ?? null
        );

        $filterType = $filters['type'] ?? null;

        $lines = collect();
        foreach ($panels as $panel) {
            $commune = $communes[$panel->commune_id] ?? null;
            if (!$commune) continue;
            $rates = $ratesByCommune[$commune->id];

            // Si filtre client/campagne actif → on ne garde le panneau que
            // s'il a une campagne assignée correspondante. Sinon (pas de
            // filtre client/campagne) on garde tous les panneaux pour
            // ODP et on filtre TM individuellement.
            $assignment = $campaignAssignmentMap[$panel->id] ?? null;
            $hasClientCampaignFilter = !empty($filters['client_id']) || !empty($filters['campaign_id']);
            if ($hasClientCampaignFilter && !$assignment) continue;

            $surface = (float) ($panel->format?->surface ?? 0);
            $dimensions = $panel->format?->name ?? '—';

            foreach (self::TYPES as $type) {
                if ($filterType && $filterType !== $type) continue;
                if (!$this->isEligible($panel, $type, $assignment)) continue;

                $unitRate = (float) ($rates[$type] ?? 0);
                if ($unitRate <= 0) continue; // pas tarif → ligne ignorée

                // Formule : tarif × surface × nombre de mois
                $amount = round($unitRate * $surface * $months, 2);

                $lines->push([
                    'commune'        => $commune->name,
                    'commune_id'     => $commune->id,
                    'panel_id'       => $panel->id,
                    'reference'      => $panel->reference,
                    'name'           => $panel->name,
                    'dimensions'     => $dimensions,
                    'surface'        => $surface,
                    'type'           => $type,
                    'statut'         => $panel->status?->value ?? 'libre',
                    'client_name'    => $assignment['client_name']    ?? null,
                    'client_id'      => $assignment['client_id']      ?? null,
                    'campaign_name'  => $assignment['campaign_name']  ?? null,
                    'campaign_id'    => $assignment['campaign_id']    ?? null,
                    'period_start'   => $periodStart,
                    'period_end'     => $periodEnd,
                    'months'         => $months,
                    'rate'           => $unitRate,
                    'amount'         => $amount,
                ]);
            }
        }

        return $lines;
    }

    /**
     * Règle d'éligibilité par type. Réutilise une assignment précomputée
     * (résultat de buildCampaignAssignmentMap) pour éviter les requêtes
     * N+1 sur le statut campagne.
     */
    public function isEligible(Panel $panel, string $type, ?array $assignment): bool
    {
        return match ($type) {
            self::TYPE_TM  => $assignment !== null,  // campagne active requise
            self::TYPE_ODP => true,                  // tous panneaux internes
            default        => false,
        };
    }

    /**
     * Map panel_id → ['campaign_id'=>, 'campaign_name'=>, 'client_id'=>,
     * 'client_name'=>] pour les campagnes actives ou en cours sur la
     * période. Une seule requête pour tous les panneaux passés en
     * paramètre.
     *
     * Une campagne "active sur la période" = statut ACTIF, dates qui se
     * chevauchent avec [periodStart, periodEnd] (même partiellement).
     *
     * Si un panneau a plusieurs campagnes sur la période (rare mais
     * possible avec extensions), on prend la plus récente.
     */
    private function buildCampaignAssignmentMap(array $panelIds, Carbon $periodStart, Carbon $periodEnd, ?int $campaignId = null, ?int $clientId = null): array
    {
        if (empty($panelIds)) return [];

        $q = \DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaign_panels.panel_id', $panelIds)
            ->where('campaigns.status', CampaignStatus::ACTIF->value)
            ->where('campaigns.start_date', '<=', $periodEnd->toDateString())
            ->where('campaigns.end_date',   '>=', $periodStart->toDateString())
            ->whereNull('campaigns.deleted_at')
            ->select(
                'campaign_panels.panel_id',
                'campaigns.id as campaign_id',
                'campaigns.name as campaign_name',
                'campaigns.start_date',
                'campaigns.end_date',
                'clients.id as client_id',
                'clients.name as client_name'
            );

        if ($campaignId) $q->where('campaigns.id', $campaignId);
        if ($clientId)   $q->where('campaigns.client_id', $clientId);

        $rows = $q->orderByDesc('campaigns.start_date')->get();

        $map = [];
        foreach ($rows as $r) {
            // Premier match = plus récent (orderByDesc), on garde celui-là.
            if (!isset($map[$r->panel_id])) {
                $map[$r->panel_id] = [
                    'campaign_id'   => (int) $r->campaign_id,
                    'campaign_name' => $r->campaign_name,
                    'client_id'     => $r->client_id ? (int) $r->client_id : null,
                    'client_name'   => $r->client_name,
                ];
            }
        }
        return $map;
    }

    /**
     * Bornes d'une période + nombre de mois pour la formule.
     * @return array{0:Carbon, 1:Carbon, 2:int}
     */
    public function resolvePeriod(string $periodType, int $periodValue, int $year): array
    {
        return match ($periodType) {
            self::PERIOD_MONTHLY => [
                Carbon::create($year, max(1, min(12, $periodValue)), 1)->startOfDay(),
                Carbon::create($year, max(1, min(12, $periodValue)), 1)->endOfMonth(),
                1,
            ],
            self::PERIOD_QUARTERLY => [
                Carbon::create($year, ($periodValue - 1) * 3 + 1, 1)->startOfDay(),
                Carbon::create($year, ($periodValue - 1) * 3 + 3, 1)->endOfMonth(),
                3,
            ],
            self::PERIOD_ANNUAL => [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
                12,
            ],
            default => throw new \InvalidArgumentException("Périodicité inconnue : $periodType"),
        };
    }

    /**
     * Agrégation des lignes pour les KPI / résumés. Évite d'avoir à
     * reparcourir la collection manuellement à chaque vue.
     */
    public function summarize(Collection $lines): array
    {
        return [
            'total'        => (float) $lines->sum('amount'),
            'by_type'      => $lines->groupBy('type')->map->sum('amount'),
            'by_commune'   => $lines->groupBy('commune')->map->sum('amount'),
            'panels_count' => $lines->pluck('panel_id')->unique()->count(),
            'lines_count'  => $lines->count(),
        ];
    }
}
