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

    /**
     * Cas réel : 2 lignes dans 2 communes différentes — chaque ligne
     * doit utiliser SON tarif ODP. Plateau (15 000) + Cocody (5 000).
     *
     * Ligne 1 (Plateau) : PU 200 000 × 1 × 3 mois × 12 m²
     *   HT       = 200 000 × 1 × 3      = 600 000
     *   ODP      = 15 000 × 12 × 1 × 3   = 540 000
     *   TM       =  1 000 × 12 × 1 × 3   =  36 000
     *
     * Ligne 2 (Cocody) : PU 150 000 × 2 × 3 mois × 8 m²
     *   HT       = 150 000 × 2 × 3      = 900 000
     *   ODP      = 5 000 × 8 × 2 × 3     = 240 000
     *   TM       = 1 000 × 8 × 2 × 3     =  48 000
     *
     * Totaux facture :
     *   HT brut       = 1 500 000
     *   Net HT        = 1 500 000 (pas de remise)
     *   TVA           = 270 000
     *   TTC           = 1 770 000
     *   TSP           = 45 000
     *   Total TM      = 84 000
     *   Total ODP     = 780 000
     *   Autres taxes  = 909 000
     *   TOTAL À PAYER = 2 679 000
     */
    public function test_multi_lignes_multi_communes(): void
    {
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([
            [
                'pu_ht_mensuel'     => 200000,
                'quantite'          => 1,
                'duree_mois'        => 3,
                'dimension_m2'      => 12,
                'odp_rate_applique' => 15000,
                'tm_rate_applique'  => 1000,
            ],
            [
                'pu_ht_mensuel'     => 150000,
                'quantite'          => 2,
                'duree_mois'        => 3,
                'dimension_m2'      => 8,
                'odp_rate_applique' => 5000,
                'tm_rate_applique'  => 1000,
            ],
        ]);

        $this->assertEqualsWithDelta(1500000, $totals['amount'],        0.01, 'Total HT brut');
        $this->assertEqualsWithDelta(1500000, $totals['net_ht'],        0.01, 'Net HT (sans remise)');
        $this->assertEqualsWithDelta(270000,  $totals['tva_amount'],    0.01, 'TVA 18%');
        $this->assertEqualsWithDelta(1770000, $totals['amount_ttc'],    0.01, 'TTC');
        $this->assertEqualsWithDelta(45000,   $totals['tsp_amount'],    0.01, 'TSP 3%');
        $this->assertEqualsWithDelta(780000,  $totals['odp_total'],     0.01, 'ODP cumul 540k + 240k');
        $this->assertEqualsWithDelta(84000,   $totals['tm_total'],      0.01, 'TM cumul 36k + 48k');
        $this->assertEqualsWithDelta(2679000, $totals['total_a_payer'], 0.01, 'TOTAL À PAYER');
    }

    /**
     * Durée fractionnaire (0.5 mois = règle CIBLE pour les courts spots).
     * PU 100 000 × 1 panneau × 0.5 mois × 10 m² / Yopougon (ODP 1000)
     *   HT  = 50 000
     *   TVA = 9 000
     *   TTC = 59 000
     *   TSP = 1 500
     *   ODP = 1 000 × 10 × 1 × 0.5 = 5 000
     *   TM  = 1 000 × 10 × 1 × 0.5 = 5 000
     *   Autres taxes = 11 500
     *   TOTAL = 70 500
     */
    public function test_duree_fractionnaire_demi_mois(): void
    {
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([[
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 0.5,
            'dimension_m2'      => 10,
            'odp_rate_applique' => 1000,
            'tm_rate_applique'  => 1000,
        ]]);

        $this->assertEqualsWithDelta(50000, $totals['amount'],        0.01);
        $this->assertEqualsWithDelta(9000,  $totals['tva_amount'],    0.01);
        $this->assertEqualsWithDelta(59000, $totals['amount_ttc'],    0.01);
        $this->assertEqualsWithDelta(1500,  $totals['tsp_amount'],    0.01);
        $this->assertEqualsWithDelta(5000,  $totals['odp_total'],     0.01);
        $this->assertEqualsWithDelta(5000,  $totals['tm_total'],      0.01);
        $this->assertEqualsWithDelta(70500, $totals['total_a_payer'], 0.01);
    }

    /**
     * Cas limite : remise 100% (gratuité) — Net HT et TVA/TSP tombent à 0,
     * MAIS ODP et TM restent dues (taxes propres, indépendantes du HT).
     * 1 panneau Plateau 12m² pendant 2 mois, PU 500 000, remise 100%.
     *   HT brut       = 1 000 000
     *   Net HT        = 0
     *   TVA / TSP     = 0
     *   ODP           = 15 000 × 12 × 1 × 2 = 360 000
     *   TM            =  1 000 × 12 × 1 × 2 =  24 000
     *   TOTAL         = 384 000 (uniquement les taxes communales)
     */
    public function test_remise_100pct_garde_taxes_communales(): void
    {
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([[
            'pu_ht_mensuel'     => 500000,
            'quantite'          => 1,
            'duree_mois'        => 2,
            'dimension_m2'      => 12,
            'odp_rate_applique' => 15000,
            'tm_rate_applique'  => 1000,
        ]], ['remise_pct' => 100]);

        $this->assertEqualsWithDelta(1000000, $totals['amount'],        0.01);
        $this->assertEqualsWithDelta(0,       $totals['net_ht'],        0.01);
        $this->assertEqualsWithDelta(0,       $totals['tva_amount'],    0.01);
        $this->assertEqualsWithDelta(0,       $totals['tsp_amount'],    0.01);
        $this->assertEqualsWithDelta(360000,  $totals['odp_total'],     0.01, 'ODP reste due malgré remise 100%');
        $this->assertEqualsWithDelta(24000,   $totals['tm_total'],      0.01, 'TM reste due malgré remise 100%');
        $this->assertEqualsWithDelta(384000,  $totals['total_a_payer'], 0.01);
    }

    /**
     * Garde-fou TM : si tm_rate_applique=0 (commune mal seedée), le
     * fallback doit bien appliquer 1000 (base nationale 2025) — sinon
     * pénalité fiscale CI. Vérifie aussi qu'un tm_rate explicite > 0
     * est respecté (dérogation possible).
     */
    public function test_fallback_tm_si_taux_zero(): void
    {
        $calc = new InvoiceCalculator();

        // Cas 1 : commune mal seedée → tm = 0 → fallback 1000
        $line0 = $calc->calculateLine([
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 1,
            'dimension_m2'      => 5,
            'odp_rate_applique' => 1000,
            'tm_rate_applique'  => 0,
        ]);
        $this->assertEqualsWithDelta(5000, $line0['tm_ligne'], 0.01,
            'TM = 1000 × 5 = 5000 (fallback car tm_rate=0)');

        // Cas 2 : tm_rate explicite > 0 → on respecte
        $line2k = $calc->calculateLine([
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 1,
            'dimension_m2'      => 5,
            'odp_rate_applique' => 1000,
            'tm_rate_applique'  => 2000,
        ]);
        $this->assertEqualsWithDelta(10000, $line2k['tm_ligne'], 0.01,
            'TM = 2000 × 5 = 10000 (dérogation respectée)');
    }

    /**
     * Services annexes libres (prompt v2) — N lignes avec libellé +
     * prix HT, chaque ligne soumise à TVA 18 %. Le total services TTC
     * doit être la somme des prix HT × 1,18.
     *
     * 1 ligne facturation simple + 3 services libres :
     *   - Frais d'impression       50 000 HT → 59 000 TTC
     *   - Photographe livraison    80 000 HT → 94 400 TTC
     *   - Conception créa         120 000 HT → 141 600 TTC
     * Total services HT  = 250 000
     * Total services TTC = 295 000
     *
     * Ligne facturation : PU 100k × 1 × 1 × 5 m² / commune ODP 1000
     *   HT    = 100 000
     *   TVA   = 18 000
     *   TTC   = 118 000
     *   TSP   = 3 000
     *   ODP   = 1 000 × 5 × 1 × 1 = 5 000
     *   TM    = 1 000 × 5 × 1 × 1 = 5 000
     *   Autres = 13 000
     * TOTAL = 118 000 + 13 000 + 295 000 = 426 000
     */
    public function test_services_annexes_libres_n_lignes(): void
    {
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([[
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 1,
            'dimension_m2'      => 5,
            'odp_rate_applique' => 1000,
            'tm_rate_applique'  => 1000,
        ]], [
            'services' => [
                ['label' => "Frais d'impression",      'prix_ht' => 50000],
                ['label' => 'Photographe livraison',    'prix_ht' => 80000],
                ['label' => 'Conception créa',          'prix_ht' => 120000],
            ],
        ]);

        $this->assertEqualsWithDelta(250000, $totals['services_ht_total'],  0.01);
        $this->assertEqualsWithDelta(295000, $totals['services_ttc_total'], 0.01);
        $this->assertEqualsWithDelta(426000, $totals['total_a_payer'],      0.01);
    }

    /**
     * Rétrocompat : si on passe les anciens champs services_impression
     * et services_pose_depose (et PAS le tableau moderne 'services'),
     * le calculator les agrège en 2 services et donne le même total.
     * Assure que les factures historiques continuent de fonctionner.
     */
    public function test_services_legacy_retrocompatibles(): void
    {
        $calc = new InvoiceCalculator();
        $modern = $calc->calculateInvoice([], [
            'services' => [
                ['label' => "Frais d'impression",      'prix_ht' => 50000],
                ['label' => 'Frais de pose et dépose', 'prix_ht' => 30000],
            ],
        ]);
        $legacy = $calc->calculateInvoice([], [
            'services_impression'  => 50000,
            'services_pose_depose' => 30000,
        ]);
        $this->assertEqualsWithDelta($modern['total_a_payer'], $legacy['total_a_payer'], 0.01);
        $this->assertEqualsWithDelta(80000, $legacy['services_ht_total'],  0.01);
        $this->assertEqualsWithDelta(94400, $legacy['services_ttc_total'], 0.01);
    }

    /**
     * Remise + services : la remise s'applique UNIQUEMENT sur le HT des
     * lignes. Les services ont leur propre TVA (18%) et ne sont pas
     * affectés par la remise (frais réels facturés au coût).
     *
     * 1 ligne PU 200k / 1 / 1 mois / 5 m² / Marcory (ODP 1000)
     *   HT brut = 200 000, Remise 10% → Net HT 180 000
     *   TVA     = 32 400
     *   TTC     = 212 400
     *   TSP     = 5 400
     *   ODP     = 1 000 × 5 × 1 × 1 = 5 000
     *   TM      = 1 000 × 5 × 1 × 1 = 5 000
     *   Autres taxes = 15 400
     * Services :
     *   Impression 50 000 + Pose 30 000 = 80 000 HT → 94 400 TTC
     * TOTAL = 212 400 + 15 400 + 94 400 = 322 200
     */
    public function test_remise_et_services_combines(): void
    {
        $calc = new InvoiceCalculator();
        $totals = $calc->calculateInvoice([[
            'pu_ht_mensuel'     => 200000,
            'quantite'          => 1,
            'duree_mois'        => 1,
            'dimension_m2'      => 5,
            'odp_rate_applique' => 1000,
            'tm_rate_applique'  => 1000,
        ]], [
            'remise_pct'           => 10,
            'services_impression'  => 50000,
            'services_pose_depose' => 30000,
        ]);

        $this->assertEqualsWithDelta(200000, $totals['amount'],               0.01, 'HT brut intact');
        $this->assertEqualsWithDelta(180000, $totals['net_ht'],               0.01, 'Net HT après remise 10%');
        $this->assertEqualsWithDelta(32400,  $totals['tva_amount'],           0.01, 'TVA sur Net HT (pas sur HT brut)');
        $this->assertEqualsWithDelta(212400, $totals['amount_ttc'],           0.01);
        $this->assertEqualsWithDelta(5400,   $totals['tsp_amount'],           0.01);
        $this->assertEqualsWithDelta(5000,   $totals['odp_total'],            0.01);
        $this->assertEqualsWithDelta(5000,   $totals['tm_total'],             0.01);
        $this->assertEqualsWithDelta(50000,  $totals['services_impression'],  0.01);
        $this->assertEqualsWithDelta(30000,  $totals['services_pose_depose'], 0.01);
        $this->assertEqualsWithDelta(322200, $totals['total_a_payer'],        0.01);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TX-9 (2026-07-29) — Mode "dates campagne fournies"
    // TM = mois anniversaires · ODP = trimestres × (tarif × 3)
    // ═══════════════════════════════════════════════════════════════════

    public function test_tx9_avec_dates_campagne_courte_1_mois_1_trimestre(): void
    {
        $calc = new InvoiceCalculator();

        // Campagne 01/03 → 05/03 (5 jours) : TM = 1 mois, ODP = 1 trimestre (T1)
        $line = [
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 1,          // saisi par le commercial
            'dimension_m2'      => 12,
            'odp_rate_applique' => 4000,       // Adjamé
            'tm_rate_applique'  => 1000,
            'campaign_start'    => '2026-03-01',
            'campaign_end'      => '2026-03-05',
        ];

        $c = $calc->calculateLine($line);

        // Loyer = pu × qte × duree_mois négocié = 100000 × 1 × 1 = 100000
        $this->assertEquals(100000, $c['montant_ht_ligne']);
        // TM = 1000 × 12 × 1 mois anniv = 12 000
        $this->assertEquals(12000, $c['tm_ligne']);
        // ODP = (4000 × 3) × 12 × 1 trimestre = 144 000 (forfait T1 plein)
        $this->assertEquals(144000, $c['odp_ligne']);
    }

    public function test_tx9_avec_dates_campagne_15mars_30avril_2_mois_2_trimestres(): void
    {
        $calc = new InvoiceCalculator();

        // Campagne 15/03 → 30/04 : TM = 2 mois (anniv 15/04 dépassé),
        // ODP = 2 trimestres (T1 + T2 touchés)
        $line = [
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 2,
            'dimension_m2'      => 12,
            'odp_rate_applique' => 4000,
            'tm_rate_applique'  => 1000,
            'campaign_start'    => '2026-03-15',
            'campaign_end'      => '2026-04-30',
        ];

        $c = $calc->calculateLine($line);

        $this->assertEquals(200000, $c['montant_ht_ligne'], 'Loyer 2 mois négociés');
        $this->assertEquals(24000,  $c['tm_ligne'],         'TM = 1000×12×2');
        $this->assertEquals(288000, $c['odp_ligne'],        'ODP = 4000×3×12×2 trimestres');
    }

    public function test_tx9_sans_dates_campagne_fallback_ancien_comportement(): void
    {
        $calc = new InvoiceCalculator();

        // Sans dates → fallback sur duree_mois pour TM et ODP
        // (compatibilité totale avec les factures FNE émises avant TX-9)
        $line = [
            'pu_ht_mensuel'     => 100000,
            'quantite'          => 1,
            'duree_mois'        => 3,
            'dimension_m2'      => 12,
            'odp_rate_applique' => 4000,
            'tm_rate_applique'  => 1000,
            // campaign_start / campaign_end ABSENTS
        ];

        $c = $calc->calculateLine($line);

        $this->assertEquals(300000, $c['montant_ht_ligne'], 'Loyer 3 mois');
        $this->assertEquals(36000,  $c['tm_ligne'],         'TM ancien : 1000×12×3');
        $this->assertEquals(144000, $c['odp_ligne'],        'ODP ancien : 4000×12×3 (PAS ×3 trimestriel)');
    }
}
