<?php

namespace Tests\Unit;

use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Services\Axes\AxeService;
use App\Services\InvoiceFromCampaignBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Découpage des axes entiers des campagnes en cours (2026-11-13, validé par
 * l'utilisateur : « faisons le découpage pour que dans l'espace tech ce soit
 * comme notre nouvelle refonte » ; factures pas encore générées).
 *
 * Scénario : BEM a signé avant le découpage l'axe CDYLUP-001 (4 pieds) à
 * 400 000 FCFA/mois ; Yannick a la pose de l'axe, planifiée, vague 7.
 * CDYLUP-007 (2 pieds) a une pose ouverte qui a déjà une photo. Un pied de
 * CDYLUP-001 est aussi sur une autre campagne en cours.
 *
 * Base SQLite réduite (les migrations ne passent pas sous SQLite).
 */
class DecouperAxesDesCampagnesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('reservation_id')->nullable(); $t->string('status')->default('actif');
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('status'); $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->decimal('unit_price', 14, 2)->nullable();
            $t->string('source')->nullable(); $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panel_formats', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->decimal('width', 8, 2)->nullable(); $t->decimal('height', 8, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('panel_photos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->string('path'); $t->unsignedInteger('ordre')->default(0); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable();
            $t->unsignedBigInteger('commune_id')->nullable(); $t->unsignedBigInteger('format_id')->nullable();
            $t->decimal('monthly_rate', 14, 2)->nullable();
            $t->unsignedBigInteger('axe_id')->nullable(); $t->boolean('est_axe')->default(false);
            $t->unsignedSmallInteger('pied_numero')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('pose_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('assigned_user_id')->nullable(); $t->unsignedBigInteger('pose_team_id')->nullable();
            $t->string('team_name')->nullable(); $t->dateTime('scheduled_at')->nullable();
            $t->dateTime('started_at')->nullable(); $t->dateTime('arrived_at')->nullable(); $t->dateTime('done_at')->nullable();
            $t->string('status')->default('planifiee'); $t->unsignedTinyInteger('progress_percent')->default(0);
            $t->text('notes')->nullable(); $t->string('public_token')->nullable(); $t->string('pose_kind')->nullable();
            $t->unsignedBigInteger('vague_id')->nullable(); $t->timestamps();
        });
        Schema::create('tech_notifications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('type'); $t->string('title');
            $t->text('detail')->nullable(); $t->text('payload')->nullable(); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::create('piges', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('pose_task_id')->nullable(); $t->string('photo_path'); $t->string('photo_thumb')->nullable();
            $t->string('status')->default('en_attente'); $t->timestamp('verified_at')->nullable(); $t->timestamps();
        });

        DB::table('reservations')->insert([
            ['id' => 1, 'status' => 'confirme', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
            ['id' => 2, 'status' => 'confirme', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
        ]);
        DB::table('campaigns')->insert([
            ['id' => 2, 'name' => 'BEM 2026', 'client_id' => 2, 'reservation_id' => 1, 'status' => 'actif',
             'start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
            ['id' => 3, 'name' => 'Autre client', 'client_id' => 3, 'reservation_id' => 2, 'status' => 'actif',
             'start_date' => '2026-06-01', 'end_date' => '2026-12-31'],
            ['id' => 4, 'name' => 'Ancienne', 'client_id' => 2, 'reservation_id' => null, 'status' => 'termine',
             'start_date' => '2025-01-01', 'end_date' => '2025-12-31'],
        ]);
        DB::table('communes')->insert(['id' => 1, 'name' => 'Cocody']);
        DB::table('panels')->insert([
            ['id' => 30, 'reference' => 'CDYLUP-001', 'name' => 'Angré (4P x 2F)', 'commune_id' => 1, 'monthly_rate' => 500000,
             'est_axe' => true, 'axe_id' => null, 'pied_numero' => null],
            ['id' => 31, 'reference' => 'CDYLUP-001-P01', 'name' => 'Angré — pied 1/4', 'commune_id' => 1, 'monthly_rate' => 150000,
             'est_axe' => false, 'axe_id' => 30, 'pied_numero' => 1],
            ['id' => 32, 'reference' => 'CDYLUP-001-P02', 'name' => 'Angré — pied 2/4', 'commune_id' => 1, 'monthly_rate' => 150000,
             'est_axe' => false, 'axe_id' => 30, 'pied_numero' => 2],
            ['id' => 33, 'reference' => 'CDYLUP-001-P03', 'name' => 'Angré — pied 3/4', 'commune_id' => 1, 'monthly_rate' => 150000,
             'est_axe' => false, 'axe_id' => 30, 'pied_numero' => 3],
            ['id' => 34, 'reference' => 'CDYLUP-001-P04', 'name' => 'Angré — pied 4/4', 'commune_id' => 1, 'monthly_rate' => 150000,
             'est_axe' => false, 'axe_id' => 30, 'pied_numero' => 4],
            ['id' => 40, 'reference' => 'CDYLUP-007', 'name' => 'Palmeraie (2P x 2F)', 'commune_id' => 1, 'monthly_rate' => 300000,
             'est_axe' => true, 'axe_id' => null, 'pied_numero' => null],
            ['id' => 41, 'reference' => 'CDYLUP-007-P01', 'name' => 'Palmeraie — pied 1/2', 'commune_id' => 1, 'monthly_rate' => 150000,
             'est_axe' => false, 'axe_id' => 40, 'pied_numero' => 1],
            ['id' => 42, 'reference' => 'CDYLUP-007-P02', 'name' => 'Palmeraie — pied 2/2', 'commune_id' => 1, 'monthly_rate' => 150000,
             'est_axe' => false, 'axe_id' => 40, 'pied_numero' => 2],
        ]);
        DB::table('campaign_panels')->insert([
            ['campaign_id' => 2, 'panel_id' => 30, 'type' => 'interne'],
            ['campaign_id' => 2, 'panel_id' => 40, 'type' => 'interne'],
            // Le pied 4 de CDYLUP-001 est déjà vendu à un autre client.
            ['campaign_id' => 3, 'panel_id' => 34, 'type' => 'interne'],
            // Une campagne terminée ne se découpe pas.
            ['campaign_id' => 4, 'panel_id' => 30, 'type' => 'interne'],
        ]);
        DB::table('reservation_panels')->insert([
            ['reservation_id' => 1, 'panel_id' => 30, 'unit_price' => 400000],
            ['reservation_id' => 1, 'panel_id' => 40, 'unit_price' => 250000],
            ['reservation_id' => 2, 'panel_id' => 34, 'unit_price' => 150000],
        ]);
        DB::table('pose_tasks')->insert([
            // La pose de la vague précédente, faite : historique, intouchée.
            ['id' => 1, 'panel_id' => 30, 'campaign_id' => 2, 'assigned_user_id' => 2, 'status' => 'realisee',
             'scheduled_at' => '2026-07-01 08:00:00', 'done_at' => '2026-07-01 12:00:00', 'vague_id' => 6, 'notes' => null],
            // La pose ouverte de l'axe : à découper.
            ['id' => 5, 'panel_id' => 30, 'campaign_id' => 2, 'assigned_user_id' => 2, 'status' => 'planifiee',
             'scheduled_at' => '2026-11-14 08:00:00', 'done_at' => null, 'vague_id' => 7, 'notes' => 'Bâche au dépôt'],
            // Pose ouverte de CDYLUP-007 qui a déjà une photo.
            ['id' => 6, 'panel_id' => 40, 'campaign_id' => 2, 'assigned_user_id' => 2, 'status' => 'en_cours',
             'scheduled_at' => '2026-11-13 08:00:00', 'done_at' => null, 'vague_id' => 7, 'notes' => null],
        ]);
        DB::table('piges')->insert([
            ['panel_id' => 30, 'campaign_id' => 2, 'pose_task_id' => 1, 'photo_path' => 'piges/axe-juillet.jpg',
             'status' => 'verifie', 'verified_at' => '2026-07-02 10:00:00'],
            ['panel_id' => 40, 'campaign_id' => 2, 'pose_task_id' => 6, 'photo_path' => 'piges/palmeraie.jpg',
             'status' => 'en_attente', 'verified_at' => null],
        ]);
    }

    private function panneauxDeLaCampagne(int $campagne): array
    {
        return DB::table('campaign_panels')->where('campaign_id', $campagne)->orderBy('panel_id')->pluck('panel_id')->all();
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $this->artisan('axes:decouper-campagnes')
            ->expectsOutputToContain('SIMULATION')
            ->expectsOutputToContain('CDYLUP-001-P04 déjà pris par la campagne #3')
            ->expectsOutputToContain('400 000 FCFA/mois')
            ->assertSuccessful();

        $this->assertSame([30, 40], $this->panneauxDeLaCampagne(2));
        $this->assertSame(3, DB::table('pose_tasks')->count());
        $this->assertSame('planifiee', DB::table('pose_tasks')->where('id', 5)->value('status'));
    }

    public function test_appliquer_remplace_l_axe_par_ses_pieds_une_pose_par_pied(): void
    {
        $this->artisan('axes:decouper-campagnes', ['--appliquer' => true])->assertSuccessful();

        // La campagne tient les pieds libres de CDYLUP-001 ; CDYLUP-007, qui
        // a une photo sur sa pose ouverte, reste en axe entier.
        $this->assertSame([31, 32, 33, 40], $this->panneauxDeLaCampagne(2));
        $this->assertSame([30], $this->panneauxDeLaCampagne(4), 'campagne terminée intouchée');

        $pieds = PoseTask::where('campaign_id', 2)->whereIn('panel_id', [31, 32, 33, 34])->orderBy('panel_id')->get();
        $this->assertSame([31, 32, 33], $pieds->pluck('panel_id')->all(), 'pas de pose pour le pied pris ailleurs');
        foreach ($pieds as $p) {
            $this->assertSame(2, (int) $p->assigned_user_id);
            $this->assertSame('planifiee', (string) $p->status);
            $this->assertSame('2026-11-14 08:00', $p->scheduled_at->format('Y-m-d H:i'));
            $this->assertSame(7, (int) $p->vague_id);
            $this->assertSame('Bâche au dépôt', $p->notes, 'la consigne suit chaque pied');
        }

        $axe = PoseTask::find(5);
        $this->assertSame('annulee', (string) $axe->status);
        $this->assertStringContainsString('Bâche au dépôt', $axe->notes);
        $this->assertStringContainsString('Découpée en 3 pieds', $axe->notes);

        $this->assertSame('realisee', DB::table('pose_tasks')->where('id', 1)->value('status'), 'l\'historique reste');
        $this->assertSame('en_cours', DB::table('pose_tasks')->where('id', 6)->value('status'));
        $this->assertSame(0, DB::table('tech_notifications')->count(), 'ni alerte ni WhatsApp : même pose, découpée');
        $this->assertSame(3, DB::table('reservation_panels')->count(), 'réservations inchangées');
    }

    public function test_relancer_apres_coup_ne_fait_rien_de_plus(): void
    {
        $this->artisan('axes:decouper-campagnes', ['--appliquer' => true])->assertSuccessful();
        $this->artisan('axes:decouper-campagnes', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame([31, 32, 33, 40], $this->panneauxDeLaCampagne(2));
        $this->assertSame(3, PoseTask::whereIn('panel_id', [31, 32, 33])->count());
    }

    public function test_un_axe_absent_de_la_reservation_n_est_pas_decoupe(): void
    {
        DB::table('reservation_panels')->where('panel_id', 30)->delete();

        $this->artisan('axes:decouper-campagnes', ['--appliquer' => true])
            ->expectsOutputToContain('prix du contrat introuvable')
            ->assertSuccessful();

        $this->assertSame([30, 40], $this->panneauxDeLaCampagne(2));
        $this->assertSame('planifiee', DB::table('pose_tasks')->where('id', 5)->value('status'));
    }

    public function test_la_facture_de_l_axe_decoupe_reste_celle_du_contrat(): void
    {
        $builder = app(InvoiceFromCampaignBuilder::class);
        $negocie = ['int_30' => 400000.0];
        $avant = $builder->grouperParAxe(Panel::whereIn('id', [30])->get(), $negocie);

        $this->artisan('axes:decouper-campagnes', ['--appliquer' => true])->assertSuccessful();
        $apres = $builder->grouperParAxe(Panel::whereIn('id', $this->panneauxDeLaCampagne(2))->get(), $negocie + ['int_40' => 250000.0]);
        $ligne = collect($apres)->first(fn ($g) => $g['porteur']->id === 30);

        $this->assertSame(400000.0, $avant[0]['pu']);
        $this->assertSame(400000.0, $ligne['pu'], 'le prix de l\'axe au contrat, pas 3 × 150 000');
        $this->assertNull($ligne['complement']);
        $this->assertTrue($ligne['taxes_a_la_main']);
        $this->assertCount(2, $apres, 'une ligne par axe');
    }

    public function test_une_vente_au_pied_garde_la_somme_de_ses_pieds(): void
    {
        $lignes = app(InvoiceFromCampaignBuilder::class)->grouperParAxe(
            Panel::whereIn('id', [31, 32])->get(),
            ['int_31' => 120000.0, 'int_32' => 0.0, 'int_30' => 400000.0],
        );

        $this->assertSame(120000.0, $lignes[0]['pu'], 'pieds au contrat : leur somme, pied offert à 0');
        $this->assertSame('2 pieds', $lignes[0]['complement']);
    }

    public function test_le_portail_client_garde_la_photo_de_l_axe_entier(): void
    {
        $this->artisan('axes:decouper-campagnes', ['--appliquer' => true])->assertSuccessful();

        $panneaux = Panel::with('commune', 'format')->whereIn('id', $this->panneauxDeLaCampagne(2))->get();
        $entree = app(AxeService::class)->grouperParAxe($panneaux, fn ($p) => $p, photos: true)
            ->first(fn ($e) => $e['axe']?->id === 30);
        $verifiees = Pige::where('status', 'verifie')->get();

        $html = view('client.partials._carte_axe', [
            'entree' => $entree,
            'poses' => PoseTask::where('status', 'realisee')->get()->keyBy('panel_id'),
            'pigesVerif' => $verifiees->groupBy('panel_id'),
            'pigeIndexMap' => [$verifiees->first()->id => 0],
        ])->render();

        $this->assertStringContainsString('⛓ Axe · 3 pieds', $html);
        $this->assertStringContainsString('Axe entier · photo du 02/07/2026', $html);
        $this->assertStringNotContainsString('pigés', $html, 'les pieds ne sont pas encore pigés');
    }
}
