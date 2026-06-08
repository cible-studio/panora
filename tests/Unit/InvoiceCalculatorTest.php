<?php

namespace Tests\Unit;

use App\Services\InvoiceCalculator;
use Tests\TestCase;

/**
 * Tests référence FNE — Module Facturation Panora.
 *
 * Test de contrôle Treichville (prompt validé 2026-06-08) :
 *   PU 130 000 / 1 panneau / 4 mois / 12 m² / Treichville (ODP 1000)
 *   → HT 520 000, TVA 93 600, TTC 613 600,
 *     ODP 48 000, TM 48 000, TSP 15 600,
 *     AUTRES TAXES 111 600, TOTAL 725 200.
 */
class InvoiceCalculatorTest extends TestCase
{
    public function test_treichville_reference_compute_giving_725200(): void
    {
        $calc = new InvoiceCalculator();

        $line = [
            'pu_ht_mensuel'     => 130000,
            'quantite'          => 1,
            'duree_mois'        => 4,
            'dimension_m2'      => 12,
            'odp_rate_applique' => 1000,
            'tm_rate_applique'  => 1000,
        ];

        // Vérification ligne
        $lineCalc = $calc->calculateLine($line);
        $this->assertEqualsWithDelta(520000, $lineCalc['montant_ht_ligne'], 0.01);
        $this->assertEqualsWithDelta(48000,  $lineCalc['odp_ligne'], 0.01);
        $this->assertEqualsWithDelta(48000,  $lineCalc['tm_ligne'], 0.01);

        // Vérification facture
        $totals = $calc->calculateInvoice([$line]);

        $this->assertEqualsWithDelta(520000, $totals['amount'],        0.01, 'Total HT brut');
        $this->assertEqualsWithDelta(520000, $totals['net_ht'],        0.01, 'Net HT (pas de remise)');
        $this->assertEqualsWithDelta(93600,  $totals['tva_amount'],    0.01, 'TVA 18%');
        $this->assertEqualsWithDelta(613600, $totals['amount_ttc'],    0.01, 'TTC');
        $this->assertEqualsWithDelta(15600,  $totals['tsp_amount'],    0.01, 'TSP 3%');
        $this->assertEqualsWithDelta(48000,  $totals['tm_total'],      0.01, 'Total TM');
        $this->assertEqualsWithDelta(48000,  $totals['odp_total'],     0.01, 'Total ODP');
        $this->assertEqualsWithDelta(725200, $totals['total_a_payer'], 0.01, 'TOTAL À PAYER FNE');
    }

    public function test_plateau_3panneaux_2mois_8m2_pu_500k_avec_remise_5pct(): void
    {
        // Cas réaliste : Plateau (ODP 15 000), 3 panneaux 2 mois 8 m², PU 500 000
        // HT brut = 500 000 × 3 × 2 = 3 000 000
        // Net HT (5% remise) = 2 850 000
        // TVA = 513 000, TSP = 85 500, TTC = 3 363 000
        // ODP = 15 000 × 8 × 3 × 2 = 720 000
        // TM  =  1 000 × 8 × 3 × 2 =  48 000
        // Autres taxes = 85 500 + 720 000 + 48 000 = 853 500
        // Total = 3 363 000 + 853 500 = 4 216 500
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([[
            'pu_ht_mensuel'     => 500000,
            'quantite'          => 3,
            'duree_mois'        => 2,
            'dimension_m2'      => 8,
            'odp_rate_applique' => 15000,
            'tm_rate_applique'  => 1000,
        ]], ['remise_pct' => 5]);

        $this->assertEqualsWithDelta(3000000, $totals['amount'],        0.01);
        $this->assertEqualsWithDelta(2850000, $totals['net_ht'],        0.01);
        $this->assertEqualsWithDelta(513000,  $totals['tva_amount'],    0.01);
        $this->assertEqualsWithDelta(3363000, $totals['amount_ttc'],    0.01);
        $this->assertEqualsWithDelta(85500,   $totals['tsp_amount'],    0.01);
        $this->assertEqualsWithDelta(720000,  $totals['odp_total'],     0.01);
        $this->assertEqualsWithDelta(48000,   $totals['tm_total'],      0.01);
        $this->assertEqualsWithDelta(4216500, $totals['total_a_payer'], 0.01);
    }

    public function test_services_additionnels_sont_factures_ttc(): void
    {
        // Services seuls (impression 100k + pose-dépose 50k = 150k HT)
        // TVA services = 27 000 → TTC services = 177 000
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([], [
            'services_impression'  => 100000,
            'services_pose_depose' => 50000,
        ]);

        $this->assertEqualsWithDelta(0,      $totals['net_ht'],        0.01);
        $this->assertEqualsWithDelta(0,      $totals['tva_amount'],    0.01);
        $this->assertEqualsWithDelta(150000, $totals['services_impression'] + $totals['services_pose_depose'], 0.01);
        // Total = 0 + 0 + 0 + 0 + 0 + 177 000 = 177 000
        $this->assertEqualsWithDelta(177000, $totals['total_a_payer'], 0.01);
    }

    public function test_facture_vide_donne_zero(): void
    {
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([]);
        $this->assertEqualsWithDelta(0, $totals['total_a_payer'], 0.01);
        $this->assertEqualsWithDelta(0, $totals['amount'],        0.01);
        $this->assertEqualsWithDelta(0, $totals['net_ht'],        0.01);
    }
}
