<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Commune;
use App\Models\Panel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ZonesCommunesStatsService — Stats agrégées par commune sur une période.
 *
 * Colonnes : commune, ville, zone, total_panneaux, occupes, libres,
 *            maintenance, taux, tarif_moyen, ca_annee.
 *
 * Utilisé par :
 *  - Onglet "Zones & Communes" de /admin/rapports (via $statsCommunes calculé
 *    dans RapportController::buildReportData — logique dupliquée mais garde
 *    la vue existante inchangée).
 *  - Exports Excel + PDF dédiés à cet onglet (source de vérité unique).
 */
class ZonesCommunesStatsService
{
    /**
     * @param  Carbon  $from
     * @param  Carbon  $to
     * @param  array   $filters (commune_id, city, category_id, zone)
     * @return Collection<int, array>
     */
    public function build(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $filterCommune  = $filters['commune_id']  ?? null;
        $filterCity     = $filters['city']        ?? null;
        $filterCategory = $filters['category_id'] ?? null;
        $filterZone     = in_array($filters['zone'] ?? null, ['abidjan', 'interieur'], true)
                          ? $filters['zone'] : null;

        $communes = Commune::query()
            ->when($filterCommune, fn($q) => $q->where('id', $filterCommune))
            ->when($filterCity,    fn($q) => $q->where('city', $filterCity))
            ->when($filterZone === 'abidjan',   fn($q) => $q->whereIn('id', Commune::abidjanIds()))
            ->when($filterZone === 'interieur', fn($q) => $q->whereNotIn('id', Commune::abidjanIds()))
            ->get(['id', 'name', 'city']);

        $abidjanIds = Commune::abidjanIds();

        return $communes->map(function ($commune) use ($from, $to, $filterCategory, $abidjanIds) {
            $base = Panel::where('commune_id', $commune->id)
                ->when($filterCategory, fn($q) => $q->where('category_id', $filterCategory));

            $total = (clone $base)->count();

            $occ = (clone $base)->whereHas('campaigns', function ($q) use ($from, $to) {
                $q->whereIn('status', ['actif', 'planifie', 'termine'])
                  ->where('start_date', '<=', $to->toDateString())
                  ->where('end_date',   '>=', $from->toDateString());
            })->count();

            $maint  = (clone $base)->where('status', 'maintenance')->count();
            $libres = max(0, $total - $occ - $maint);
            $taux   = $total > 0 ? round(($occ / $total) * 100, 1) : 0;

            $tarifMoyen = (clone $base)->avg('monthly_rate') ?? 0;

            $caAnnee = Campaign::where('start_date', '<=', $to->toDateString())
                ->where('end_date',   '>=', $from->toDateString())
                ->whereIn('status', ['actif', 'planifie', 'termine'])
                ->whereHas('panels', fn($q) => $q->where('commune_id', $commune->id))
                ->sum('total_amount');

            return [
                'commune_id'  => $commune->id,
                'commune'     => $commune->name,
                'city'        => $commune->city ?? '—',
                'zone'        => in_array($commune->id, $abidjanIds, true) ? 'Abidjan' : 'Intérieur',
                'total'       => (int) $total,
                'occupes'     => (int) $occ,
                'libres'      => (int) $libres,
                'maintenance' => (int) $maint,
                'taux'        => (float) $taux,
                'tarif_moyen' => (int) round($tarifMoyen),
                'ca_annee'    => (int) $caAnnee,
            ];
        })
        ->filter(fn($r) => $r['total'] > 0)
        ->sortByDesc('taux')
        ->values();
    }

    /**
     * Totaux pour l'entête d'export (parc global sur la période).
     */
    public function summary(Collection $rows): array
    {
        return [
            'nb_communes' => $rows->count(),
            'nb_panels'   => $rows->sum('total'),
            'nb_occupes'  => $rows->sum('occupes'),
            'nb_libres'   => $rows->sum('libres'),
            'nb_maint'    => $rows->sum('maintenance'),
            'taux_moyen'  => $rows->count() > 0 ? round($rows->avg('taux'), 1) : 0,
            'ca_total'    => (int) $rows->sum('ca_annee'),
        ];
    }
}
