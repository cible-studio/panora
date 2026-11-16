<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\TaxeSuiviController;
use App\Models\Commune;
use App\Models\TaxeReglement;
use App\Models\TaxeReprise;
use App\Services\Taxes\TaxeExerciceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Suivi des mairies (2026-11-16) — saisie et annulation des règlements,
 * saisie de reprise, ancien point d'entrée de paiement, et compilation des
 * vues. Les contrôleurs sont appelés directement (pas de rendu de la mise
 * en page admin, qui demande la base complète — MySQL seulement).
 */
class TaxeSuiviEcransTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['taxes.exercice_reprise' => 2026]);

        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('city')->nullable();
            $t->decimal('odp_rate', 10, 2)->nullable(); $t->decimal('tm_rate', 10, 2)->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_16_100000_create_taxe_reglements_table.php'))->up();
        (require base_path('database/migrations/2026_11_16_100100_create_taxe_reprises_table.php'))->up();
        DB::table('communes')->insert(['id' => 1, 'name' => 'COCODY', 'odp_rate' => 5000, 'tm_rate' => 1000]);
    }

    private function suivi(): TaxeSuiviController
    {
        return new TaxeSuiviController(app(TaxeExerciceService::class));
    }

    public function test_un_reglement_s_ajoute_sans_jamais_ecraser_le_precedent(): void
    {
        Storage::fake('local');
        $commune = Commune::find(1);

        foreach ([['2575000', '2026-04-10'], ['2488000', '2026-09-02']] as [$montant, $date]) {
            $req = Request::create('/', 'POST', [
                'exercice' => 2026, 'montant' => $montant, 'paid_at' => $date, 'objet' => 'tm',
                'mode' => 'cheque', 'reference' => 'CHQ-' . $montant,
            ], [], ['quittance' => UploadedFile::fake()->create('quittance.pdf', 20, 'application/pdf')]);
            $reponse = $this->suivi()->storeReglement($req, $commune);
            $this->assertStringContainsString('/admin/taxes/suivi/mairie/1', $reponse->getTargetUrl());
        }

        $lignes = TaxeReglement::orderBy('id')->get();
        $this->assertCount(2, $lignes, 'deux versements sur le même exercice : deux lignes');
        $this->assertSame([2575000, 2488000], $lignes->pluck('montant')->all());
        $this->assertSame('quittance.pdf', $lignes[0]->quittance_nom);
        Storage::disk('local')->assertExists($lignes[0]->quittance_path);
    }

    public function test_un_reglement_refuse_un_montant_nul_une_date_future_et_un_exercice_non_suivi(): void
    {
        $commune = Commune::find(1);
        $cas = [
            ['montant' => 0,      'paid_at' => '2026-04-10', 'exercice' => 2026],
            ['montant' => 1000,   'paid_at' => now()->addDays(3)->toDateString(), 'exercice' => 2026],
            ['montant' => 1000,   'paid_at' => '2025-04-10', 'exercice' => 2025],
        ];
        foreach ($cas as $donnees) {
            try {
                $this->suivi()->storeReglement(Request::create('/', 'POST', $donnees + ['objet' => 'global']), $commune);
                $this->fail('Doit être refusé : ' . json_encode($donnees));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, TaxeReglement::count());
    }

    public function test_une_annulation_exige_un_motif_et_garde_la_ligne(): void
    {
        $r = TaxeReglement::create(['commune_id' => 1, 'exercice' => 2026, 'montant' => 100000, 'paid_at' => '2026-09-03', 'objet' => 'global']);

        try {
            $this->suivi()->annulerReglement(Request::create('/', 'POST', []), $r);
            $this->fail('Une annulation sans motif doit être refusée.');
        } catch (ValidationException) {
        }
        $this->assertNull($r->fresh()->annule_le);

        $this->suivi()->annulerReglement(Request::create('/', 'POST', ['motif' => 'Saisi deux fois']), $r);
        $r->refresh();
        $this->assertNotNull($r->annule_le);
        $this->assertSame('Saisi deux fois', $r->motif_annulation);
        $this->assertSame(1, TaxeReglement::count(), 'la ligne reste');
        $this->assertSame(0, TaxeReglement::valides()->count(), 'mais sort des totaux');
    }

    public function test_la_saisie_de_reprise_ne_vaut_que_pour_l_exercice_de_reprise(): void
    {
        $commune = Commune::find(1);
        $montants = ['arrieres' => 41840000, 'odp' => 0, 'tm_t1' => 1342000, 'tm_t2' => 1233000, 'tm_t3' => 2488000, 'tm_t4' => 0];

        $this->suivi()->saveReprise(Request::create('/', 'PUT', $montants + ['exercice' => 2026]), $commune);
        $this->suivi()->saveReprise(Request::create('/', 'PUT', ['tm_t4' => 500000] + $montants + ['exercice' => 2026]), $commune);
        $this->assertSame(1, TaxeReprise::count(), 'une seule ligne par mairie et par exercice, corrigeable');
        $this->assertSame(500000, TaxeReprise::first()->tm_t4);

        $this->expectException(HttpException::class);
        $this->suivi()->saveReprise(Request::create('/', 'PUT', $montants + ['exercice' => 2027]), $commune);
    }

    public function test_l_ancien_point_d_entree_cree_un_reglement_au_lieu_d_ecraser(): void
    {
        $donnees = [
            'commune_id' => 1, 'period_type' => 'trimestriel', 'period_year' => 2026, 'period_value' => 3,
            'odp_paye' => 296000, 'tm_paye' => 0, 'paid_at' => '2026-08-20', 'reference' => 'Q-1',
        ];
        $ctrl = new TaxController();
        $this->assertTrue($ctrl->recordPayment(Request::create('/', 'POST', $donnees))->getData(true)['ok']);
        $this->assertTrue($ctrl->recordPayment(Request::create('/', 'POST', ['odp_paye' => 100000] + $donnees))->getData(true)['ok']);

        $lignes = TaxeReglement::orderBy('id')->get();
        $this->assertCount(2, $lignes, 'même période, deux versements : deux lignes');
        $this->assertSame([296000, 100000], $lignes->pluck('montant')->all());
        $this->assertSame('odp', $lignes[0]->objet);
        $this->assertSame(2026, $lignes[0]->exercice);

        $refus = $ctrl->recordPayment(Request::create('/', 'POST', ['period_year' => 2025] + $donnees));
        $this->assertSame(422, $refus->getStatusCode(), 'avant la reprise : pas de suivi');
    }

    public function test_les_vues_du_suivi_compilent(): void
    {
        $vues = [
            'admin/taxes/suivi/index.blade.php',
            'admin/taxes/suivi/commune.blade.php',
            'admin/taxes/index.blade.php',
            'admin/rapports/taxes.blade.php',
            'pdf/taxes-releve-mairie.blade.php',
            'pdf/taxes-suivi.blade.php',
        ];
        foreach ($vues as $vue) {
            $php = app('blade.compiler')->compileString(file_get_contents(resource_path('views/' . $vue)));
            $tmp = tempnam(sys_get_temp_dir(), 'vue') . '.php';
            file_put_contents($tmp, $php);
            exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $sortie, $code);
            @unlink($tmp);
            $this->assertSame(0, $code, $vue . ' : ' . implode("\n", $sortie));
            $sortie = [];
        }
    }

    public function test_la_page_taxes_n_ecrit_plus_dans_l_ancienne_table(): void
    {
        $vue = file_get_contents(resource_path('views/admin/taxes/index.blade.php'));
        $this->assertStringNotContainsString('payments.record', $vue, 'la modale qui écrasait les versements est revenue');
        $this->assertStringNotContainsString('openPaymentModal', $vue);
        $this->assertStringContainsString("route('admin.taxes.suivi.index'", $vue);

        $ctrl = file_get_contents(app_path('Http/Controllers/Admin/TaxController.php'));
        $this->assertStringNotContainsString('CommuneTaxPayment::updateOrCreate', $ctrl);
    }
}
