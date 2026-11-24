<?php

namespace Tests\Unit;

use App\Models\CampaignPanelMouvement;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TM d'une campagne qui tient la LIGNE d'un axe (2026-11-24, règle validée
 * par l'utilisateur) : elle compte pour la TM de chacun des pieds de l'axe,
 * sur ses dates, comme une campagne découpée en pieds.
 *
 * Cas réel : à Cocody, KOTON (Lup), VOODOO – MANSA BANK et KOTON – SOLDES,
 * terminées avant le découpage du 13/11, tiennent encore la ligne de leurs
 * axes Lumipub. La ligne d'axe n'étant jamais taxée, leur TM ne sortait
 * nulle part ; seul BEM, découpé, apparaissait. Données fictives ici.
 */
class TmCampagneSurLigneDAxeTest extends TestCase
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
        DB::table('panel_formats')->insert(['id' => 1, 'name' => '3m²', 'width' => 1.5, 'height' => 2, 'surface' => 3]);
        // Un axe Lumipub de 2 pieds à 3 faces de 3 m² : 9 m² taxables par pied.
        $axe = ['commune_id' => 1, 'format_id' => 1, 'odp_start_date' => '2025-01-01', 'faces_par_pied' => 3];
        DB::table('panels')->insert([
            ['id' => 100, 'reference' => 'ESSLUP-01', 'name' => 'Axe essai (2P x 3F)', 'est_axe' => true, 'axe_id' => null, 'pied_numero' => null] + $axe,
            ['id' => 101, 'reference' => 'ESSLUP-01-P1', 'name' => 'Axe essai — pied 1', 'est_axe' => false, 'axe_id' => 100, 'pied_numero' => 1] + $axe,
            ['id' => 102, 'reference' => 'ESSLUP-01-P2', 'name' => 'Axe essai — pied 2', 'est_axe' => false, 'axe_id' => 100, 'pied_numero' => 2] + $axe,
        ]);
        DB::table('clients')->insert(['id' => 1, 'name' => 'CLIENT ESSAI']);
        // Campagne terminée avant le découpage : elle tient la LIGNE de l'axe.
        DB::table('campaigns')->insert(['id' => 50, 'name' => 'Campagne essai', 'client_id' => 1, 'status' => 'termine',
            'start_date' => '2026-04-01', 'end_date' => '2026-06-30']);
        DB::table('campaign_panels')->insert(['campaign_id' => 50, 'panel_id' => 100, 'type' => 'interne']);
    }

    private function lignesTm(int $trimestre, bool $lumipub = true): \Illuminate\Support\Collection
    {
        return app(TaxCalculationService::class)
            ->generateLines('trimestriel', $trimestre, 2026, $lumipub ? ['inclure_lumipub' => true] : [])
            ->where('type', 'tm')->values();
    }

    public function test_la_campagne_de_la_ligne_d_axe_compte_pour_chaque_pied(): void
    {
        $t2 = $this->lignesTm(2);

        $this->assertSame([101, 102], $t2->pluck('panel_id')->sort()->values()->all(), 'une ligne par pied');
        $this->assertNotContains(100, $t2->pluck('panel_id')->all(), "la ligne d'axe n'est jamais taxée");
        foreach ($t2 as $ligne) {
            $this->assertSame('Campagne essai', $ligne['campaign_name']);
            $this->assertSame('CLIENT ESSAI', $ligne['client_name']);
            $this->assertSame(3, $ligne['months'], "avril à juin");
            $this->assertEquals(9, $ligne['surface'], '3 faces de 3 m²');
            $this->assertEquals(27000, $ligne['amount'], '1 000 × 9 m² × 3 mois');
        }
        $this->assertCount(0, $this->lignesTm(1), 'rien en T1');
        $this->assertCount(0, $this->lignesTm(3), 'rien en T3');
    }

    public function test_sans_la_case_lumipub_les_pieds_restent_hors_des_taxes(): void
    {
        $this->assertCount(0, $this->lignesTm(2, false));
    }

    public function test_un_pied_rattache_lui_meme_n_est_compte_qu_une_fois(): void
    {
        DB::table('campaign_panels')->insert(['campaign_id' => 50, 'panel_id' => 101, 'type' => 'interne']);

        $t2 = $this->lignesTm(2);
        $this->assertSame(1, $t2->where('panel_id', 101)->count());
        $this->assertSame(1, $t2->where('panel_id', 102)->count());
        $this->assertEquals(54000, $t2->sum('amount'));
    }

    /** Ligne d'axe retirée de la campagne : ses pieds paient jusqu'à la date réelle de sortie. */
    public function test_ligne_d_axe_retiree_jusqu_a_sa_sortie(): void
    {
        DB::table('campaign_panels')->where('panel_id', 100)->delete();
        DB::table('campaign_panel_mouvements')->insert([
            'campaign_id' => 50, 'panel_id' => 100, 'reference' => 'ESSLUP-01', 'sens' => CampaignPanelMouvement::RETRAIT,
            'motif' => null, 'source' => 'manuel', 'effet_le' => '2026-05-15',
            'created_at' => '2026-11-24 08:00:00', 'updated_at' => '2026-11-24 08:00:00',
        ]);

        $t2 = $this->lignesTm(2);
        $this->assertSame([2, 2], $t2->pluck('months')->all(), 'avril et mai (sortie le 15/05)');
    }

    /** Le filtre client garde les pieds de l'axe tenu par la campagne du client. */
    public function test_le_filtre_client_retrouve_les_pieds(): void
    {
        $lignes = app(TaxCalculationService::class)
            ->generateLines('trimestriel', 2, 2026, ['inclure_lumipub' => true, 'client_id' => 1])
            ->where('type', 'tm');

        $this->assertCount(2, $lignes);
    }
}
