<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Commune;
use Illuminate\Support\Collection;

/**
 * BillingAllocationService — répartit les encaissements par commune
 * et par ville au PRORATA DU HT des lignes (§11 cahier).
 *
 * Citation cahier :
 *   "Une facture pouvant porter plusieurs communes, répartir chaque
 *    encaissement au prorata du HT des lignes. CENTRALISER CETTE
 *    RÈGLE." → c'est ICI.
 *
 * Évite la duplication dans chaque vue analytique (dashboard finance,
 * fiche client, rapports). Toutes les vues qui ventilent un montant
 * "payé par commune" passent par ce service.
 */
class BillingAllocationService
{
    /**
     * Calcule la répartition d'un montant (typiquement total encaissé
     * d'une facture) sur les lignes au prorata du HT.
     *
     * @return array<int, array{commune_id:int|null, commune_name:string, share_pct:float, amount:int}>
     */
    public function allocateForInvoice(Invoice $invoice, int $amount): array
    {
        $invoice->loadMissing('lines.commune');
        $lines = $invoice->lines;

        $totalHt = (int) $lines->sum('montant_ht_ligne');
        if ($totalHt <= 0 || $amount <= 0) {
            return [];
        }

        // Agrège par commune
        $byCommune = $lines->groupBy(fn($l) => $l->commune_id)->map(function ($group) {
            $first = $group->first();
            return [
                'commune_id'   => $first->commune_id,
                'commune_name' => $first->commune?->name ?? $first->snapshot_commune_name ?? '—',
                'ht'           => (int) $group->sum('montant_ht_ligne'),
            ];
        })->values();

        $alloc = [];
        $consumed = 0;
        $lastIdx = $byCommune->count() - 1;

        foreach ($byCommune as $i => $row) {
            $pct = $row['ht'] / $totalHt;
            // Le dernier prend le solde pour absorber les arrondis
            $part = $i === $lastIdx
                ? $amount - $consumed
                : (int) round($amount * $pct);
            $consumed += $part;

            $alloc[] = [
                'commune_id'   => $row['commune_id'],
                'commune_name' => $row['commune_name'],
                'share_pct'    => round($pct * 100, 2),
                'amount'       => $part,
            ];
        }

        return $alloc;
    }

    /**
     * Agrège la ventilation pour une collection de factures (ex: toutes
     * les factures payées du mois). Retourne le total par commune.
     *
     * @param iterable<Invoice> $invoices
     * @return Collection<int, array{commune_id, commune_name, total}>
     */
    public function aggregateByCommune(iterable $invoices): Collection
    {
        $totals = [];

        foreach ($invoices as $invoice) {
            $paid = (int) $invoice->paidAmount();
            if ($paid <= 0) continue;

            foreach ($this->allocateForInvoice($invoice, $paid) as $row) {
                $key = $row['commune_id'] ?? 'unknown';
                if (!isset($totals[$key])) {
                    $totals[$key] = [
                        'commune_id'   => $row['commune_id'],
                        'commune_name' => $row['commune_name'],
                        'total'        => 0,
                    ];
                }
                $totals[$key]['total'] += $row['amount'];
            }
        }

        return collect($totals)
            ->sortByDesc('total')
            ->values();
    }
}
