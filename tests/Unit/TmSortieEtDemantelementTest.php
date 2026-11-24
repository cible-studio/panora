<?php

namespace Tests\Unit;

use App\Models\CampaignPanelMouvement;
use App\Models\Panel;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TM d'un panneau sorti d'une campagne, et démantèlement daté
 * (2026-11-24, règles validées par l'utilisateur).
 *
 *   1. Un panneau retiré d'une campagne garde sa TM, du début de la campagne
 *      (ou de son ajout) jusqu'à sa date RÉELLE de sortie. Avant : retiré =
 *      détaché = plus de TM du tout, même pour les mois affichés.
 *   2. Motif « démantelé » : le panneau quitte aussi le parc à cette date ;
 *      l'ODP s'arrête là (soft delete daté), et la TM aussi.
 *   3. Cas réel : YOPCAIS-01A, campagne du 01/01 au 31/12/2026, démantelé le
 *      27/07, retiré de la campagne le 24/11. Données fictives ici.
 */
class TmSortieEtDemantelementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 09:00:00');

        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('city')->nullable();
            $t->decimal('odp_rate', 10, 2)->nullable(); $t->decimal('tm_rate', 10, 2)->nullable(); $t->timestamps();
        });
        Schema::create('commune_tax_rate_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commune_id'); $t->decimal('odp_rate', 10, 2)->default(0);
            $t->decimal('tm_rate', 10, 2)->default(0); $t->date('effective_from'); $t->date('effective_to')->nullable();
            $t->string('notes', 500)->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->decimal('width', 8, 2)->nullable(); $t->decimal('height', 8, 2)->nullable();
            $t->decimal('surface', 8, 2)->nullable(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable();
            $t->unsignedBigInteger('commune_id')->nullable(); $t->unsignedBigInteger('format_id')->nullable();
            $t->string('status')->default('libre'); $t->unsignedBigInteger('category_id')->nullable();
            $t->date('odp_start_date')->nullable();
            $t->unsignedBigInteger('axe_id')->nullable(); $t->boolean('est_axe')->default(false);
            $t->unsignedSmallInteger('pied_numero')->nullable(); $t->unsignedSmallInteger('faces_par_pied')->nullable();
            $t->boolean('is_lit')->default(false);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable(); $t->string('status');
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('campaign_panel_mouvements', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('reference', 100)->nullable();
            $t->string('sens', 10); $t->string('motif', 40)->nullable(); $t->text('note')->nullable();
            $t->string('source', 20)->default('manuel'); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_24_120000_add_effet_le_to_campaign_panel_mouvements.php'))->up();
        (require base_path('database/migrations/2026_11_18_120000_add_tarif_eclaire_to_communes.php'))->up();

        DB::table('communes')->insert(['id' => 1, 'name' => 'COMMUNE ESSAI', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        DB::table('panel_formats')->insert(['id' => 1, 'name' => '36m²', 'width' => 6, 'height' => 6, 'surface' => 36]);
        DB::table('panels')->insert([
            ['id' => 1, 'reference' => 'ESS-01A', 'name' => 'Face A', 'commune_id' => 1, 'format_id' => 1, 'odp_start_date' => '2025-01-01'],
            ['id' => 2, 'reference' => 'ESS-02A', 'name' => 'Autre', 'commune_id' => 1, 'format_id' => 1, 'odp_start_date' => '2025-01-01'],
        ]);
        DB::table('clients')->insert(['id' => 1, 'name' => 'CLIENT ESSAI']);
        DB::table('campaigns')->insert(['id' => 10, 'name' => 'Campagne 2026', 'client_id' => 1, 'status' => 'actif',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        // ESS-02A reste dans la campagne ; ESS-01A en a été retiré.
        DB::table('campaign_panels')->insert(['campaign_id' => 10, 'panel_id' => 2, 'type' => 'interne']);
    }

    private function tm(int $panelId, string $type, int $valeur, int $annee = 2026, ?int $fin = null): int
    {
        return (int) app(TaxCalculationService::class)->generateLines($type, $valeur, $annee, ['inclure_lumipub' => true], $fin)
            ->where('type', 'tm')->where('panel_id', $panelId)->sum('months');
    }

    private function retirer(?string $effetLe, string $cree = '2026-11-24 08:55:00'): void
    {
        DB::table('campaign_panel_mouvements')->insert([
            'campaign_id' => 10, 'panel_id' => 1, 'reference' => 'ESS-01A', 'sens' => CampaignPanelMouvement::RETRAIT,
            'motif' => CampaignPanelMouvement::MOTIF_DEMANTELE, 'source' => 'manuel',
            'effet_le' => $effetLe, 'created_at' => $cree, 'updated_at' => $cree,
        ]);
    }

    public function test_un_panneau_retire_garde_sa_tm_jusqu_a_sa_date_reelle_de_sortie(): void
    {
        $this->retirer('2026-07-27');

        $this->assertSame(7, $this->tm(1, 'personnalise', 1, 2026, 12), 'janvier à juillet (27/07 entamé)');
        $this->assertSame(1, $this->tm(1, 'trimestriel', 3), 'T3 : juillet seulement (sorti le 27/07)');
        $this->assertSame(0, $this->tm(1, 'mensuel', 11), 'rien en novembre');
    }

    public function test_sans_date_reelle_la_tm_court_jusqu_au_jour_du_retrait(): void
    {
        $this->retirer(null, '2026-11-24 08:55:00');

        $this->assertSame(11, $this->tm(1, 'personnalise', 1, 2026, 12), 'janvier à novembre');
        $this->assertSame(0, $this->tm(1, 'mensuel', 12), 'plus rien en décembre');
    }

    /** Le remplaçant ajouté en cours de campagne : TM à partir de son entrée réelle. */
    public function test_un_remplacant_compte_depuis_son_entree_reelle(): void
    {
        DB::table('campaign_panel_mouvements')->insert([
            'campaign_id' => 10, 'panel_id' => 2, 'reference' => 'ESS-02A', 'sens' => CampaignPanelMouvement::AJOUT,
            'motif' => CampaignPanelMouvement::MOTIF_AJOUT_MANUEL, 'source' => 'manuel',
            'effet_le' => '2026-08-01', 'created_at' => '2026-11-24 09:00:00', 'updated_at' => '2026-11-24 09:00:00',
        ]);

        $this->assertSame(0, $this->tm(2, 'mensuel', 1), 'pas en janvier');
        $this->assertSame(5, $this->tm(2, 'personnalise', 1, 2026, 12), 'août à décembre');
    }

    public function test_un_panneau_encore_dans_la_campagne_ne_change_pas(): void
    {
        $this->assertSame(12, $this->tm(2, 'annuel', 0));
    }

    public function test_le_demantelement_date_arrete_l_odp_et_la_tm(): void
    {
        Panel::withTrashed()->find(1)->demantelerLe(Carbon::parse('2026-07-27'));
        $this->assertSame('2026-07-27', Panel::withTrashed()->find(1)->deleted_at->toDateString());

        $calc = app(TaxCalculationService::class);
        $odp = fn ($type, $v) => (int) $calc->generateLines($type, $v, 2026, ['inclure_lumipub' => true])
            ->where('type', 'odp')->where('panel_id', 1)->sum('months');
        $this->assertSame(1, $odp('mensuel', 7), 'ODP de juillet due (il était debout)');
        $this->assertSame(0, $odp('mensuel', 8), 'plus d\'ODP en août');
        $this->assertSame(0, $odp('mensuel', 11), 'ni en novembre');
    }

    public function test_les_intervalles_de_presence(): void
    {
        $d = Carbon::parse('2026-01-01');
        $f = Carbon::parse('2026-12-31');
        $m = fn ($sens, $date, $motif = null, $source = 'manuel') => ['sens' => $sens, 'motif' => $motif, 'source' => $source, 'date' => Carbon::parse($date)];
        $txt = fn (array $iv) => array_map(fn ($i) => $i[0]->toDateString() . '→' . $i[1]->toDateString(), $iv);

        $this->assertSame(['2026-01-01→2026-12-31'], $txt(TaxCalculationService::intervallesPresence($d, $f, [], true)));
        $this->assertSame([], TaxCalculationService::intervallesPresence($d, $f, [], false));
        $this->assertSame(['2026-01-01→2026-07-27'], $txt(TaxCalculationService::intervallesPresence($d, $f, [$m('retrait', '2026-07-27')], false)));
        $this->assertSame(['2026-03-15→2026-12-31'], $txt(TaxCalculationService::intervallesPresence($d, $f, [$m('ajout', '2026-03-15', 'ajout_manuel')], true)), 'ajouté en cours de campagne');
        $this->assertSame(['2026-01-01→2026-05-10'], $txt(TaxCalculationService::intervallesPresence($d, $f,
            [$m('ajout', '2026-02-01', 'creation_campagne', 'creation'), $m('retrait', '2026-05-10')], false)), 'ajouté à la création : depuis le début');
        $this->assertSame(['2026-01-01→2026-03-31', '2026-06-01→2026-12-31'], $txt(TaxCalculationService::intervallesPresence($d, $f,
            [$m('retrait', '2026-03-31'), $m('ajout', '2026-06-01', 'ajout_manuel')], true)), 'sorti puis revenu');
        $this->assertSame(['2026-01-01→2026-07-27'], $txt(TaxCalculationService::intervallesPresence($d, $f, [], true, Carbon::parse('2026-07-27 23:59:59'))), 'démantelé');
    }

    /** Le retrait et la suppression demandent la date réelle ; la case « démantelé » suit le motif. */
    public function test_les_ecrans_demandent_la_date_reelle(): void
    {
        $fiche = File::get(resource_path('views/admin/campaigns/show.blade.php'));
        $this->assertStringContainsString('name="sortie_le"', $fiche);
        $this->assertStringContainsString('name="demanteler"', $fiche);
        $this->assertStringContainsString("'sortie_le'  => ['nullable', 'date', 'before_or_equal:today'", File::get(app_path('Http/Controllers/Admin/CampaignController.php')));

        // Symétrique : la date réelle d'entrée d'un panneau ajouté (remplaçant).
        $this->assertStringContainsString('name="entree_le"', $fiche);
        $this->assertStringContainsString('$entreeLe', File::get(app_path('Http/Controllers/Admin/CampaignController.php')));
        $this->assertStringContainsString('addPanels($newCampaign, $internalIds, null, $newCampaign->start_date)', File::get(app_path('Http/Controllers/Admin/CampaignController.php')), 'duplication : depuis le début');

        $this->assertStringContainsString('name="demantele_le"', File::get(resource_path('views/admin/panels/index.blade.php')));
        $this->assertStringNotContainsString("confirm('Supprimer ce panneau ?')", File::get(resource_path('views/admin/panels/partials/table-rows.blade.php')));
        $this->assertStringContainsString('$panel->demantelerLe($le);', File::get(app_path('Http/Controllers/Admin/PanelController.php')));
    }
}
