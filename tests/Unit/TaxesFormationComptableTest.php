<?php

namespace Tests\Unit;

use App\Exports\TaxesDetailsExport;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Settings\CommuneController;
use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use App\Models\Tax;
use App\Services\AlertService;
use App\Services\TaxCalculationService;
use App\Services\Taxes\TarifsCommunes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Taxes communales — corrections avant la vidéo des comptables (2026-11-27,
 * audit validé : « ok pour tout ce qui est recommandé »).
 *
 * Les points couverts ailleurs : relevé « à saisir », PDF et Excel du Suivi
 * (TaxeExerciceServiceTest), PDF du Détail en année de reprise
 * (TaxeDetailSelectionTest), jour férié de l'alerte (TaxeExerciceServiceTest),
 * comptes désactivés (FinanceFormationComptableTest).
 */
class TaxesFormationComptableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');

        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('code')->nullable(); $t->string('city')->nullable(); $t->string('region')->nullable();
            $t->unsignedInteger('odp_rate')->default(0); $t->unsignedInteger('tm_rate')->default(0); $t->timestamps();
        });
        Schema::create('commune_tax_rate_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commune_id'); $t->unsignedInteger('odp_rate'); $t->unsignedInteger('tm_rate');
            $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->string('notes', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_18_120000_add_tarif_eclaire_to_communes.php'))->up();

        DB::table('communes')->insert(['id' => 1, 'name' => 'ABENGOUROU', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        DB::table('commune_tax_rate_history')->insert([
            'commune_id' => 1, 'odp_rate' => 1000, 'tm_rate' => 1000, 'effective_from' => '2025-01-01', 'effective_to' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function programmerDecembre(): CommuneTaxRateHistory
    {
        return app(TarifsCommunes::class)->enregistrer(Commune::find(1), Carbon::parse('2026-12-01'), ['odp' => 1500, 'tm' => 1200]);
    }

    private function ligneDeLaPage(): Commune
    {
        return (new CommuneController())->tariffs()->getData()['communes']->firstWhere('id', 1);
    }

    // ── Point 6 : le tarif « courant » ne se périme plus ─────────────────

    public function test_la_page_des_tarifs_montre_le_tarif_en_vigueur(): void
    {
        $this->programmerDecembre();

        $avant = $this->ligneDeLaPage();
        $this->assertSame(1000.0, $avant->tarif_courant['odp']);
        $this->assertSame('2025-01-01', $avant->last_change_date->toDateString(), 'dernier changement : jamais une date future');
        $this->assertSame('01/12/2026', $avant->next_change['date']);

        // Le 1er décembre est passé : rien n'a mis à jour la fiche…
        Carbon::setTestNow('2026-12-05 09:00:00');
        $this->assertSame(1000, (int) Commune::find(1)->odp_rate, 'la colonne de la fiche reste l\'ancienne');

        // … mais la page montre le tarif qui s'applique, comme les calculs.
        $apres = $this->ligneDeLaPage();
        $this->assertSame(['odp' => 1500.0, 'tm' => 1200.0, 'odp_eclaire' => null, 'tm_eclaire' => null], $apres->tarif_courant);
        $this->assertSame(Commune::find(1)->ratesAt('2026-12-05'), ['odp' => 1500.0, 'tm' => 1200.0]);
        $this->assertSame('2026-12-01', $apres->last_change_date->toDateString());
        $this->assertNull($apres->next_change);
    }

    // ── Point 7 : un tarif programmé s'annule ──────────────────────────

    public function test_supprimer_un_tarif_programme_rouvre_l_ancien(): void
    {
        $entree = $this->programmerDecembre();
        $this->assertSame('2026-11-30', CommuneTaxRateHistory::where('effective_from', '2025-01-01')->first()->effective_to->toDateString());

        $vue = file_get_contents(resource_path('views/admin/settings/communes-tariffs.blade.php'));
        $this->assertStringContainsString("route('admin.settings.communes.tariffs.delete'", $vue);
        $this->assertStringContainsString('Supprimer ce tarif programmé', $vue);

        (new CommuneController())->deleteTariffEntry(Commune::find(1), $entree);

        $this->assertNull(CommuneTaxRateHistory::find($entree->id));
        $this->assertNull(CommuneTaxRateHistory::where('effective_from', '2025-01-01')->first()->effective_to, 'l\'ancien tarif reprend sans fin');
        $this->assertSame(1000.0, Commune::find(1)->ratesAt('2027-03-01')['odp']);
    }

    public function test_un_tarif_passe_ne_se_supprime_pas(): void
    {
        $origine = CommuneTaxRateHistory::first();
        (new CommuneController())->deleteTariffEntry(Commune::find(1), $origine);
        $this->assertNotNull(CommuneTaxRateHistory::find($origine->id), 'journal fiscal : le passé reste');
    }

    // ── Point 8 : tout montant entier ──────────────────────────────────

    public function test_les_champs_de_tarif_acceptent_tout_entier(): void
    {
        $vue = file_get_contents(resource_path('views/admin/settings/communes-tariffs.blade.php'));
        $this->assertStringNotContainsString('step="100"', $vue);
        $this->assertSame(4, substr_count($vue, 'min="0" step="1"'));
    }

    // ── Point 16 : notes d'historique lisibles ─────────────────────────

    public function test_les_notes_d_historique_sont_lisibles(): void
    {
        Commune::find(1)->update(['odp_rate' => 1500]);
        $note = CommuneTaxRateHistory::whereDate('effective_from', '2026-11-18')->first()->notes;

        $this->assertStringContainsString('ODP 1 500 F/m²/mois', $note);
        $this->assertStringNotContainsString('odp_rate', $note);
    }

    // ── Point 13 : panneaux = mâts, faces à côté ───────────────────────

    public function test_faces_et_mats_ont_une_seule_definition(): void
    {
        $this->assertSame(1, TaxCalculationService::facesDeLigne([]));
        $this->assertSame(2, TaxCalculationService::facesDeLigne(['faces_count' => 2]), 'double-face fusionné');
        $this->assertSame(3, TaxCalculationService::facesDeLigne(['faces_pied' => 3]), 'pied d\'axe');
        $this->assertSame(6, TaxCalculationService::facesDeLigne(['faces_count' => 2, 'faces_pied' => 3]));

        $totaux = app(TaxCalculationService::class)->summarize(collect([
            ['panel_id' => 1, 'type' => 'odp', 'amount' => 100, 'commune' => 'A', 'faces_count' => 2],
            ['panel_id' => 3, 'type' => 'odp', 'amount' => 100, 'commune' => 'A'],
            ['panel_id' => 1, 'type' => 'tm',  'amount' => 50,  'commune' => 'A'],
            ['panel_id' => 2, 'type' => 'tm',  'amount' => 50,  'commune' => 'A'],
        ]));
        $this->assertSame(2, $totaux['mats_count']);
        $this->assertSame(3, $totaux['faces_count']);

        foreach (['app/Http/Controllers/Admin/TaxController.php', 'app/Services/Taxes/TaxeExerciceService.php'] as $f) {
            $this->assertStringContainsString('TaxCalculationService::facesDeLigne(', file_get_contents(base_path($f)), $f);
        }
    }

    // ── Points 9, 16, 17 : l'Excel du Détail ───────────────────────────

    public function test_l_excel_du_detail_se_recalcule(): void
    {
        $export = new TaxesDetailsExport(collect(), 'novembre 2026');
        $this->assertContains('État actuel du panneau', $export->headings());
        $this->assertNotContains('Statut', $export->headings());

        $ligne = [
            'commune' => 'ABENGOUROU', 'reference' => 'ABG-001', 'name' => 'Carrefour', 'dimensions' => '4x3',
            'surface' => 12.0, 'type' => 'odp', 'statut' => 'occupe', 'client_name' => 'Client essai', 'campaign_name' => 'Campagne essai',
            'campaign_start' => Carbon::parse('2026-03-10'), 'campaign_end' => Carbon::parse('2026-04-09'),
            'period_start' => Carbon::parse('2026-01-01'), 'period_end' => Carbon::parse('2026-12-31'),
            'months' => 12, 'rate' => 1000, 'rate_applied' => 1250, 'amount' => 180000,
        ];
        $odp = $export->map($ligne);
        $this->assertSame('En affichage', $odp[6]);
        $this->assertSame(['01/01/2026', '31/12/2026'], [$odp[9], $odp[10]], 'ODP : la période taxée, pas la dernière campagne');
        $this->assertSame(1250.0, $odp[12], 'le tarif appliqué : 1 250 × 12 m² × 12 mois = 180 000');
        $this->assertSame(180000.0, $odp[12] * $odp[4] * $odp[11]);

        $tm = $export->map(['type' => 'tm'] + $ligne);
        $this->assertSame(['10/03/2026', '09/04/2026'], [$tm[9], $tm[10]], 'TM : les dates de la campagne');

        $this->assertStringContainsString("mb_strtoupper(\$this->periodLabel)", file_get_contents(app_path('Exports/TaxesDetailsExport.php')), '« FÉVRIER », pas « FéVRIER »');
    }

    // ── Point 18 : l'alerte trimestrielle dans le centre d'alertes ─────

    public function test_l_alerte_trimestrielle_arrive_dans_le_centre_d_alertes_du_comptable(): void
    {
        $this->assertArrayHasKey('taxe_due_soon', AlertService::TYPES);
        $this->assertContains('taxe_due_soon', AlertService::broadcastTypesForRole('comptable'));

        $src = file_get_contents(app_path('Console/Commands/NotifyTaxDueSoon.php'));
        $this->assertStringContainsString("AlertService::notify(\n            'taxe_due_soon'", $src);
        $this->assertStringNotContainsString('{$trimestre}e trimestre', $src, '« T1 », pas « 1e trimestre »');
    }

    // ── Point 20 : les anciennes adresses ramènent à la page Taxes ─────

    public function test_les_anciennes_fonctions_ramenent_a_la_page_taxes(): void
    {
        $ctrl = app(TaxController::class);
        $this->app->instance('request', Request::create('/admin/taxes/create'));

        foreach ([
            fn () => $ctrl->create(),
            fn () => $ctrl->store(Request::create('/admin/taxes', 'POST')),
            fn () => $ctrl->edit(new Tax()),
            fn () => $ctrl->destroy(new Tax()),
            fn () => $ctrl->markPaid(Request::create('/', 'PATCH'), new Tax()),
            fn () => $ctrl->exportPdf(Request::create('/admin/taxes/export/pdf')),
        ] as $i => $appel) {
            $r = $appel();
            $this->assertSame(route('admin.taxes.index'), $r->getTargetUrl(), "appel n° {$i}");
        }

        $json = Request::create('/admin/taxes/auto/preview', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame(410, $ctrl->previewAuto($json, app(\App\Services\TaxAutoService::class))->getStatusCode());
    }

    // ── Points 3 et 21 ────────────────────────────────────────────────

    public function test_les_liens_suivent_la_periode_et_les_droits_viennent_de_la_config(): void
    {
        $index = file_get_contents(resource_path('views/admin/taxes/index.blade.php'));
        $this->assertStringContainsString('function majLiens(params)', $index);
        $this->assertStringContainsString('majLiens(params);', $index);

        $routes = file_get_contents(base_path('routes/admin.php'));
        $this->assertStringContainsString("Route::middleware(\\App\\Support\\Acces::middleware('taxes'))->group(function () {\n            Route::get('taxes/auto/preview'", $routes);
        $this->assertStringNotContainsString("Route::middleware('role:admin,mediaplanner,comptable')->group(function () {\n            Route::get('taxes/auto/preview'", $routes);
    }
}
