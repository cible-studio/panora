<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * CaRealService — CA RÉEL (facturé HT + encaissé TTC) pour la page Rapports.
 *
 * Bloc 4 — Famille B "CA réel" (2026-06-18).
 *
 * Pourquoi un nouveau service ?
 *   - La page Rapports affichait jusqu'ici `Campaign::sum('total_amount')`
 *     comme "CA période" — c'est le CA CONTRACTUEL (prévisionnel), pas le
 *     CA réel comptable. La patronne veut désormais voir le CA réel
 *     (factures émises HT + paiements encaissés TTC) côte à côte avec le
 *     CA contractuel.
 *
 * Single source of truth :
 *   - Les définitions métier (encaissé, facturé) doivent rester identiques
 *     à celles de FinancialDashboardService — sinon Finance et Rapports
 *     affichent des chiffres divergents pour la même période. Ce service
 *     délègue donc le calcul des KPIs à FinancialDashboardService, et se
 *     contente d'enrichir avec :
 *       - les filtres "ignorés" (commune, zone, category) pour le bandeau
 *         d'info côté Rapports — cf. Garde-fou 1 ;
 *       - les séries mensuelles 12 mois (HT facturé / TTC encaissé) pour
 *         le graphique Chart.js à 2 lignes — cf. Commit 13.
 *
 * Périmètre des filtres acceptés (cf. arbitrage Q2 patronne) :
 *   - ✅ from, to, commercial_id, client_id
 *   - ❌ commune_id, zone, category_id : ignorés silencieusement (loggés
 *        dans `ignored_filters` pour bandeau).
 */
class CaRealService
{
    /** Filtres incompatibles avec la logique facturation (cf. Q2). */
    public const INCOMPATIBLE_FILTER_KEYS = ['commune_id', 'zone', 'category_id'];

    public function __construct(
        protected FinancialDashboardService $financial,
    ) {}

    /**
     * KPIs CA réel sur une période.
     *
     * @param  string|CarbonInterface  $from
     * @param  string|CarbonInterface  $to
     * @param  ?int                    $commercialUserId  filtre commercial
     * @param  ?int                    $clientId          filtre client (sera repris en plan B si besoin)
     * @param  array                   $extraFilters      filtres passés par RapportController — ignored_filters dérivés
     * @return array{
     *     ht_facture: float,
     *     ttc_encaisse: float,
     *     taux_recouvrement: float,
     *     ignored_filters: array<int,string>,
     * }
     */
    public function kpis(
        string|CarbonInterface $from,
        string|CarbonInterface $to,
        ?int $commercialUserId = null,
        ?int $clientId = null,
        array $extraFilters = []
    ): array {
        [$fromCarbon, $toCarbon] = $this->normalizePeriod($from, $to);

        // ── Délégation à FinancialDashboardService pour garantir la
        //    cohérence Finance ↔ Rapports (cf. Garde-fou 3 patronne).
        $finance = $this->financial->kpis($fromCarbon, $toCarbon, $commercialUserId);

        // Si un client_id est passé, on re-calcule en restreignant aux
        // factures de ce client uniquement (FinancialDashboardService ne
        // scope que par commercial). On ne touche pas aux autres clés.
        if ($clientId !== null) {
            [$htFacture, $ttcEncaisse] = $this->kpisForClient(
                $fromCarbon,
                $toCarbon,
                $commercialUserId,
                $clientId
            );
            $tauxRecouvrement = $htFacture > 0
                ? round(($ttcEncaisse / max(1.0, $htFacture)) * 100, 1)
                : 0.0;
        } else {
            $htFacture        = $finance['facture_periode_ht'];
            $ttcEncaisse      = $finance['encaisse'];
            $tauxRecouvrement = $finance['taux_recouvrement'];
        }

        return [
            'ht_facture'        => round((float) $htFacture, 2),
            'ttc_encaisse'      => round((float) $ttcEncaisse, 2),
            'taux_recouvrement' => $tauxRecouvrement,
            'ignored_filters'   => $this->detectIgnoredFilters($extraFilters),
        ];
    }

