<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Commune;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taxes communales — rapport agrégé sur une année donnée.
 *
 * ⚠ 2026-11-16 : monthlyMatrix() lit désormais TaxCalculationService (cf.
 * son en-tête) ; la « convention de calcul » ci-dessous est l'ANCIENNE,
 * conservée pour l'historique.
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

    /** @var array<string, Collection> */
    private array $memoMensuel = [];

    /**
     * Renvoie un tableau aplati :
     *   [
     *     'commune_id' => int,
     *     'commune'    => string,
     *     'month'      => int (1..12),
     *     'panel_count'=> int   (panneaux taxés ce mois)
     *     'days'       => 0     (plus calculé — clé gardée pour les appelants)
     *     'odp'        => float (montant ODP du mois)
     *     'tm'         => float (montant TM du mois)
     *     'total'      => float
     *   ]
     *
     * Une ligne par couple (commune, mois) ayant au moins une ligne de taxe.
     *
     * ═══ 2026-11-16 — SOURCE UNIQUE ═══
     * Ce rapport avait sa propre formule : tarif × NOMBRE de panneaux occupés
     * (sans les m²), ODP comptée seulement les mois de campagne, campagnes
     * planifiées et panneaux de régies externes compris. Ses montants ne se
     * raccordaient ni à la page Taxes, ni au détail, ni au Suivi des mairies.
     * Il lit désormais le moteur unique, mois par mois :
     * TaxCalculationService::generateLines() — ODP = tarif × m² × mois où le
     * panneau est debout, TM = tarif × m² × mois de campagne (toutes les
     * campagnes, chaque mois dans le mois où il commence). Le parc Panora
     * hors pieds Lumipub, comme le Suivi des mairies (réglage unique :
     * TaxeExerciceService::inclureLumipub()). Les panneaux
     * de régies externes n'y sont plus : leurs taxes ne sont pas dues par la
     * régie.
     *
     * @return Collection<int, array>
     */
    public function monthlyMatrix(int $year, array $filters = []): Collection
    {
        // annualByCommune() et totals() rappellent monthlyMatrix() : 12 appels
        // au moteur à chaque fois sans cette mémoire.
        $cle = $year . '|' . md5(json_encode($filters));
        if (isset($this->memoMensuel[$cle])) {
            return $this->memoMensuel[$cle];
        }

        $calc = app(TaxCalculationService::class);
        $filtres = array_filter([
            'client_id'   => $filters['client_id']   ?? null,
            'campaign_id' => $filters['campaign_id'] ?? null,
            'commune_id'  => $filters['commune_id']  ?? null,
        ]) + ['inclure_lumipub' => \App\Services\Taxes\TaxeExerciceService::inclureLumipub()];

        $lignes = collect();
        for ($mois = 1; $mois <= 12; $mois++) {
            $parCommune = $calc->generateLines(TaxCalculationService::PERIOD_MONTHLY, $mois, $year, $filtres)
                ->groupBy('commune_id');

            foreach ($parCommune as $communeId => $g) {
                $odp = (float) $g->where('type', TaxCalculationService::TYPE_ODP)->sum('amount');
                $tm  = (float) $g->where('type', TaxCalculationService::TYPE_TM)->sum('amount');
                $lignes->push([
                    'commune_id'  => (int) $communeId,
                    'commune'     => $g->first()['commune'] ?? '—',
                    'year'        => $year,
                    'month'       => $mois,
                    'panel_count' => $g->where('type', TaxCalculationService::TYPE_ODP)->count(),
                    'days'        => 0,
                    'odp'         => round($odp, 2),
                    'tm'          => round($tm, 2),
                    'total'       => round($odp + $tm, 2),
                ]);
            }
        }

        return $this->memoMensuel[$cle] = $lignes->sortBy(['commune', 'month'])->values();
    }

    /**
     * Totaux ODP / TM / total d'une année en UN appel annuel au moteur
     * (2026-11-16) — pour la courbe pluriannuelle du rapport, où douze
     * appels par année passée seraient trop lents. Mêmes règles que
     * monthlyMatrix() ; seuls les tarifs sont ceux du 1er janvier.
     *
     * @return array{odp: float, tm: float, total: float}
     */
    public function totalsAnnuels(int $year, array $filters = []): array
    {
        $filtres = array_filter([
            'client_id'   => $filters['client_id']   ?? null,
            'campaign_id' => $filters['campaign_id'] ?? null,
            'commune_id'  => $filters['commune_id']  ?? null,
        ]) + ['inclure_lumipub' => \App\Services\Taxes\TaxeExerciceService::inclureLumipub()];

        $lignes = app(TaxCalculationService::class)
            ->generateLines(TaxCalculationService::PERIOD_ANNUAL, 0, $year, $filtres);
        $odp = (float) $lignes->where('type', TaxCalculationService::TYPE_ODP)->sum('amount');
        $tm  = (float) $lignes->where('type', TaxCalculationService::TYPE_TM)->sum('amount');

        return ['odp' => $odp, 'tm' => $tm, 'total' => $odp + $tm];
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
