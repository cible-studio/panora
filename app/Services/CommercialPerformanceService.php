<?php
// app/Services/CommercialPerformanceService.php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CommercialPerformanceService — source unique pour les KPIs Performance Commerciale.
 *
 * Orchestre :
 *   - FinancialDashboardService::kpis(...)   → recouvrement/encaisse/du/en_retard (réutilisé)
 *   - calculs spécifiques M1 : panier moyen, délai paiement moyen, CA HT/TTC,
 *     répartition par secteur, diversification (Herfindahl + % top-1), trends.
 *
 * RBAC enforcement : la méthode resolveCommercialIdForCurrentUser() force
 * un user commercial à ne voir que ses propres données — protège contre
 * le forging d'URL /admin/performance/commerciaux/{autre-id}.
 */
class CommercialPerformanceService
{
    public function __construct(protected FinancialDashboardService $financial) {}

    /**
     * KPI principaux pour un commercial sur la période.
     * Délègue le bloc financier à FinancialDashboardService et complète.
     *
     * @return array{
     *   ca_ht:int, ca_ttc:int, nb_campagnes:int, panier_moyen_ttc:int,
     *   taux_recouvrement:float, encaisse:int, du:int, en_retard:int,
     *   facture_periode:int, delai_moyen_paiement_jours:?int,
     *   factures_impayees_count:int, reste_du:int
     * }
     */
    public function kpis(int $commercialId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fdsKpi = $this->financial->kpis($from, $to, $commercialId);

        // ── CA TTC sur la période = sum(campaigns.total_amount)
        //    où campaigns appartient au commercial ET chevauche la période.
        $caTtc = (int) $this->campaignsBaseQuery($commercialId, $from, $to)->sum('total_amount');

        // ── CA HT = sum(invoices.net_ht) pour les factures du commercial
        //    sur la période. Champ exposé par Invoice (BIGINT en FCFA entiers).
        $caHt = (int) DB::table('invoices')
            ->where('commercial_user_id', $commercialId)
            ->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['annulee'])
            ->sum('net_ht');

        $nbCampagnes = $this->campaignsBaseQuery($commercialId, $from, $to)->count();
        $panierMoyen = $nbCampagnes > 0 ? (int) round($caTtc / $nbCampagnes) : 0;

        // ── Délai moyen de paiement (jours entre issued_at et paid_at)
        $delai = DB::table('invoices')
            ->where('commercial_user_id', $commercialId)
            ->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('paid_at')
            ->selectRaw('AVG(DATEDIFF(paid_at, issued_at)) as avg_days')
            ->value('avg_days');

        // ── Factures impayées (statuts non-soldé)
        $impayees = Invoice::query()
            ->where('commercial_user_id', $commercialId)
            ->whereIn('status', ['envoyee', 'partiellement_payee', 'en_retard', 'litige'])
            ->get();
        $resteDu = $impayees->sum(fn ($inv) => $inv->remainingAmount());

