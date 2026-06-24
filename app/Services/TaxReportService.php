<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Commune;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taxes communales — rapport agrégé depuis les campagnes effectivement
 * actives (status actif/pose/termine) sur une année donnée.
 *
 * Convention de calcul (CIBLE CI / mairies CI) :
 *   tarif Commune.odp_rate / .tm_rate = montant ANNUEL par panneau.
 *   Pour un mois où un panneau est en occupation effective au moins 1 jour
 *   sur cette commune, on applique 1/12 du tarif annuel à ce panneau.
 *
 * Avantages :
 *   - Reflète l'occupation réelle (campagne 2 mois sur Cocody = 2/12 de la
 *     taxe annuelle, pas 12/12).
 *   - Granularité mois → permet d'agréger en trimestre / année / commune.
 *   - Pas de DB supplémentaire : tout est dérivé à la volée des Campaigns
 *     et de leurs panneaux. Les écritures Tax restent gérées par
 *     TaxAutoService (paiement effectif, échéances, statut).
 *
 * Pour des très gros volumes la requête peut être migrée vers un cube
 * matérialisé (table tax_calculations rafraîchie nuit) ; à ce stade
 * l'agrégation in-memory reste largement assez rapide (< 100 ms pour
 * un parc de 1000 panneaux et 50 campagnes/an).
 */
class TaxReportService
{
    public const TYPES = ['odp', 'tm'];

