<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceSchedule;
use App\Models\Relance;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FinancialDashboardService — source unique de calcul pour le tableau
 * de bord financier (encaissements, créances, recouvrement).
 *
 * RÈGLES :
 *   • Ne RECALCULE PAS les montants déjà gérés par le module Facturation
 *     (Invoice::paidAmount, remainingAmount, paymentStatus, isOverdue…).
 *   • Pour les vues "par commune" et "par commercial", on ventile le
 *     paiement au prorata des lignes via PaymentAllocator.
 *   • Le scope commercial respecte Invoice::forCommercialUser (4 cas).
 *   • Les vues "factures" excluent toujours les brouillons et les avoirs
 *     (credit_note_for_id IS NOT NULL) — les avoirs sont des écritures
 *     négatives qui faussent les totaux d'encaissement.
 *
 * BUCKETS période :
 *   day | week | month | quarter | year
 *   Le service auto-pick si le caller ne précise pas (basé sur la
 *   plage demandée pour rester lisible : ≤ 31j → day, ≤ 90j → week,
 *   ≤ 365j → month, > 365j → month aussi par défaut, sinon quarter).
 */
class FinancialDashboardService
{
    public function __construct(
        protected PaymentAllocator $allocator,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    // KPI globaux (en-tête du tableau de bord)
    // ══════════════════════════════════════════════════════════════════

    /**
     * 4 KPI affichés en tête du dashboard.
     *
     * @return array{encaisse:float, du:float, en_retard:float,
     *               taux_recouvrement:float, facture_periode:float}
     */
    public function kpis(CarbonInterface $from, CarbonInterface $to, ?int $commercialUserId = null): array
    {
        $encaisse = $this->totalEncaissePeriode($from, $to, $commercialUserId);

        // Total dû = somme des reste-à-payer sur factures actives
        $du = $this->openInvoicesQuery($commercialUserId)
            ->get()
            ->sum(fn($inv) => $inv->remainingAmount());

        // En retard = idem mais filtré sur isOverdue()
        $enRetard = $this->openInvoicesQuery($commercialUserId)
            ->get()
            ->filter(fn($inv) => $inv->isOverdue())
            ->sum(fn($inv) => $inv->remainingAmount());

        // Facturé sur la période = total_a_payer des factures émises (issued_at) sur la période
        $facturePeriode = (float) $this->invoicesQueryForPeriod($from, $to, $commercialUserId)->sum('total_a_payer');

        $taux = $facturePeriode > 0 ? round(($encaisse / $facturePeriode) * 100, 1) : 0.0;

        return [
            'encaisse'          => round($encaisse, 2),
            'du'                => round($du, 2),
            'en_retard'         => round($enRetard, 2),
            'taux_recouvrement' => $taux,
            'facture_periode'   => round($facturePeriode, 2),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    // ENCAISSEMENTS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Série temporelle des encaissements pour le graphique d'évolution.
     *
     * @param  string  $bucket  day|week|month|quarter|year (auto si null)
     * @return array<int,array{label:string, key:string, montant:float}>
     */
    public function encaissementsByPeriod(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $bucket = null,
        ?int $commercialUserId = null
    ): array {
        $bucket = $bucket ?: $this->autoBucket($from, $to);

        $rows = $this->paymentsQueryForPeriod($from, $to, $commercialUserId)
            ->get(['invoice_payments.paid_at', 'invoice_payments.montant']);

        // Agrégation en mémoire (volumes raisonnables — pas plus de qq
        // milliers de paiements/an pour CIBLE). Si volumétrie monte,
        // basculer sur GROUP BY DATE_FORMAT côté SQL.
        $grouped = [];
        foreach ($rows as $p) {
            $key = $this->bucketKey($p->paid_at, $bucket);
            $grouped[$key] = ($grouped[$key] ?? 0) + (float) $p->montant;
        }

        // Reconstitue tous les buckets de la période (même vides) pour
        // que le graphe ne saute pas de points.
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->endOfDay();
        $series = [];
        while ($cursor->lte($end)) {
            $key = $this->bucketKey($cursor, $bucket);
            if (!isset($series[$key])) {
                $series[$key] = [
                    'key'     => $key,
                    'label'   => $this->bucketLabel($cursor, $bucket),
                    'montant' => round($grouped[$key] ?? 0.0, 2),
                ];
            }
            $cursor = $this->bucketAdvance($cursor, $bucket);
        }
        return array_values($series);
    }

    /**
     * Top clients par montant encaissé sur la période.
     */
    public function encaissementsByClient(CarbonInterface $from, CarbonInterface $to, int $limit = 10, ?int $commercialUserId = null): Collection
    {
        return $this->paymentsQueryForPeriod($from, $to, $commercialUserId)
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->join('clients',  'clients.id',  '=', 'invoices.client_id')
            ->groupBy('clients.id', 'clients.name')
            ->select(
                'clients.id',
                'clients.name',
                DB::raw('SUM(invoice_payments.montant) as total'),
                DB::raw('COUNT(DISTINCT invoices.id) as factures_count')
            )
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'client_id'      => (int) $r->id,
                'client_name'    => $r->name,
                'total'          => round((float) $r->total, 2),
                'factures_count' => (int) $r->factures_count,
            ]);
    }

    /**
     * Répartition des encaissements par commune (ventilation au prorata
     * des lignes via PaymentAllocator).
     */
    public function encaissementsByCommune(CarbonInterface $from, CarbonInterface $to, ?int $commercialUserId = null): Collection
    {
        $payments = $this->paymentsQueryForPeriod($from, $to, $commercialUserId)
            ->with(['invoice.lines.commune:id,name'])
            ->get();

        $byCommune = []; // [commune_id => ['name'=>X, 'total'=>Y]]
        foreach ($payments as $p) {
            foreach ($this->allocator->allocate($p) as $alloc) {
                $cid = $alloc['commune_id'] ?? 0;
                if (!isset($byCommune[$cid])) {
                    $byCommune[$cid] = [
                        'commune_id'   => $cid ?: null,
                        'commune_name' => $alloc['commune_name'] ?? '— Non ventilable',
                        'total'        => 0.0,
                    ];
                }
                $byCommune[$cid]['total'] += $alloc['amount'];
            }
        }

        return collect($byCommune)
            ->map(fn($r) => array_merge($r, ['total' => round($r['total'], 2)]))
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Encaissements par commercial — remonte campaign.commercial_user_id
     * (fallback créateur résa ou créateur campagne via le scope).
     *
     * Stratégie : pour chaque paiement, on identifie le commercial de la
     * campagne liée (ou via la résa), et on attribue 100% du montant à
     * ce commercial. Une facture = un commercial (pas de mix par ligne
     * pour le commercial — c'est le commercial du DOSSIER campagne).
     */
    public function encaissementsByCommercial(CarbonInterface $from, CarbonInterface $to, ?int $commercialUserId = null): Collection
    {
        // Eager-load les relations utilisées par resolveCommercialContact() —
        // la relation est nommée `commercial` (pas `commercialUser`) sur
        // Campaign ET Reservation. Cf. Campaign:67 / Reservation:300.
        $payments = $this->paymentsQueryForPeriod($from, $to, $commercialUserId)
            ->with([
                'invoice.campaign.commercial:id,name',
                'invoice.campaign.reservation.commercial:id,name',
                'invoice.campaign.user:id,name',
                'invoice.creator:id,name',
            ])
            ->get();

        $byCom = []; // [user_id => ['name'=>X, 'total'=>Y, 'factures'=>set]]
        foreach ($payments as $p) {
            $user = $this->resolveCommercialForInvoice($p->invoice);
            $uid  = $user?->id ?? 0;
            $name = $user?->name ?? '— Sans commercial';
            if (!isset($byCom[$uid])) {
                $byCom[$uid] = [
                    'user_id'         => $uid ?: null,
                    'user_name'       => $name,
                    'total'           => 0.0,
                    'factures_uniq'   => [],
                ];
            }
            $byCom[$uid]['total'] += (float) $p->montant;
            $byCom[$uid]['factures_uniq'][$p->invoice_id] = true;
        }

        return collect($byCom)
            ->map(fn($r) => [
                'user_id'        => $r['user_id'],
                'user_name'      => $r['user_name'],
                'total'          => round($r['total'], 2),
                'factures_count' => count($r['factures_uniq']),
            ])
            ->sortByDesc('total')
            ->values();
    }

    // ══════════════════════════════════════════════════════════════════
    // CRÉANCES
    // ══════════════════════════════════════════════════════════════════

    /**
     * Liste paginée des créances : factures avec reste à payer > 0.
     * Filtres : statut paiement (non_payee|partielle|en_retard), client, commune.
     */
    public function creancesQuery(?int $commercialUserId = null)
    {
        return $this->openInvoicesQuery($commercialUserId)
            ->with(['client:id,name', 'campaign:id,name', 'schedules' => fn($q) => $q->orderBy('due_date'), 'payments']);
    }

    /**
     * Balance âgée : répartit le reste à payer par tranche d'ancienneté
     * basée sur la due_date la plus ancienne non payée (fallback issued_at).
     *
     * @return array{
     *     buckets: array<string,float>,
     *     total: float,
     *     by_client: Collection<array{client_id:int,client_name:string,
     *                                  buckets:array<string,float>,total:float}>
     * }
     */
    public function agingBalance(?int $commercialUserId = null): array
    {
        $invoices = $this->openInvoicesQuery($commercialUserId)
            ->with(['schedules', 'client:id,name', 'payments'])
            ->get();

        $buckets = [
            '0-30'    => 0.0,
            '31-60'   => 0.0,
            '61-90'   => 0.0,
            '91-120'  => 0.0,
            '120+'    => 0.0,
        ];
        $byClient = []; // [client_id => ['client_id', 'client_name', 'buckets', 'total']]

        $today = Carbon::today();
        foreach ($invoices as $inv) {
            $remaining = $inv->remainingAmount();
            if ($remaining <= 0) continue;

            $ageDays = $this->invoiceAgeDays($inv, $today);
            $bucketKey = $this->ageBucket($ageDays);
            $buckets[$bucketKey] += $remaining;

            $cid = $inv->client_id;
            if (!isset($byClient[$cid])) {
                $byClient[$cid] = [
                    'client_id'   => $cid,
                    'client_name' => $inv->client?->name ?? '—',
                    'buckets'     => array_fill_keys(array_keys($buckets), 0.0),
                    'total'       => 0.0,
                ];
            }
            $byClient[$cid]['buckets'][$bucketKey] += $remaining;
            $byClient[$cid]['total'] += $remaining;
        }

        return [
            'buckets'   => array_map(fn($v) => round($v, 2), $buckets),
            'total'     => round(array_sum($buckets), 2),
            'by_client' => collect($byClient)
                ->map(function ($r) {
                    $r['buckets'] = array_map(fn($v) => round($v, 2), $r['buckets']);
                    $r['total']   = round($r['total'], 2);
                    return $r;
                })
                ->sortByDesc('total')
                ->values(),
        ];
    }

    /**
     * Âge d'une facture en jours, basé sur la due_date la plus ancienne
     * non payée. Fallback issued_at si pas d'échéancier (cf. décision actée).
     */
    public function invoiceAgeDays(Invoice $invoice, ?CarbonInterface $ref = null): int
    {
        $ref = $ref ?? Carbon::today();
        $schedules = $invoice->relationLoaded('schedules') ? $invoice->schedules : $invoice->schedules()->get();

        $oldestUnpaid = $schedules->whereNull('paid_at')->sortBy('due_date')->first();
        $base = $oldestUnpaid?->due_date ?? $invoice->issued_at;
        if (!$base) return 0;

        return max(0, $base->startOfDay()->diffInDays($ref->copy()->startOfDay(), false));
    }

    protected function ageBucket(int $days): string
    {
        return match (true) {
            $days <= 30  => '0-30',
            $days <= 60  => '31-60',
            $days <= 90  => '61-90',
            $days <= 120 => '91-120',
            default      => '120+',
        };
    }

    // ══════════════════════════════════════════════════════════════════
    // RECOUVREMENT
    // ══════════════════════════════════════════════════════════════════

    /**
     * Clients à relancer — agrégation des restes à payer par client.
     *
     * @param  string  $sort  total_du | ancien | prochaine_echeance
     */
    public function clientsToFollow(
        string $sort = 'total_du',
        ?int $commercialUserId = null,
        ?int $filterCommercialId = null,
        ?int $filterCommuneId = null,
        ?int $filterClientId = null
    ): Collection
    {
        // Phase 8D cahier §8 — Filtres dynamiques sur le tableau de recouvrement :
        //   filterCommercialId : restreint aux factures du commercial (admin uniquement)
        //   filterCommuneId    : restreint aux factures avec au moins une ligne dans cette commune
        //   filterClientId     : restreint à un client précis
        $query = $this->openInvoicesQuery($commercialUserId);
        if ($filterCommercialId) {
            $query->where('commercial_user_id', $filterCommercialId);
        }
        if ($filterClientId) {
            $query->where('client_id', $filterClientId);
        }
        if ($filterCommuneId) {
            $query->whereHas('lines', fn($q) => $q->where('commune_id', $filterCommuneId));
        }

        $invoices = $query
            ->with(['client:id,name,phone,email', 'schedules' => fn($q) => $q->orderBy('due_date')])
            ->get();

        $byClient = [];
        $today = Carbon::today();
        foreach ($invoices as $inv) {
            $remaining = $inv->remainingAmount();
            if ($remaining <= 0) continue;

            $cid = $inv->client_id;
            if (!isset($byClient[$cid])) {
                $byClient[$cid] = [
                    'client_id'         => $cid,
                    'client_name'       => $inv->client?->name ?? '—',
                    'client_phone'      => $inv->client?->phone,
                    'client_email'      => $inv->client?->email,
                    'total_du'          => 0.0,
                    'factures_count'    => 0,
                    'plus_ancien_jours' => 0,
                    'prochaine_echeance'=> null,
                    'derniere_relance'  => null,
                    'en_retard'         => false,
                ];
            }

            $age = $this->invoiceAgeDays($inv, $today);

            $byClient[$cid]['total_du']          += $remaining;
            $byClient[$cid]['factures_count']++;
            $byClient[$cid]['plus_ancien_jours']  = max($byClient[$cid]['plus_ancien_jours'], $age);

            // Prochaine échéance = la due_date non payée la plus proche
            $next = $inv->schedules->whereNull('paid_at')->sortBy('due_date')->first();
            if ($next) {
                $existing = $byClient[$cid]['prochaine_echeance'];
                if (!$existing || $next->due_date->lt($existing)) {
                    $byClient[$cid]['prochaine_echeance'] = $next->due_date;
                }
            }

            if ($inv->isOverdue()) {
                $byClient[$cid]['en_retard'] = true;
            }
        }

        // Dernière relance par client
        $lastByClient = Relance::query()
            ->whereIn('client_id', array_keys($byClient))
            ->select('client_id', DB::raw('MAX(relance_date) as last_date'))
            ->groupBy('client_id')
            ->pluck('last_date', 'client_id');

        foreach ($byClient as $cid => &$row) {
            $row['derniere_relance'] = $lastByClient[$cid] ?? null;
            $row['total_du']         = round($row['total_du'], 2);
        }

        $coll = collect($byClient)->values();

        return match ($sort) {
            'ancien'             => $coll->sortByDesc('plus_ancien_jours')->values(),
            'prochaine_echeance' => $coll->sortBy(fn($r) => $r['prochaine_echeance']?->timestamp ?? PHP_INT_MAX)->values(),
            default              => $coll->sortByDesc('total_du')->values(),
        };
    }

    // ══════════════════════════════════════════════════════════════════
    // QUERIES DE BASE (réutilisées)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Factures "ouvertes" : ni brouillon, ni annulées, ni avoirs.
     * Le filtre reste-à-payer > 0 est appliqué en mémoire après chargement
     * (paidAmount est une méthode, pas une colonne).
     */
    protected function openInvoicesQuery(?int $commercialUserId = null)
    {
        $q = Invoice::query()
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereNull('credit_note_for_id');

        if ($commercialUserId !== null) {
            $q->forCommercialUser($commercialUserId);
        }
        return $q;
    }

    /** Factures émises sur la période. */
    protected function invoicesQueryForPeriod(CarbonInterface $from, CarbonInterface $to, ?int $commercialUserId = null)
    {
        $q = Invoice::query()
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereNull('credit_note_for_id')
            ->whereBetween('issued_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($commercialUserId !== null) {
            $q->forCommercialUser($commercialUserId);
        }
        return $q;
    }

    /**
     * Paiements de la période. On filtre les paiements via leur date
     * (paid_at) ET on exclut ceux dont la facture est annulée / brouillon
     * / un avoir (pour rester cohérent avec les autres queries).
     */
    protected function paymentsQueryForPeriod(CarbonInterface $from, CarbonInterface $to, ?int $commercialUserId = null)
    {
        $q = InvoicePayment::query()
            ->whereBetween('invoice_payments.paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('invoice', function ($iv) use ($commercialUserId) {
                $iv->whereNotIn('status', ['brouillon', 'annulee'])
                   ->whereNull('credit_note_for_id');
                if ($commercialUserId !== null) {
                    $iv->forCommercialUser($commercialUserId);
                }
            });
        return $q;
    }

    /** Total encaissé brut sur la période — utilisé par les KPI. */
    protected function totalEncaissePeriode(CarbonInterface $from, CarbonInterface $to, ?int $commercialUserId = null): float
    {
        return (float) $this->paymentsQueryForPeriod($from, $to, $commercialUserId)->sum('invoice_payments.montant');
    }

    // ══════════════════════════════════════════════════════════════════
    // BUCKETING TEMPOREL
    // ══════════════════════════════════════════════════════════════════

    protected function autoBucket(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = $from->diffInDays($to);
        return match (true) {
            $days <= 31  => 'day',
            $days <= 90  => 'week',
            $days <= 730 => 'month',
            default      => 'quarter',
        };
    }

    protected function bucketKey(CarbonInterface $date, string $bucket): string
    {
        return match ($bucket) {
            'day'     => $date->format('Y-m-d'),
            'week'    => $date->copy()->startOfWeek()->format('o-\WW'),
            'quarter' => $date->format('Y') . '-Q' . ceil($date->month / 3),
            'year'    => $date->format('Y'),
            default   => $date->format('Y-m'), // month
        };
    }

    protected function bucketLabel(CarbonInterface $date, string $bucket): string
    {
        return match ($bucket) {
            'day'     => $date->format('d/m'),
            'week'    => 'S' . $date->copy()->startOfWeek()->format('W'),
            'quarter' => 'T' . ceil($date->month / 3) . ' ' . $date->format('y'),
            'year'    => $date->format('Y'),
            default   => $date->locale('fr')->isoFormat('MMM YY'), // month
        };
    }

    protected function bucketAdvance(CarbonInterface $date, string $bucket): CarbonInterface
    {
        return match ($bucket) {
            'day'     => $date->copy()->addDay(),
            'week'    => $date->copy()->addWeek(),
            'quarter' => $date->copy()->addMonths(3),
            'year'    => $date->copy()->addYear(),
            default   => $date->copy()->addMonth(),
        };
    }

    // ══════════════════════════════════════════════════════════════════
    // RÉSOLUTION COMMERCIAL
    // ══════════════════════════════════════════════════════════════════

    /**
     * Résout le commercial d'une facture en suivant la chaîne canonique :
     *   campaign.commercialUser → reservation.commercialUser →
     *   reservation.user (créateur) → campaign.user → invoice.creator
     *
     * Renvoie null seulement si aucune piste — auquel cas la facture
     * apparaît dans "— Sans commercial".
     */
    protected function resolveCommercialForInvoice(?Invoice $invoice)
    {
        if (!$invoice) return null;
        $camp = $invoice->campaign;
        if ($camp) {
            // Délègue au modèle Campaign pour rester aligné avec les
            // autres vues "qui est le commercial du dossier ?"
            if (method_exists($camp, 'resolveCommercialContact')) {
                $contact = $camp->resolveCommercialContact();
                if ($contact) return $contact;
            }
        }
        return $invoice->creator;
    }
}