        return [
            'ca_ttc'                     => $caTtc,
            'ca_ht'                      => $caHt,
            'nb_campagnes'               => $nbCampagnes,
            'panier_moyen_ttc'           => $panierMoyen,
            'taux_recouvrement'          => (float) $fdsKpi['taux_recouvrement'],
            'encaisse'                   => (int) $fdsKpi['encaisse'],
            'du'                         => (int) $fdsKpi['du'],
            'en_retard'                  => (int) $fdsKpi['en_retard'],
            'facture_periode'            => (int) $fdsKpi['facture_periode'],
            'delai_moyen_paiement_jours' => $delai !== null ? (int) round($delai) : null,
            'factures_impayees_count'    => $impayees->count(),
            'reste_du'                   => (int) $resteDu,
        ];
    }

    /**
     * CA par secteur d'activité client pour ce commercial sur la période.
     * Source : sum(campaigns.total_amount) GROUP BY clients.sector.
     *
     * @return Collection<int, array{sector:string, ca:int, count:int, pct:float}>
     */
    public function bySector(int $commercialId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = $this->campaignsBaseQuery($commercialId, $from, $to)
            ->join('clients', 'clients.id', '=', 'campaigns.client_id')
            ->selectRaw('COALESCE(NULLIF(clients.sector,""), "Non renseigné") as sector,
                         SUM(campaigns.total_amount) as ca,
                         COUNT(*) as cnt')
            ->groupBy('sector')
            ->orderByDesc('ca')
            ->get();

        $total = (int) $rows->sum('ca');
        return $rows->map(fn ($r) => [
            'sector' => $r->sector,
            'ca'     => (int) $r->ca,
            'count'  => (int) $r->cnt,
            'pct'    => $total > 0 ? round(($r->ca / $total) * 100, 1) : 0.0,
        ])->values();
    }

    /** Top N secteurs (sous-set de bySector). */
    public function topSectors(int $commercialId, CarbonInterface $from, CarbonInterface $to, int $limit = 3): Collection
    {
        return $this->bySector($commercialId, $from, $to)->take($limit);
    }

    /**
     * TOP COMMERCIAL PAR SECTEUR d'activité (vue d'ensemble équipe).
     *
     * Pour chaque secteur client présent dans la période, renvoie le
     * commercial qui a réalisé le plus de CA sur ce secteur, son CA
     * et son nombre de campagnes. Utile sur le tableau de bord
     * Performance commerciale (admin/MP) pour identifier les "spécialistes"
     * de chaque industrie (ex: qui domine Banque & Finance ? Auto ?).
     *
     * Stratégie : 1 seule requête SQL groupée (secteur, commercial)
     * + post-traitement PHP pour garder le top par secteur. Inclut aussi
     * le CA total du secteur et la part_du_top% pour mesurer la
     * concentration (un commercial qui fait 90% d'un secteur = monopole).
     *
     * @return Collection<array{sector:string, commercial_id:int,
     *                           commercial_name:string, ca:int, count:int,
     *                           sector_total_ca:int, share_pct:float}>
     */
    public function topCommercialBySector(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = Campaign::query()
            ->join('clients', 'clients.id', '=', 'campaigns.client_id')
            ->join('users',   'users.id',   '=', 'campaigns.commercial_user_id')
            ->whereNotNull('campaigns.commercial_user_id')
            ->whereNull('campaigns.deleted_at')
            ->where('campaigns.status', '!=', 'annule')
            ->where('campaigns.start_date', '<=', $to)
            ->where('campaigns.end_date',   '>=', $from)
            ->selectRaw('
                COALESCE(NULLIF(clients.sector, ""), "Non renseigné") as sector,
                campaigns.commercial_user_id                          as commercial_id,
                users.name                                            as commercial_name,
                SUM(campaigns.total_amount)                           as ca,
                COUNT(*)                                              as cnt
            ')
            ->groupBy('sector', 'campaigns.commercial_user_id', 'users.name')
            ->orderBy('sector')
            ->orderByDesc('ca')
            ->get();

        // Pour chaque secteur, garde la 1re ligne (= top par CA) +
        // calcule la part du top vs total secteur.
        return $rows->groupBy('sector')
            ->map(function ($group) {
                $top       = $group->first();
                $secteurCa = (int) $group->sum('ca');
                return [
                    'sector'           => $top->sector,
                    'commercial_id'    => (int) $top->commercial_id,
                    'commercial_name'  => $top->commercial_name,
                    'ca'               => (int) $top->ca,
                    'count'            => (int) $top->cnt,
                    'sector_total_ca'  => $secteurCa,
                    'share_pct'        => $secteurCa > 0 ? round(($top->ca / $secteurCa) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('sector_total_ca')
            ->values();
    }

    /**
     * Score de diversification — indice de Herfindahl-Hirschman INVERSÉ.
     *   1.0 = portefeuille parfaitement équilibré (tous clients à parts égales)
     *   0.0 = mono-client (1 client = 100 % du CA)
     *
     * Formule : 1 - Σ((ca_client_i / ca_total)²)
     * Cf. https://fr.wikipedia.org/wiki/Indice_de_Herfindahl
     */
    public function diversificationScore(int $commercialId, CarbonInterface $from, CarbonInterface $to): float
    {
        $rows = $this->campaignsBaseQuery($commercialId, $from, $to)
            ->selectRaw('client_id, SUM(total_amount) as ca')
            ->groupBy('client_id')
            ->pluck('ca');

        $total = (float) $rows->sum();
        if ($total <= 0) return 0.0;

        $sumSquared = $rows->sum(fn ($ca) => pow($ca / $total, 2));
        return round(max(0.0, min(1.0, 1.0 - $sumSquared)), 3);
    }

    /**
     * Part du CA réalisée chez le top-1 client (lecture plus intuitive
     * que Herfindahl pour beaucoup de gens).
     *
     * @return array{client_id:?int, client_name:?string, ca:int, pct:float}
     */
    public function topClientShare(int $commercialId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->campaignsBaseQuery($commercialId, $from, $to)
            ->join('clients', 'clients.id', '=', 'campaigns.client_id')
            ->selectRaw('clients.id, clients.name, SUM(campaigns.total_amount) as ca')
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('ca')
            ->get();

        if ($rows->isEmpty()) {
            return ['client_id' => null, 'client_name' => null, 'ca' => 0, 'pct' => 0.0];
        }
        $top = $rows->first();
        $total = (float) $rows->sum('ca');
        return [
            'client_id'   => (int) $top->id,
            'client_name' => $top->name,
            'ca'          => (int) $top->ca,
            'pct'         => $total > 0 ? round(($top->ca / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Évolution CA mensuelle pour le commercial — N derniers mois glissants.
     *
     * @return Collection<int, array{label:string, year:int, month:int, ca:int}>
     */
    public function monthlyTrend(int $commercialId, int $months = 12): Collection
    {
        $end   = now()->endOfMonth();
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = DB::table('campaigns')
            ->where('commercial_user_id', $commercialId)
            ->whereNull('deleted_at')
            ->where('start_date', '<=', $end)
            ->selectRaw('YEAR(start_date) as y, MONTH(start_date) as m, SUM(total_amount) as ca')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

        $moisFr = [1=>'janv', 2=>'févr', 3=>'mars', 4=>'avr', 5=>'mai', 6=>'juin', 7=>'juil', 8=>'août', 9=>'sept', 10=>'oct', 11=>'nov', 12=>'déc'];

        $result = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $key = $d->year . '-' . str_pad((string) $d->month, 2, '0', STR_PAD_LEFT);
            $result->push([
                'label' => $moisFr[$d->month] . ' ' . $d->format('y'),
                'year'  => $d->year,
                'month' => $d->month,
                'ca'    => isset($rows[$key]) ? (int) $rows[$key]->ca : 0,
            ]);
        }
        return $result;
    }

    /**
     * COURBE GLOBALE — évolution mensuelle du CA équipe sur N mois
     * (somme de TOUS les commerciaux). Affiché en tête de la page
     * Performance commerciale pour donner le tempo global de la régie.
     *
     * Inclut aussi le nombre de campagnes mensuelles pour distinguer
     * un mois "gros tickets" (peu de campagnes, gros CA) d'un mois
     * "volume" (beaucoup de petites campagnes).
     *
     * @return Collection<array{label:string,year:int,month:int,ca:int,count:int}>
     */
    public function globalMonthlyTrend(int $months = 12): Collection
    {
        $end   = now()->endOfMonth();
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = DB::table('campaigns')
            ->whereNotNull('commercial_user_id')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'annule')
            ->where('start_date', '<=', $end)
            ->where('start_date', '>=', $start)
            ->selectRaw('YEAR(start_date) as y, MONTH(start_date) as m,
                         SUM(total_amount) as ca,
                         COUNT(*) as cnt')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

        $moisFr = [1=>'janv', 2=>'févr', 3=>'mars', 4=>'avr', 5=>'mai', 6=>'juin', 7=>'juil', 8=>'août', 9=>'sept', 10=>'oct', 11=>'nov', 12=>'déc'];

        $result = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $d   = now()->subMonths($i);
            $key = $d->year . '-' . str_pad((string) $d->month, 2, '0', STR_PAD_LEFT);
            $result->push([
                'label' => $moisFr[$d->month] . ' ' . $d->format('y'),
                'year'  => $d->year,
                'month' => $d->month,
                'ca'    => isset($rows[$key]) ? (int) $rows[$key]->ca   : 0,
                'count' => isset($rows[$key]) ? (int) $rows[$key]->cnt  : 0,
            ]);
        }
        return $result;
    }

    /**
     * Comparaison année N vs N-1 du CA.
     * @return array{current:int, previous:int, delta_pct:?float}
     */
    public function yearComparison(int $commercialId, int $year): array
    {
        $current  = (int) DB::table('campaigns')
            ->where('commercial_user_id', $commercialId)
            ->whereYear('start_date', $year)
            ->whereNull('deleted_at')
            ->sum('total_amount');
        $previous = (int) DB::table('campaigns')
            ->where('commercial_user_id', $commercialId)
            ->whereYear('start_date', $year - 1)
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $delta = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
        return ['current' => $current, 'previous' => $previous, 'delta_pct' => $delta];
    }

    /**
     * Liste paginée des campagnes du commercial sur la période avec montants.
     */
    public function campaignsList(int $commercialId, CarbonInterface $from, CarbonInterface $to, int $perPage = 20)
    {
        return Campaign::query()
            ->where('commercial_user_id', $commercialId)
            ->where('start_date', '<=', $to)
            ->where('end_date',   '>=', $from)
            ->with(['client:id,name,sector', 'invoices:id,campaign_id,total_a_payer,status,issued_at,paid_at'])
            ->orderByDesc('start_date')
            ->paginate($perPage);
    }

    /**
     * Classement de TOUS les commerciaux sur la période — leaderboard direction.
     *
     * @return Collection<int, array{user, ca_ttc, taux_recouvrement, panier_moyen,
     *                                nb_campagnes, encaisse, reste_du}>
     */
    public function leaderboard(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $commerciaux = User::commerciaux()->get(['id', 'name', 'email', 'agent_code']);

        return $commerciaux->map(function (User $u) use ($from, $to) {
            $k = $this->kpis($u->id, $from, $to);
            return [
                'user'              => $u,
                'ca_ttc'            => $k['ca_ttc'],
                'ca_ht'             => $k['ca_ht'],
                'taux_recouvrement' => $k['taux_recouvrement'],
                'panier_moyen'      => $k['panier_moyen_ttc'],
                'nb_campagnes'      => $k['nb_campagnes'],
                'encaisse'          => $k['encaisse'],
                'reste_du'          => $k['reste_du'],
            ];
        })->sortByDesc('ca_ttc')->values();
    }

    /**
     * RBAC enforcement : un user commercial ne peut voir que ses propres stats.
     * Un admin / mediaplanner / direction peut voir n'importe qui.
     *
     * @return int|null   ID effectif à utiliser, ou null si refus.
     */
    public function resolveCommercialIdForCurrentUser(?int $requestedId, ?User $currentUser): ?int
    {
        if (!$currentUser) return null;
        $role = $currentUser->role?->value;
        if (in_array($role, ['admin', 'mediaplanner'], true)) {
            return $requestedId ?? $currentUser->id; // direction voit qui elle veut
        }
        if ($role === 'commercial') {
            // Force : on ignore $requestedId si différent de soi.
            return $currentUser->id;
        }
        return null; // technique : pas d'accès
    }

    /**
     * Base query : campagnes du commercial dont la période chevauche [from, to].
     * Filtre exclusion soft-delete + statuts annulés (faussent les stats).
     */
    protected function campaignsBaseQuery(int $commercialId, CarbonInterface $from, CarbonInterface $to)
    {
        return Campaign::query()
            ->where('campaigns.commercial_user_id', $commercialId)
            ->whereNull('campaigns.deleted_at')
            ->where('campaigns.status', '!=', 'annule')
            ->where('campaigns.start_date', '<=', $to)
            ->where('campaigns.end_date',   '>=', $from);
    }
}