    /**
     * Renvoie un tableau aplati :
     *   [
     *     'commune_id' => int,
     *     'commune'    => string,
     *     'month'      => int (1..12),
     *     'panel_count'=> int   (panneaux distincts occupant ≥1j ce mois)
     *     'days'       => int   (somme des jours d'occupation tous panneaux)
     *     'odp'        => float (montant ODP du mois)
     *     'tm'         => float (montant TM du mois)
     *     'total'      => float
     *   ]
     *
     * Une ligne par couple (commune, mois) ayant au moins 1 panneau
     * occupé. Les communes sans tarif sont ignorées (cf. TaxAutoService).
     *
     * @return Collection<int, array>
     */
    public function monthlyMatrix(int $year, array $filters = []): Collection
    {
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end   = Carbon::create($year, 12, 31)->endOfYear();

        // Toutes les campagnes qui touchent l'année (1 jour suffit). On
        // exclut les annulées : pas de pose effective, donc pas de taxe.
        // Filtres optionnels client_id / campaign_id / commune_id pour les
        // rapports filtrés (spec Évolution 4 — 5.7 rapports combinables).
        $campaignsQuery = Campaign::query()
            ->whereIn('status', ['actif', 'termine', 'planifie'])
            ->where('start_date', '<=', $end)
            ->where('end_date',   '>=', $start)
            ->with([
                'panels:id,commune_id',
                'externalPanels:id,commune_id',
            ]);

        if (!empty($filters['client_id']))   $campaignsQuery->where('client_id', $filters['client_id']);
        if (!empty($filters['campaign_id'])) $campaignsQuery->where('id', $filters['campaign_id']);

        // Filtre commune : on ne garde que les campagnes qui touchent la
        // commune demandée — utilisé en aval pour les KPI et la matrice.
        if (!empty($filters['commune_id'])) {
            $cid = (int) $filters['commune_id'];
            $campaignsQuery->where(function ($q) use ($cid) {
                $q->whereHas('panels',        fn($p) => $p->where('commune_id', $cid))
                  ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $cid));
            });
        }

        $campaigns = $campaignsQuery->get(['id','client_id','start_date','end_date','status']);

        // Index commune_id → tarifs (évite N requêtes)
        $rates = Commune::pluck('odp_rate', 'id')->map(fn($r) => (float) $r);
        $tmRates = Commune::pluck('tm_rate', 'id')->map(fn($r) => (float) $r);
        $names = Commune::pluck('name', 'id');

        // Buckets : "communeId:month" → ['panels' => [panelKey, ...], 'days' => int]
        $buckets = [];

        foreach ($campaigns as $c) {
            // Période effective : intersection campagne ∩ année
            $effStart = $c->start_date->lt($start) ? $start->copy() : $c->start_date->copy();
            $effEnd   = $c->end_date->gt($end)     ? $end->copy()   : $c->end_date->copy();
            if ($effStart->gt($effEnd)) continue;

            $allPanels = $c->panels->concat($c->externalPanels)
                ->filter(fn($p) => !empty($p->commune_id));

            // Si filtre commune : on ignore les panneaux des autres communes
            // de la même campagne — sinon une campagne ADJAME + COCODY ferait
            // remonter COCODY dans le rapport filtré ADJAME.
            if (!empty($filters['commune_id'])) {
                $cid = (int) $filters['commune_id'];
                $allPanels = $allPanels->filter(fn($p) => (int) $p->commune_id === $cid);
            }

            // Pour chaque panneau on découpe l'occupation par mois
            foreach ($allPanels as $p) {
                $cur = $effStart->copy()->startOfMonth();
                while ($cur->lte($effEnd)) {
                    $monthStart = $cur->copy();
                    $monthEnd   = $cur->copy()->endOfMonth();
                    $segStart   = $effStart->gt($monthStart) ? $effStart : $monthStart;
                    $segEnd     = $effEnd->lt($monthEnd)     ? $effEnd   : $monthEnd;
                    $days       = (int) $segStart->diffInDays($segEnd) + 1;

                    if ($days > 0) {
                        $key = $p->commune_id . ':' . $cur->month;
                        if (!isset($buckets[$key])) {
                            $buckets[$key] = [
                                'commune_id' => (int) $p->commune_id,
                                'month'      => (int) $cur->month,
                                'panels'     => [],
                                'days'       => 0,
                            ];
                        }
                        // Clé panneau distincte interne/externe pour ne pas
                        // additionner le même panneau s'il est sur 2 campagnes
                        // qui se chevauchent partiellement.
                        $panelKey = ($p->getTable() === 'external_panels' ? 'e' : 'i') . ':' . $p->id;
                        $buckets[$key]['panels'][$panelKey] = true;
                        $buckets[$key]['days'] += $days;
                    }
                    $cur->addMonth();
                }
            }
        }

        // Convertit les buckets en lignes formatées + applique les tarifs
        return collect($buckets)
            ->values()
            ->map(function ($b) use ($rates, $tmRates, $names) {
                // FIX TX-3 (2026-06-22, validé patronne) : tarifs MENSUELS, pas annuels.
                // Avant : ($annual / 12) × panels → 12× trop bas.
                // Maintenant : tarif_mensuel × panels (le bucket représente déjà 1 mois).
                $panelCount = count($b['panels']);
                $odpRate    = (float) ($rates[$b['commune_id']] ?? 0);
                $tmRate     = (float) ($tmRates[$b['commune_id']] ?? 0);

                $odp = round($odpRate * $panelCount, 2);
                $tm  = round($tmRate  * $panelCount, 2);

                return [
                    'commune_id'  => $b['commune_id'],
                    'commune'     => $names[$b['commune_id']] ?? '—',
                    'month'       => $b['month'],
                    'panel_count' => $panelCount,
                    'days'        => $b['days'],
                    'odp'         => $odp,
                    'tm'          => $tm,
                    'total'       => $odp + $tm,
                ];
            })
            ->sortBy(['commune', 'month'])
            ->values();
    }

    /**
     * Synthèse par commune sur l'année — agrège les 12 mois.
     * Renvoie une ligne par commune avec totaux ODP / TM / Total et la
     * répartition par trimestre (q1..q4).
     */
    public function annualByCommune(int $year, array $filters = []): Collection
    {
        $monthly = $this->monthlyMatrix($year, $filters);

        return $monthly->groupBy('commune_id')->map(function ($rows, $communeId) {
            $first = $rows->first();
            $byQuarter = ['q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0];
            foreach ($rows as $r) {
                $q = match (true) {
                    $r['month'] <= 3  => 'q1',
                    $r['month'] <= 6  => 'q2',
                    $r['month'] <= 9  => 'q3',
                    default           => 'q4',
                };
                $byQuarter[$q] += (float) $r['total'];
            }
            return [
                'commune_id'  => $communeId,
                'commune'     => $first['commune'],
                'odp'         => (float) $rows->sum('odp'),
                'tm'          => (float) $rows->sum('tm'),
                'total'       => (float) $rows->sum('total'),
                'months'      => $rows->count(),
                'panel_max'   => (int) $rows->max('panel_count'),
                'days'        => (int) $rows->sum('days'),
                'q1'          => round($byQuarter['q1'], 2),
                'q2'          => round($byQuarter['q2'], 2),
                'q3'          => round($byQuarter['q3'], 2),
                'q4'          => round($byQuarter['q4'], 2),
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Totaux globaux annuels (toutes communes confondues).
     */
    public function totals(int $year, array $filters = []): array
    {
        $monthly = $this->monthlyMatrix($year, $filters);
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $sum = $monthly->where('month', $m)->sum('total');
            $byMonth[$m] = (float) $sum;
        }

        return [
            'year'       => $year,
            'odp'        => (float) $monthly->sum('odp'),
            'tm'         => (float) $monthly->sum('tm'),
            'total'      => (float) $monthly->sum('total'),
            'by_month'   => $byMonth,
            'q1'         => array_sum(array_slice($byMonth, 0, 3, true)),
            'q2'         => array_sum(array_slice($byMonth, 3, 3, true)),
            'q3'         => array_sum(array_slice($byMonth, 6, 3, true)),
            'q4'         => array_sum(array_slice($byMonth, 9, 3, true)),
            'communes'   => $monthly->groupBy('commune_id')->count(),
            'panel_max'  => (int) $monthly->max('panel_count'),
        ];
    }
}
