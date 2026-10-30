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
                // TX-9 : dates campagne (fallback sur period_start/end du devis
                // si les lignes ont été créées sans dates persistées).
                'campaign_start'     => $line->campaign_start ?? $quote->period_start,
                'campaign_end'       => $line->campaign_end   ?? $quote->period_end,
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
        // tva_applicable transmis depuis le 2026-10-30 : InvoiceCalculator
        // sait déjà le traiter (il le fait pour les factures depuis le
        // 2026-08-03), le devis se contentait de ne pas le lui passer —
        // un service sans TVA était donc annoncé TTC sur le devis et HT
        // sur la facture qui en découlait.
        $svcArr   = $quote->services->map(fn($s) => [
            'label'          => $s->label,
            'prix_ht'        => (float) $s->prix_ht,
            'tva_applicable' => (bool) $s->tva_applicable,
        ])->all();

        $totals = $this->calculator->calculateInvoice($linesArr, [
            'remise_pct' => (float) $quote->remise_pct,
            'services'   => !empty($svcArr) ? $svcArr : null,
        ]);

        // calculateInvoice retourne aussi services_ht_total et services_ttc_total
        // qui SONT stockés dans la table quotes (différence avec invoices).
        // On garde toutes les clés.

        // Retirer les clés non-persistables sur la table quotes. tsp_rate_override
        // est propre aux factures (ajout 2026-08-03) : l'écrire faisait planter
        // toute création de devis (« Unknown column », 2026-10-29).
        unset($totals['services_impression'], $totals['services_pose_depose'], $totals['tsp_rate_override']);

        $quote->forceFill($totals)->save();
        return $quote->fresh(['lines', 'services']);
    }
}
