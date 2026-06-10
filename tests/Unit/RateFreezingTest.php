<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Test du gel des taux à l'émission (RÈGLE D'OR #3 du cahier).
 *
 * « les taux ODP/TM appliqués sont figés sur chaque ligne au moment
 *   de la validation de la facture (via Commune::ratesAt(issued_at)),
 *   pour que la modification ultérieure d'un tarif ne change pas une
 *   facture passée. »
 *
 * On vérifie 2 propriétés essentielles :
 *
 *   1. Quand le builder/controller crée une InvoiceLine, il pose
 *      odp_rate_applique + tm_rate_applique = SNAPSHOT au moment T.
 *
 *   2. Le calculateur (InvoiceCalculator) utilise STRICTEMENT ces
 *      colonnes figées, pas les valeurs courantes de Commune (qui
 *      ont pu changer après l'émission).
 *
 * Le point 2 est testable en Unit sans DB : on passe au calculateur
 * un tableau de lignes avec des odp_rate_applique différents des
 * valeurs "actuelles" et on vérifie que les calculs utilisent bien
 * les snapshots, pas autre chose.
 */
class RateFreezingTest extends TestCase
{
    public function test_calculator_uses_snapshotted_odp_rate_not_current_commune_rate(): void
    {
        // Cas concret : ligne Plateau facturée en 2024 quand ODP était
        // 8 000 (par hypothèse). En 2025 le tarif est passé à 15 000.
        // La facture 2024 doit garder son ODP à 8 000 — pas être
        // re-calculée à 15 000 si on rafraîchit la facture.
        $calc = new \App\Services\InvoiceCalculator();

        $ligne2024 = [
            'pu_ht_mensuel'     => 200_000,
            'quantite'          => 1,
            'duree_mois'        => 3,
            'dimension_m2'      => 12,
            'odp_rate_applique' => 8_000, // SNAPSHOT 2024 (différent du 2025 réel)
            'tm_rate_applique'  => 1_000,
        ];

        $out = $calc->calculateLine($ligne2024);

        // ODP = 8_000 × 12 × 1 × 3 = 288_000 (basé sur le snapshot)
        // Si le calculator allait chercher la valeur 2025 (15_000), on
        // aurait ODP = 540_000.
        $this->assertSame(288_000, $out['odp_ligne'],
            'Le calculator doit utiliser odp_rate_applique snapshot, pas le tarif courant Commune.');
        $this->assertSame(36_000, $out['tm_ligne'],
            'Idem pour TM (1_000 × 12 × 1 × 3).');
    }

    public function test_calculator_falls_back_to_default_tm_only_if_snapshot_is_zero(): void
    {
        // Si la ligne a tm_rate_applique = 0 (commune mal seedée à l'époque
        // de l'émission), on tombe sur la base nationale 1_000 (config
        // billing.tm_default). Ça reste un "gel" car la valeur appliquée
        // est résolue AU MOMENT du calcul de la ligne, pas re-lookupée
        // depuis Commune à chaque vue.
        $calc = new \App\Services\InvoiceCalculator();

        $out = $calc->calculateLine([
            'pu_ht_mensuel'     => 100_000,
            'quantite'          => 1,
            'duree_mois'        => 1,
            'dimension_m2'      => 5,
            'odp_rate_applique' => 1_000,
            'tm_rate_applique'  => 0, // commune mal seedée
        ]);

        // Fallback 1_000 × 5 × 1 × 1 = 5_000
        $this->assertSame(5_000, $out['tm_ligne'],
            'TM=0 → fallback sur tm_default config (1_000), évite zéro fiscal silencieux.');
    }

    public function test_odp_rate_zero_is_preserved_exoneration(): void
    {
        // ODP=0 = exonération explicite (rare mais possible pour une
        // commune particulière). Ne PAS fallback comme pour TM —
        // ce serait fausser la fiscalité réelle de cette commune.
        $calc = new \App\Services\InvoiceCalculator();

        $out = $calc->calculateLine([
            'pu_ht_mensuel'     => 100_000,
            'quantite'          => 1,
            'duree_mois'        => 1,
            'dimension_m2'      => 5,
            'odp_rate_applique' => 0,    // exonération commune
            'tm_rate_applique'  => 1_000,
        ]);

        $this->assertSame(0, $out['odp_ligne'],
            'ODP=0 = exonération, doit rester à 0 (pas de fallback)');
    }

    public function test_invoice_aggregate_respects_per_line_snapshots(): void
    {
        // Facture avec 2 lignes ayant des ODP figés DIFFÉRENTS :
        //   Ligne 1 (Plateau, snapshot 2024) ODP 8_000
        //   Ligne 2 (Plateau, snapshot 2025) ODP 15_000
        // Le calculateur facture doit additionner les 2 sans aller
        // chercher une valeur unique "courante Plateau".
        $calc = new \App\Services\InvoiceCalculator();

        $totals = $calc->calculateInvoice([
            [
                'pu_ht_mensuel'     => 100_000, 'quantite' => 1, 'duree_mois' => 1,
                'dimension_m2'      => 10,
                'odp_rate_applique' => 8_000,
                'tm_rate_applique'  => 1_000,
            ],
            [
                'pu_ht_mensuel'     => 100_000, 'quantite' => 1, 'duree_mois' => 1,
                'dimension_m2'      => 10,
                'odp_rate_applique' => 15_000,
                'tm_rate_applique'  => 1_000,
            ],
        ]);

        // odp_total = 8_000 × 10 + 15_000 × 10 = 80_000 + 150_000 = 230_000
        $this->assertSame(230_000, $totals['odp_total'],
            'Le total ODP additionne les snapshots distincts ligne par ligne.');
        // tm_total = 1_000 × 10 × 2 = 20_000
        $this->assertSame(20_000, $totals['tm_total']);
    }
}
