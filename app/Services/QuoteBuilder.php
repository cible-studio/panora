<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteLine;

/**
 * QuoteBuilder — recalcule les totaux d'un devis en réutilisant
 * exactement les mêmes règles que la facture (InvoiceCalculator).
 *
 * Le principe : le client doit voir les mêmes chiffres au centime près
 * sur son devis puis sur sa facture finale. Utiliser 2 calculators
 * différents = risque de divergence → un seul calculator, appelé par
 * QuoteBuilder ici et par InvoiceCalculator::recalculateAndPersist().
 */
class QuoteBuilder
{
    public function __construct(protected InvoiceCalculator $calculator) {}

    /**
     * Recalcule tous les agrégats d'un devis à partir de ses lignes
     * en base + services annexes, puis persiste.
     *
     * @return Quote  fresh instance après persist
     */
    public function recalculateAndPersist(Quote $quote): Quote
    {
        $quote->loadMissing(['lines', 'services']);

        // 1) Recalcul par ligne (montant_ht, odp, tm) → persist sur chaque QuoteLine
        // Puis reload pour que $quote->lines contienne les valeurs à jour.
        foreach ($quote->lines as $line) {
            $calc = $this->calculator->calculateLine([
                'pu_ht_mensuel'      => (float) $line->pu_ht_mensuel,
                'quantite'           => (int) $line->quantite,
                'duree_mois'         => (float) $line->duree_mois,
                'dimension_m2'       => (float) $line->dimension_m2,
                'odp_rate_applique'  => (float) $line->odp_rate_applique,
                'tm_rate_applique'   => (float) $line->tm_rate_applique,
            ]);
            $line->forceFill([
                'montant_ht_ligne' => $calc['montant_ht_ligne'],
                'odp_ligne'        => $calc['odp_ligne'],
                'tm_ligne'         => $calc['tm_ligne'],
            ])->save();
        }
        $quote->load('lines'); // reload avec valeurs à jour

        // 2) Agrégats devis
        $linesArr = $quote->lines->map(fn(QuoteLine $l) => $l->toArray())->all();
        $svcArr   = $quote->services->map(fn($s) => [
            'label'   => $s->label,
            'prix_ht' => (float) $s->prix_ht,
        ])->all();

        $totals = $this->calculator->calculateInvoice($linesArr, [
            'remise_pct' => (float) $quote->remise_pct,
            'services'   => !empty($svcArr) ? $svcArr : null,
        ]);

        // calculateInvoice retourne aussi services_ht_total et services_ttc_total
        // qui SONT stockés dans la table quotes (différence avec invoices).
        // On garde toutes les clés.

        // Retirer les clés non-persistables sur la table quotes
        unset($totals['services_impression'], $totals['services_pose_depose']);

        $quote->forceFill($totals)->save();
        return $quote->fresh(['lines', 'services']);
    }
}
