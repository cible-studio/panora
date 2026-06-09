<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Collection;

/**
 * PaymentAllocator — ventile un paiement (ou un montant arbitraire) sur
 * les lignes de la facture au prorata du montant HT ligne.
 *
 * Source unique de cette règle métier — utilisée par le tableau de bord
 * financier pour :
 *   - répartir un encaissement par commune (via line.commune_id)
 *   - répartir un encaissement par commercial (via campaign liée)
 *
 * Règle :
 *   ratio_i = montant_ht_ligne_i / sum(montant_ht_ligne)
 *   alloué_à_ligne_i = paiement × ratio_i
 *
 * Si la facture n'a pas de lignes (cas dégénéré : factures historiques
 * avant la refonte FNE), on retombe sur 100% à la commune NULL — l'appelant
 * décide quoi en faire (souvent : exclure du graphe par commune).
 */
class PaymentAllocator
{
    /**
     * Ventile un paiement sur les lignes de sa facture.
     *
     * @return Collection<array> chaque entrée : ['line_id', 'commune_id',
     *                          'commune_name', 'ratio', 'amount']
     */
    public function allocate(InvoicePayment $payment): Collection
    {
        return $this->allocateAmount($payment->invoice, (float) $payment->montant);
    }

    /**
     * Ventile un montant arbitraire sur les lignes d'une facture. Utile
     * pour calculer la part d'une facture allouée à une commune (sans
     * passer par un paiement individuel).
     */
    public function allocateAmount(?Invoice $invoice, float $amount): Collection
    {
        if (!$invoice) return collect();

        $lines = $invoice->relationLoaded('lines') ? $invoice->lines : $invoice->lines()->get();
        $total = (float) $lines->sum('montant_ht_ligne');

        if ($total <= 0) {
            // Facture sans lignes ou lignes à 0 — on remonte une seule
            // entrée "non ventilable" pour ne pas perdre le montant.
            return collect([[
                'line_id'      => null,
                'commune_id'   => null,
                'commune_name' => null,
                'ratio'        => 1.0,
                'amount'       => $amount,
            ]]);
        }

        return $lines->map(function ($line) use ($amount, $total) {
            $ratio = (float) $line->montant_ht_ligne / $total;
            return [
                'line_id'      => $line->id,
                'commune_id'   => $line->commune_id,
                'commune_name' => $line->snapshot_commune_name ?? $line->commune?->name,
                'ratio'        => $ratio,
                'amount'       => round($amount * $ratio, 2),
            ];
        })->values();
    }
}
