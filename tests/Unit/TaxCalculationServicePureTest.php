<?php

namespace Tests\Unit;

use App\Services\TaxCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Tests purement unitaires du TaxCalculationService — pas d'accès BDD,
 * exécutés en CI sur SQLite (vs TaxCalculationServiceTest qui exige
 * MySQL pour les scénarios métier panneaux/campagnes).
 *
 * Couvre :
 *   - summarize() émet bien les alias odp_total / tm_total (fix TX-4)
 *   - Le code source contient la formule corrigée ($months / 12)
 *     et plus l'ancienne ×12 parasite (régression TX-1)
 *
 * Ce sont des sentinelles low-cost qui bloquent une régression
 * accidentelle même si MySQL n'est pas dispo sur la machine du dev.
 */
class TaxCalculationServicePureTest extends TestCase
{
    public function test_summarize_emits_odp_total_and_tm_total_aliases(): void
    {
        $service = new TaxCalculationService();
        $lines = collect([
            ['type' => 'odp', 'amount' => 100, 'commune' => 'X', 'panel_id' => 1],
            ['type' => 'odp', 'amount' => 200, 'commune' => 'X', 'panel_id' => 2],
            ['type' => 'tm',  'amount' =>  50, 'commune' => 'X', 'panel_id' => 1],
        ]);

        $s = $service->summarize($lines);

        // Clés historiques (rétro-compat details.blade.php)
        $this->assertEquals(350.0, $s['total']);
        $this->assertEquals(300,   $s['by_type']['odp']);
        $this->assertEquals(50,    $s['by_type']['tm']);
        $this->assertEquals(2,     $s['panels_count']);
        $this->assertEquals(3,     $s['lines_count']);

        // Alias ajoutés pour corriger TX-4 (TaxController::showCommune
        // et computeAnnualTotalDue les lisent — sans eux la matrice
        // mensuelle de la fiche commune affichait 0 partout).
        $this->assertArrayHasKey('odp_total', $s);
        $this->assertArrayHasKey('tm_total',  $s);
        $this->assertEquals(300.0, $s['odp_total']);
        $this->assertEquals(50.0,  $s['tm_total']);
    }

    public function test_amount_formula_uses_months_divided_by_12_not_months(): void
    {
        // Canari statique sur la source du service : si demain quelqu'un
        // remet l'ancienne formule ×12, ce test pète.
        $source = file_get_contents(__DIR__ . '/../../app/Services/TaxCalculationService.php');
        $this->assertStringContainsString(
            '$amount = round($unitRate * $surface * ($months / 12), 2);',
            $source,
            'BUG TX-1 EN COURS DE RÉGRESSION : la formule generateLines() ' .
            'doit utiliser ($months / 12) et pas $months tout court.'
        );
        $this->assertStringNotContainsString(
            '$amount = round($unitRate * $surface * $months, 2);',
            $source,
            'BUG TX-1 EN COURS DE RÉGRESSION : l\'ancienne formule ×12 est revenue.'
        );
    }

    /**
     * Hotfix B canari TX-OCC-1 : TaxController::calcul DOIT consulter
     * l'occupation effective panneau-par-panneau pour la TM. Si demain
     * quelqu'un remet le calcul TM forfaitaire (sans $moisOccByPanel),
     * ce test pète immédiatement — y compris sur SQLite sans MySQL.
     */
    public function test_tax_controller_calcul_uses_occupation_per_panel_for_tm(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Admin/TaxController.php');

        // 1) On doit calculer le moisOccByPanel en bulk avant la map.
        $this->assertStringContainsString(
            '$moisOccByPanel',
            $source,
            'BUG TX-OCC-1 EN COURS DE RÉGRESSION : TaxController::calcul ' .
            'doit pré-calculer une map panel_id → mois occupés via ' .
            '$moisOccByPanel.'
        );

        // 2) Et la TM doit boucler par panneau plutôt que forfaitaire.
        $this->assertStringContainsString(
            '$moisOcc = $moisOccByPanel[$p->id] ?? 0;',
            $source,
            'BUG TX-OCC-1 EN COURS DE RÉGRESSION : la TM doit lire ' .
            'l\'occupation par panneau au lieu d\'appliquer tarif×m²×(nbMois/12).'
        );

        // 3) L'ancienne formule forfaitaire TM ne doit plus exister.
        $this->assertStringNotContainsString(
            '$tm  = round($tmRate  * $m2 * $qty * ($nbMois / 12));',
            $source,
            'BUG TX-OCC-1 EN COURS DE RÉGRESSION : la TM forfaitaire ' .
            '(facturée même sur panneau libre, ex. BOUAFLE 1 667 FCFA) ' .
            'est revenue. Doit être un calcul par panneau avec occupation.'
        );
    }
}