    /**
     * Série mensuelle "HT facturé" pour une année calendaire — 12 buckets.
     *
     * @return Collection<int,array{label:string, month:int, ht:float}>
     */
    public function mensuelHtFacture(int $year, ?int $commercialUserId = null, ?int $clientId = null): Collection
    {
        $rows = collect();
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($year, $m, 1)->startOfMonth();
            $end   = Carbon::create($year, $m, 1)->endOfMonth();
            $ht    = (float) $this->invoicesQuery($start, $end, $commercialUserId, $clientId)->sum('net_ht');
            $rows->push([
                'label' => self::moisLabel($m),
                'month' => $m,
                'ht'    => round($ht, 2),
            ]);
        }
        return $rows;
    }

    /**
     * Série mensuelle "TTC encaissé" pour une année calendaire — 12 buckets.
     *
     * @return Collection<int,array{label:string, month:int, ttc:float}>
     */
    public function mensuelTtcEncaisse(int $year, ?int $commercialUserId = null, ?int $clientId = null): Collection
    {
        $rows = collect();
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($year, $m, 1)->startOfMonth();
            $end   = Carbon::create($year, $m, 1)->endOfMonth();
            $ttc   = (float) $this->paymentsQuery($start, $end, $commercialUserId, $clientId)->sum('invoice_payments.montant');
            $rows->push([
                'label' => self::moisLabel($m),
                'month' => $m,
                'ttc'   => round($ttc, 2),
            ]);
        }
        return $rows;
    }

    /**
     * Vérifie si l'array de filtres passé contient au moins un filtre
     * incompatible avec la logique facturation (commune/zone/catégorie).
     * Utilisé par RapportController pour décider d'afficher le bandeau.
     */
    public static function hasIncompatibleCaFilters(array $filters): bool
    {
        foreach (self::INCOMPATIBLE_FILTER_KEYS as $key) {
            if (!empty($filters[$key])) {
                return true;
            }
        }
        return false;
    }

    // ══════════════════════════════════════════════════════════════════
    // PRIVÉ — helpers
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return array{0:float, 1:float} [htFacture, ttcEncaisse]
     */
    protected function kpisForClient(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $commercialUserId,
        int $clientId
    ): array {
        $ht  = (float) $this->invoicesQuery($from, $to, $commercialUserId, $clientId)->sum('net_ht');
        $ttc = (float) $this->paymentsQuery($from, $to, $commercialUserId, $clientId)->sum('invoice_payments.montant');
        return [$ht, $ttc];
    }

    /**
     * Query factures émises sur la période — mêmes exclusions que Finance
     * (brouillon, annulee, credit_note). Scope optionnel commercial + client.
     */
    protected function invoicesQuery(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $commercialUserId = null,
        ?int $clientId = null
    ) {
        $q = Invoice::query()
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereNull('credit_note_for_id')
            ->whereBetween('issued_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($commercialUserId !== null) {
            $q->forCommercialUser($commercialUserId);
        }
        if ($clientId !== null) {
            $q->where('client_id', $clientId);
        }
        return $q;
    }

    /**
     * Query paiements sur la période — mêmes exclusions que Finance.
     */
    protected function paymentsQuery(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $commercialUserId = null,
        ?int $clientId = null
    ) {
        return InvoicePayment::query()
            ->whereBetween('invoice_payments.paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('invoice', function ($iv) use ($commercialUserId, $clientId) {
                $iv->whereNotIn('status', ['brouillon', 'annulee'])
                   ->whereNull('credit_note_for_id');
                if ($commercialUserId !== null) {
                    $iv->forCommercialUser($commercialUserId);
                }
                if ($clientId !== null) {
                    $iv->where('client_id', $clientId);
                }
            });
    }

    protected function detectIgnoredFilters(array $extra): array
    {
        $ignored = [];
        foreach (self::INCOMPATIBLE_FILTER_KEYS as $key) {
            if (!empty($extra[$key])) {
                $ignored[] = $key;
            }
        }
        return $ignored;
    }

    /**
     * @return array{0:CarbonInterface, 1:CarbonInterface}
     */
    protected function normalizePeriod(string|CarbonInterface $from, string|CarbonInterface $to): array
    {
        $f = $from instanceof CarbonInterface ? $from->copy() : Carbon::parse($from);
        $t = $to   instanceof CarbonInterface ? $to->copy()   : Carbon::parse($to);
        return [$f, $t];
    }

    protected static function moisLabel(int $m): string
    {
        return [
            1 => 'janv.', 2 => 'févr.', 3 => 'mars',  4 => 'avr.',
            5 => 'mai',   6 => 'juin',  7 => 'juil.', 8 => 'août',
            9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.',
        ][$m] ?? '?';
    }
}
