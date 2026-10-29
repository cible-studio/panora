<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

/**
 * Cohérence des chiffres financiers (2026-10-29).
 *
 * Trois écarts avaient été relevés entre la liste des factures et le
 * tableau de bord financier :
 *
 *   1. le « Restant » de la liste sommait le montant FACTURÉ, sans
 *      déduire les versements déjà reçus ;
 *   2. les avoirs — écritures négatives — étaient écartés par Finance
 *      mais comptés par la liste ;
 *   3. les sommes SQL utilisaient `total_a_payer` seul, sans le repli
 *      sur `amount_ttc` appliqué partout ailleurs.
 *
 * Ces garde-fous tiennent la règle à sa source, sans base de données :
 * le calcul du montant vit dans le modèle, et les requêtes doivent
 * toutes passer par lui.
 */
class CoherenceFinanceTest extends TestCase
{
    private const RACINE = __DIR__ . '/../../';

    private function source(string $chemin): string
    {
        $fichier = self::RACINE . $chemin;
        $this->assertFileExists($fichier);

        return file_get_contents($fichier);
    }

    // ── Le montant total : une seule règle ─────────────────────────

    public function test_le_repli_sur_amount_ttc_n_est_ecrit_qu_une_fois(): void
    {
        $source = $this->source('app/Models/Invoice.php');

        // Une seule occurrence : dans montantTotal() lui-même. Elle était
        // recopiée cinq fois dans ce seul fichier.
        $this->assertSame(
            1,
            substr_count($source, 'total_a_payer ?: $this->amount_ttc'),
            'Le repli doit vivre uniquement dans Invoice::montantTotal().'
        );
    }

    public function test_le_montant_total_gere_le_zero_et_le_nul(): void
    {
        // La colonne est NOT NULL DEFAULT 0 : une facture créée hors
        // InvoiceCalculator y porte 0 alors que amount_ttc est renseigné.
        $avecZero = new Invoice(['total_a_payer' => 0, 'amount_ttc' => 450000]);
        $this->assertSame(450000.0, $avecZero->montantTotal());

        $renseignee = new Invoice(['total_a_payer' => 6660000, 'amount_ttc' => 450000]);
        $this->assertSame(6660000.0, $renseignee->montantTotal(),
            'total_a_payer reste la colonne de référence quand elle est renseignée.');

        $vide = new Invoice(['total_a_payer' => 0, 'amount_ttc' => 0]);
        $this->assertSame(0.0, $vide->montantTotal());
    }

    public function test_la_version_sql_couvre_le_zero_pas_seulement_le_nul(): void
    {
        // COALESCE seul ne verrait pas le 0 : il ne traite que NULL.
        $this->assertStringContainsString('total_a_payer > 0', Invoice::SQL_MONTANT_TOTAL);
        $this->assertStringContainsString('COALESCE(invoices.amount_ttc, 0)', Invoice::SQL_MONTANT_TOTAL);
    }

    // ── 1. Le « Restant » déduit les versements ────────────────────

    public function test_le_restant_de_la_liste_deduit_les_versements(): void
    {
        $source = $this->source('app/Http/Controllers/Admin/InvoiceController.php');

        $this->assertMatchesRegularExpression(
            '/\$montantRestantDu\s*=.*?remainingAmount\(\)/s',
            $source,
            'Le KPI « Restant » doit passer par remainingAmount(), comme le reste de l\'application.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\$montantRestantDu\s*=\s*\(clone \$kpiQuery\(\)\)[^;]*->sum\(\x27total_a_payer\x27\)/s',
            $source,
            'Sommer total_a_payer compterait une facture à moitié réglée pour son montant entier.'
        );
    }

    // ── 2. Les avoirs écartés des deux côtés ───────────────────────

    public function test_les_avoirs_sont_ecartes_de_la_liste_des_factures(): void
    {
        $source = $this->source('app/Http/Controllers/Admin/InvoiceController.php');

        // Le CA encaissé et le restant dû de la liste doivent les écarter,
        // comme le fait FinancialDashboardService.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, 'credit_note_for_id'),
            'Les KPI de la liste doivent écarter les avoirs, comme la page Finance.'
        );
    }

    public function test_la_liste_ecarte_aussi_les_brouillons_du_ca_encaisse(): void
    {
        $source = $this->source('app/Http/Controllers/Admin/InvoiceController.php');

        $this->assertStringContainsString(
            "whereNotIn('invoices.status', ['brouillon', 'annulee'])",
            $source,
            'Le CA encaissé excluait seulement les annulées, Finance excluait aussi les brouillons.'
        );
    }

    // ── 3. Plus aucune somme sans repli dans Finance ───────────────

    public function test_les_totaux_de_finance_passent_par_le_repli(): void
    {
        foreach ([
            'app/Services/FinancialDashboardService.php',
            'app/Http/Controllers/Admin/FinanceDashboardController.php',
            'app/Exports/FinanceRecapExport.php',
            'resources/views/admin/finance/partials/creances.blade.php',
            'resources/views/admin/finance/recap-pdf.blade.php',
        ] as $fichier) {
            $source = $this->source($fichier);

            $this->assertDoesNotMatchRegularExpression(
                '/->sum\(\x27total_a_payer\x27\)/',
                $source,
                "{$fichier} : une somme de total_a_payer ignore les factures à 0."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$inv->total_a_payer\s*(\?\?\s*0)?\s*\)?\s*\}\}/',
                $source,
                "{$fichier} : affichage de total_a_payer sans repli."
            );
        }
    }

    // ── L'outil de contrôle ────────────────────────────────────────

    public function test_l_outil_de_controle_est_en_lecture_seule(): void
    {
        $source = $this->source('app/Console/Commands/VerifierFinance.php');

        foreach (['->update(', '->delete(', '->save(', 'DB::statement', '->insert('] as $ecriture) {
            $this->assertStringNotContainsString($ecriture, $source,
                "La commande de contrôle ne doit rien modifier ({$ecriture}).");
        }
    }

    public function test_l_outil_chiffre_les_statuts_a_trancher(): void
    {
        // La question « une facture Générée compte-t-elle comme facturée ? »
        // reste ouverte : l'outil doit en donner le montant pour trancher.
        $source = $this->source('app/Console/Commands/VerifierFinance.php');

        $this->assertStringContainsString("'generee', 'validee'", $source);
        $this->assertStringContainsString('SQL_MONTANT_TOTAL', $source);
    }
}
