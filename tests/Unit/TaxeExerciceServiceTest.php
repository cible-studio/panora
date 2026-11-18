<?php

namespace Tests\Unit;

use App\Models\Commune;
use App\Models\TaxeReglement;
use App\Models\TaxeReprise;
use App\Services\TaxCalculationService;
use App\Services\Taxes\TaxeExerciceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Suivi des mairies — l'exercice fiscal d'une mairie (2026-11-16).
 *
 * Règles validées par écrit le 2026-11-16 : la fiche de la direction
 * (arriérés + ODP + TM T1–T4 − règlements = solde), automatisée ;
 * panneaux et tarifs de Panora ; TM de toutes les campagnes, chaque mois
 * dans le trimestre où il commence ; arriérés reportés ; règlement jamais
 * effacé (annulation) ; prévu hors solde ; 2026 = exercice de reprise
 * (dû = ce que la mairie a réclamé, calcul Panora en contrôle).
 *
 * Scénario : Cocody (ODP 5 000, TM 1 000 F/m²/mois) et Daloa (1 000 / 1 000),
 * un panneau de 12 m² chacun, présents depuis toujours.
 *   · Cocody, panneau P1 : campagne terminée 01/07→31/12/2026, puis en 2027
 *     deux campagnes — janvier→mars et juin→décembre.
 *   · Daloa, panneau P2 : une campagne PLANIFIÉE octobre→décembre 2027.
 *
 * Base SQLite réduite (les migrations historiques ne passent pas sous
 * SQLite) ; les deux migrations du suivi, elles, sont jouées telles quelles.
 */
class TaxeExerciceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['taxes.exercice_reprise' => 2026]);

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
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable(); $t->string('status');
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->string('type')->default('interne'); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_16_100000_create_taxe_reglements_table.php'))->up();
        (require base_path('database/migrations/2026_11_16_100100_create_taxe_reprises_table.php'))->up();
        (require base_path('database/migrations/2026_11_18_120000_add_tarif_eclaire_to_communes.php'))->up();

        DB::table('communes')->insert([
            ['id' => 1, 'name' => 'COCODY', 'odp_rate' => 5000, 'tm_rate' => 1000],
            ['id' => 2, 'name' => 'DALOA',  'odp_rate' => 1000, 'tm_rate' => 1000],
        ]);
        DB::table('panel_formats')->insert(['id' => 1, 'name' => '12m²', 'width' => 4, 'height' => 3, 'surface' => 12]);
        DB::table('panels')->insert([
            ['id' => 1, 'reference' => 'CDY-001', 'name' => 'Riviera', 'commune_id' => 1, 'format_id' => 1],
            ['id' => 2, 'reference' => 'DLA-001', 'name' => 'Centre',  'commune_id' => 2, 'format_id' => 1],
        ]);
        DB::table('clients')->insert([['id' => 1, 'name' => 'Client A'], ['id' => 2, 'name' => 'Client B']]);
        DB::table('campaigns')->insert([
            ['id' => 10, 'name' => 'A 2026',     'client_id' => 1, 'status' => 'termine',  'start_date' => '2026-07-01', 'end_date' => '2026-12-31'],
            ['id' => 11, 'name' => 'A T1 2027',  'client_id' => 1, 'status' => 'termine',  'start_date' => '2027-01-01', 'end_date' => '2027-03-31'],
            ['id' => 12, 'name' => 'B 2027',     'client_id' => 2, 'status' => 'actif',    'start_date' => '2027-06-01', 'end_date' => '2027-12-31'],
            ['id' => 13, 'name' => 'Planifiée',  'client_id' => 2, 'status' => 'planifie', 'start_date' => '2027-10-01', 'end_date' => '2027-12-31'],
        ]);
        DB::table('campaign_panels')->insert([
            ['campaign_id' => 10, 'panel_id' => 1, 'type' => 'interne'],
            ['campaign_id' => 11, 'panel_id' => 1, 'type' => 'interne'],
            ['campaign_id' => 12, 'panel_id' => 1, 'type' => 'interne'],
            ['campaign_id' => 13, 'panel_id' => 2, 'type' => 'interne'],
        ]);

        // Exercice de reprise : ce que Cocody a réclamé pour 2026 (chiffres
        // de la fiche de la direction), et ses règlements.
        TaxeReprise::create([
            'commune_id' => 1, 'exercice' => 2026, 'arrieres' => 41840000, 'odp' => 0,
            'tm_t1' => 1342000, 'tm_t2' => 1233000, 'tm_t3' => 2488000, 'tm_t4' => 0,
        ]);
        TaxeReglement::create(['commune_id' => 1, 'exercice' => 2026, 'montant' => 2575000, 'paid_at' => '2026-04-10', 'objet' => 'tm']);
        TaxeReglement::create(['commune_id' => 1, 'exercice' => 2026, 'montant' => 2488000, 'paid_at' => '2026-09-02', 'objet' => 'tm']);
        TaxeReglement::create(['commune_id' => 1, 'exercice' => 2026, 'montant' => 100000,  'paid_at' => '2026-09-03', 'objet' => 'global'])
            ->annuler('Saisi deux fois', null);
        // Daloa a payé en 2026 sans montant réclamé saisi : trop-perçu.
        TaxeReglement::create(['commune_id' => 2, 'exercice' => 2026, 'montant' => 50000, 'paid_at' => '2026-05-05', 'objet' => 'global']);
        // 2027 : un versement de Cocody.
        TaxeReglement::create(['commune_id' => 1, 'exercice' => 2027, 'montant' => 1000000, 'paid_at' => '2027-02-15', 'objet' => 'global']);

        Carbon::setTestNow(Carbon::create(2027, 5, 10, 9));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): TaxeExerciceService
    {
        return app(TaxeExerciceService::class);
    }

    public function test_exercice_de_reprise_le_du_est_ce_que_la_mairie_a_reclame(): void
    {
        $cocody = $this->service()->exercice(Commune::find(1), 2026);

        $this->assertSame('reprise', $cocody['mode']);
        $this->assertSame(41840000, $cocody['arrieres']);
        $this->assertSame([1 => 1342000, 2 => 1233000, 3 => 2488000, 4 => 0], $cocody['tm_trim']);
        $this->assertSame(46903000, $cocody['total_du']);
        $this->assertSame(5063000, $cocody['total_paye'], 'le règlement annulé sort des totaux');
        $this->assertSame(2, $cocody['nb_reglements']);
        $this->assertSame(41840000, $cocody['solde']);
        $this->assertSame('partiel', $cocody['statut']);

        // Le calcul de Panora reste affiché, pour contrôle, hors solde :
        // ODP 5 000 × 12 m² × 12 mois + TM 1 000 × 12 m² × 6 mois.
        $this->assertSame(720000, $cocody['controle_odp']);
        $this->assertSame(72000, $cocody['controle_tm']);
        $this->assertFalse($cocody['a_saisir']);
    }

    public function test_une_mairie_sans_montant_reclame_est_signalee(): void
    {
        $daloa = $this->service()->exercice(Commune::find(2), 2026);

        $this->assertTrue($daloa['a_saisir'], 'panneaux et versement mais rien de réclamé saisi');
        $this->assertSame(0, $daloa['total_du']);
        $this->assertSame(-50000, $daloa['solde']);
        $this->assertSame('credit', $daloa['statut']);
    }

    public function test_a_partir_de_2027_le_du_est_le_calcul_de_panora(): void
    {
        $cocody = $this->service()->exercice(Commune::find(1), 2027);

        $this->assertSame('calcul', $cocody['mode']);
        $this->assertSame(41840000, $cocody['arrieres'], 'arriérés = solde 2026');
        $this->assertSame([1 => 180000, 2 => 180000, 3 => 180000, 4 => 180000], $cocody['odp_trim']);
        $this->assertSame(720000, $cocody['odp']);

        // TM : les DEUX campagnes 2027 (3 mois + 7 mois), chaque mois dans
        // son trimestre — juin seul au T2.
        $this->assertSame([1 => 36000, 2 => 12000, 3 => 36000, 4 => 36000], $cocody['tm_trim']);
        $this->assertSame(120000, $cocody['tm']);

        $this->assertSame(41840000 + 720000 + 120000, $cocody['total_du']);
        $this->assertSame(1000000, $cocody['total_paye']);
        $this->assertSame(41680000, $cocody['solde']);

        // Exigible au 10/05/2027 (T2 commencé) : arriérés + T1 + T2 − payé.
        $this->assertSame(41840000 + 360000 + 48000 - 1000000, $cocody['exigible']);

        $this->assertSame(['12' => ['surface' => 12.0, 'panneaux' => 1, 'faces' => 1]], $cocody['base']);
    }

    /**
     * Panneau éclairé (2026-11-18) : le moteur prend le tarif « éclairé » de
     * sa commune, panneau par panneau ; les autres gardent le tarif normal.
     */
    public function test_un_panneau_eclaire_paie_le_tarif_eclaire_de_sa_commune(): void
    {
        app(\App\Services\Taxes\TarifsCommunes::class)->enregistrer(Commune::find(1), Carbon::create(2027, 1, 1),
            ['odp' => 5000, 'tm' => 1000, 'odp_eclaire' => 7000, 'tm_eclaire' => 2000], 'Tarif de test');
        \Illuminate\Support\Facades\Cache::flush();
        $normal = $this->service()->exercice(Commune::find(1), 2027);
        $this->assertSame([1 => 180000, 2 => 180000, 3 => 180000, 4 => 180000], $normal['odp_trim'], 'non éclairé : inchangé');

        DB::table('panels')->where('id', 1)->update(['is_lit' => true]);
        // Service à portée de requête (AppServiceProvider) : on en repart d'un neuf.
        \Illuminate\Support\Facades\Cache::flush();
        $this->app->forgetScopedInstances();
        $eclaire = app(TaxeExerciceService::class)->exercice(Commune::find(1), 2027);

        $this->assertSame([1 => 252000, 2 => 252000, 3 => 252000, 4 => 252000], $eclaire['odp_trim'], '7 000 × 12 m² × 3 mois');
        $this->assertSame([1 => 72000, 2 => 24000, 3 => 72000, 4 => 72000], $eclaire['tm_trim'], '2 000 × 12 m² × mois de campagne');
    }

    public function test_un_trop_percu_vient_en_deduction_de_l_exercice_suivant(): void
    {
        $daloa = $this->service()->exercice(Commune::find(2), 2027);

        $this->assertSame(-50000, $daloa['arrieres']);
        $this->assertSame(144000, $daloa['odp']);
        $this->assertSame(0, $daloa['tm'], 'la campagne planifiée ne compte pas dans le dû');
        $this->assertSame(94000, $daloa['solde']);
        $this->assertSame(-50000 + 72000, $daloa['exigible']);
    }

    public function test_le_prevu_reprend_les_campagnes_planifiees_hors_solde(): void
    {
        $daloa = $this->service()->exercice(Commune::find(2), 2027);

        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 36000], $daloa['prevu_trim']);
        $this->assertSame(36000, $daloa['prevu_tm']);
    }

    public function test_les_totaux_et_les_zones(): void
    {
        $s = $this->service();
        $lignes = $s->exercices(2027);
        $zones = $s->parZone($lignes);

        $this->assertSame([1], $zones['abidjan']->keys()->all(), 'Cocody est à Abidjan');
        $this->assertSame([2], $zones['interieur']->keys()->all());

        $t = $s->totaux($lignes);
        $this->assertSame(2, $t['mairies']);
        $this->assertSame(41680000 + 94000, $t['solde']);
        $this->assertSame(41680000 + 94000, $t['reste']);
        $this->assertSame(36000, $t['prevu_tm']);
    }

    public function test_le_cache_du_calcul_suit_les_changements_de_campagne(): void
    {
        $neuf = fn () => new TaxeExerciceService(app(TaxCalculationService::class));

        $this->assertSame(120000, $neuf()->exercice(Commune::find(1), 2027)['tm']);

        // La campagne de juin s'arrête fin septembre : 4 mois au lieu de 7.
        DB::table('campaigns')->where('id', 12)->update(['end_date' => '2027-09-30', 'updated_at' => now()->addMinute()]);

        $this->assertSame(1000 * 12 * (3 + 4), $neuf()->exercice(Commune::find(1), 2027)['tm'],
            'le calcul en cache doit être refait quand une campagne change');
    }

    /**
     * La page Taxes (/admin/taxes, endpoint calcul) : TM du moteur unique,
     * payé / solde / statut de l'exercice (mêmes chiffres que le suivi).
     */
    public function test_la_page_taxes_lit_le_meme_calcul_et_le_meme_exercice(): void
    {
        $ctrl = new \App\Http\Controllers\Admin\TaxController();
        $calc = app(TaxCalculationService::class);

        $annuel = $ctrl->calcul(\Illuminate\Http\Request::create('/', 'GET', [
            'period_type' => 'annuel', 'period_year' => 2027, 'period_value' => 0, 'inclure_lumipub' => 1,
        ]), $calc)->getData(true);
        $cocody = collect($annuel['communes'])->firstWhere('commune_id', 1);

        $this->assertEquals(720000, $cocody['odp_theorique']);
        $this->assertEquals(120000, $cocody['tm_theorique'], 'les deux campagnes 2027, comme le suivi');
        $this->assertEquals(1000000, $cocody['total_paye']);
        $this->assertEquals(41680000, $cocody['solde'], 'solde de l\'exercice, arriérés compris');
        $this->assertSame('partiel', $cocody['statut_year']);
        $this->assertEquals(41680000 + 94000, $annuel['kpi']['solde_total']);
        $this->assertEquals(1000000, collect($annuel['kpi']['payments_evolution'])->firstWhere('mois', 2)['total']);

        $juin = $ctrl->calcul(\Illuminate\Http\Request::create('/', 'GET', [
            'period_type' => 'mensuel', 'period_year' => 2027, 'period_value' => 6,
        ]), $calc)->getData(true);
        $this->assertEquals(12000, collect($juin['communes'])->firstWhere('commune_id', 1)['tm_theorique'],
            'juin : le 1er mois de la campagne de juin');
    }

    /**
     * Décision écrite du 2026-11-16 : pieds Lumipub « décochés » partout —
     * hors du dû du suivi, comme sur la page Taxes par défaut. La ligne
     * d'axe n'est jamais taxée ; le réglage config/taxes.php les réintègre.
     */
    public function test_les_pieds_lumipub_sont_hors_du_du(): void
    {
        DB::table('panels')->insert([
            ['id' => 90, 'reference' => 'CDYLUP-009',     'name' => 'Axe (1P x 1F)', 'commune_id' => 1, 'format_id' => 1, 'est_axe' => true,  'axe_id' => null],
            ['id' => 91, 'reference' => 'CDYLUP-009-P01', 'name' => 'Pied 1/1',      'commune_id' => 1, 'format_id' => 1, 'est_axe' => false, 'axe_id' => 90],
        ]);
        $neuf = fn () => new TaxeExerciceService(app(TaxCalculationService::class));

        $this->assertFalse(TaxeExerciceService::inclureLumipub());
        $this->assertSame(720000, $neuf()->exercice(Commune::find(1), 2027)['odp'], 'ni le pied ni la ligne d\'axe');

        config(['taxes.inclure_lumipub' => true]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(720000 * 2, $neuf()->exercice(Commune::find(1), 2027)['odp'], 'le pied, jamais la ligne d\'axe');
    }

    public function test_l_alerte_part_le_premier_jour_ouvre_du_trimestre(): void
    {
        $premier = fn (string $d) => \App\Console\Commands\NotifyTaxDueSoon::premierJourOuvreDuTrimestre(Carbon::parse($d))->toDateString();

        $this->assertSame('2027-01-01', $premier('2027-02-14'), 'le 1er janvier 2027 est un vendredi');
        $this->assertSame('2027-04-01', $premier('2027-05-10'));
        $this->assertSame('2026-10-01', $premier('2026-11-16'), 'le 1er octobre 2026 est un jeudi');
        $this->assertSame('2028-04-03', $premier('2028-06-30'), 'le 1er avril 2028 est un samedi : lundi 3');
    }

    public function test_un_exercice_anterieur_a_la_reprise_n_est_pas_suivi(): void
    {
        $this->assertFalse($this->service()->estSuivi(2025));
        $this->assertTrue($this->service()->exercices(2025)->isEmpty());
    }

    public function test_la_tm_annuelle_du_moteur_compte_toutes_les_campagnes(): void
    {
        $calc = app(TaxCalculationService::class);
        $tm = fn ($lignes) => (int) $lignes->where('type', 'tm')->where('commune_id', 1)->sum('amount');

        $annuel = $tm($calc->generateLines('annuel', 0, 2027, ['inclure_lumipub' => true]));
        $parMois = 0;
        for ($m = 1; $m <= 12; $m++) {
            $parMois += $tm($calc->generateLines('mensuel', $m, 2027, ['inclure_lumipub' => true]));
        }

        // Avant le 2026-11-16 : 84 000 en annuel (seule la campagne la plus
        // récente comptait).
        $this->assertSame(120000, $annuel);
        $this->assertSame($annuel, $parMois, '12 mois = l\'année');
    }

    /**
     * Les écrans s'affichent réellement avec ces données : tableau (trois
     * onglets, deux exercices), fiche de mairie (reprise et calcul), relevé
     * PDF, PDF de l'exercice et Excel. La mise en page admin est remplacée
     * par un cadre minimal (elle demande la base complète).
     */
    public function test_les_ecrans_du_suivi_s_affichent(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        $cadre = sys_get_temp_dir() . '/panora-cadre-' . uniqid();
        // components/admin-layout inclut layouts.admin : c'est lui qu'on remplace.
        mkdir($cadre . '/layouts', 0777, true);
        file_put_contents($cadre . '/layouts/admin.blade.php',
            '<main>{{ $topbarLeft ?? "" }}{{ $topbarActions ?? "" }}{{ $slot }}</main>');
        app('view')->getFinder()->prependLocation($cadre);
        // Partagé par le middleware web en temps normal.
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $ctrl = new \App\Http\Controllers\Admin\TaxeSuiviController($this->service());

        foreach ([2026, 2027] as $exercice) {
            foreach (['abidjan', 'interieur', 'tout'] as $zone) {
                $html = $ctrl->index(\Illuminate\Http\Request::create('/', 'GET', ['exercice' => $exercice, 'zone' => $zone]))->render();
                $this->assertStringContainsString('Suivi des mairies', $html, "$exercice / $zone");
            }
            $fiche = $ctrl->commune(\Illuminate\Http\Request::create('/', 'GET', ['exercice' => $exercice]), Commune::find(1))->render();
            $this->assertStringContainsString('COCODY', $fiche);
            $this->assertStringContainsString($exercice === 2026 ? 'Ce que la mairie a réclamé' : 'À venir', $fiche);
        }

        // Libellés simples (2026-11-16). En 2026, Daloa a un versement mais
        // rien de réclamé saisi : « Réclamé à saisir », pas « Payé en trop ».
        $tableau2026 = $ctrl->index(\Illuminate\Http\Request::create('/', 'GET', ['exercice' => 2026, 'zone' => 'interieur']))->render();
        $this->assertStringContainsString('Réclamé à saisir', $tableau2026);
        $this->assertStringNotContainsString('Payé en trop', $tableau2026);
        $this->assertStringContainsString('Dû à ce jour', $tableau2026);

        $tableau2027 = $ctrl->index(\Illuminate\Http\Request::create('/', 'GET', ['exercice' => 2027, 'zone' => 'abidjan']))->render();
        $this->assertStringContainsString('41 680 000', $tableau2027, 'solde 2027 de Cocody');

        // Les deux PDF : leur HTML (DomPDF lui-même, avec ses ressources
        // distantes, rend la suite de tests interminable sur ce poste).
        $s = $this->service();
        $releve = view('pdf.taxes-releve-mairie', [
            'commune' => Commune::find(1), 'exercice' => 2026, 'reprise' => true,
            'ligne' => $s->exercice(Commune::find(1), 2026),
            'reglements' => TaxeReglement::where('commune_id', 1)->where('exercice', 2026)->get(),
        ])->render();
        $this->assertStringContainsString('SOLDE RESTANT DÛ', $releve);
        $this->assertStringContainsString('41 840 000', $releve);
        $this->assertStringNotContainsString('Saisi deux fois', $releve, 'le règlement annulé ne figure pas au relevé');

        $zones = $s->parZone($s->exercices(2027));
        $tableauPdf = view('pdf.taxes-suivi', [
            'exercice' => 2027, 'reprise' => false, 'zones' => $zones,
            'totaux' => ['abidjan' => $s->totaux($zones['abidjan']), 'interieur' => $s->totaux($zones['interieur']), 'tout' => $s->totaux($s->exercices(2027))],
        ])->render();
        $this->assertStringContainsString('ABIDJAN ET BANLIEUE', $tableauPdf);
        $this->assertStringContainsString('DALOA', $tableauPdf);

        // Mise en page pour la DG (18/11), vérifiée sur le vrai rendu DomPDF :
        // les deux zones s'alignent, un TOTAL n'arrive jamais seul en haut
        // de page, la note ne se coupe pas.
        $this->assertSame(2, substr_count($tableauPdf, '<table class="tdoc-table tdoc-suivi">'), 'une table par zone');
        $this->assertSame(2, substr_count($tableauPdf, '<th style="width:15%">Mairie</th>'), 'mêmes largeurs dans les deux zones');
        $this->assertStringContainsString('.tdoc-suivi tr.tdoc-total { page-break-before:avoid; }', $tableauPdf);
        $this->assertStringContainsString('class="tdoc-note tdoc-suivi-fin"', $tableauPdf);
        $this->assertStringContainsString('.tdoc-table tr.tdoc-total { page-break-before:avoid; }', $releve, 'relevé sur deux pages : total jamais seul');

        // L'Excel au format de la fiche de la direction.
        $feuille = (new \App\Exports\Sheets\TaxesSuiviZoneSheet(2027, 'ABIDJAN', 'ABIDJAN ET BANLIEUE', $zones['abidjan'], false))->array();
        $this->assertStringContainsString('EXERCICE 2027', $feuille[0][0]);
        $this->assertSame('MAIRIES', $feuille[3][0]);
        $this->assertSame('COCODY', $feuille[4][0]);
        $this->assertSame(12.0, $feuille[4][1], 'Dim (m²)');
        $this->assertSame(41840000, $feuille[4][4], 'arriérés');
        $this->assertSame(41680000, end($feuille)[array_key_last(end($feuille))], 'solde au total');

        // Le classeur complet se construit (styles, fusions, 3 feuilles).
        $binaire = \Maatwebsite\Excel\Facades\Excel::raw(
            new \App\Exports\TaxesSuiviExport(2027, $zones, false),
            \Maatwebsite\Excel\Excel::XLSX
        );
        $this->assertStringStartsWith('PK', $binaire, 'un .xlsx est une archive zip');

        @unlink($cadre . '/layouts/admin.blade.php');
        @rmdir($cadre . '/layouts');
        @rmdir($cadre);
    }

    public function test_la_reprise_des_anciens_versements_est_rejouable(): void
    {
        Schema::create('commune_tax_payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commune_id'); $t->string('period_type'); $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_value')->default(0); $t->decimal('odp_theorique', 14, 2)->default(0);
            $t->decimal('tm_theorique', 14, 2)->default(0); $t->decimal('odp_paye', 14, 2)->default(0);
            $t->decimal('tm_paye', 14, 2)->default(0); $t->date('paid_at')->nullable(); $t->string('mode')->nullable();
            $t->string('reference')->nullable(); $t->text('comment')->nullable(); $t->text('notes')->nullable();
            $t->string('attestation_path')->nullable(); $t->unsignedBigInteger('recorded_by')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        DB::table('commune_tax_payments')->insert([
            ['id' => 7, 'commune_id' => 2, 'period_type' => 'trimestriel', 'period_year' => 2026, 'period_value' => 3,
             'odp_paye' => 296000, 'tm_paye' => 0, 'paid_at' => '2026-08-20', 'reference' => 'Q-123'],
        ]);

        Artisan::call('taxes:reprendre-reglements');
        $this->assertSame(0, TaxeReglement::where('origine', 'commune_tax_payment')->count(), 'simulation : rien d\'écrit');

        Artisan::call('taxes:reprendre-reglements', ['--appliquer' => true]);
        Artisan::call('taxes:reprendre-reglements', ['--appliquer' => true]);

        $repris = TaxeReglement::where('origine', 'commune_tax_payment')->get();
        $this->assertCount(1, $repris, 'rejouée sans doublon');
        $this->assertSame(296000, $repris[0]->montant);
        $this->assertSame(2026, $repris[0]->exercice);
        $this->assertSame('odp', $repris[0]->objet);
        $this->assertSame('Q-123', $repris[0]->reference);
        $this->assertSame(1, DB::table('commune_tax_payments')->count(), 'l\'ancienne table n\'est pas touchée');
    }
}
