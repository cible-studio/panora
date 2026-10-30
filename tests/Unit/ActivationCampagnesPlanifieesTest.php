<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Services\CampaignService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * campaigns:activate-planned (2026-10-30) : l'alerte d'une campagne
 * planifiée non démarrée donne la vraie raison renvoyée par
 * CampaignService::activate. Avant, elle disait toujours « sans panneau »,
 * même pour un conflit de panneaux. Une seule alerte par campagne, mise
 * à jour chaque nuit (dédoublonnage d'AlertService).
 */
class ActivationCampagnesPlanifieesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable(); $t->unsignedBigInteger('reservation_id')->nullable();
            $t->string('status'); $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('external_panels', function (Blueprint $t) {
            $t->id(); $t->string('code_panneau')->nullable(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('alerts', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->string('niveau')->default('info'); $t->string('title', 150); $t->text('message');
            $t->string('related_type', 50)->nullable(); $t->unsignedBigInteger('related_id')->nullable(); $t->string('dedup_key', 191)->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('lien')->nullable(); $t->boolean('is_read')->default(false);
            $t->timestamp('triggered_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });

        Carbon::setTestNow('2026-11-02 06:00:00');
        DB::table('panels')->insert(['id' => 100, 'reference' => 'ABJ-100']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function campagne(string $nom, bool $avecPanneau): int
    {
        $id = DB::table('campaigns')->insertGetId([
            'name' => $nom, 'client_id' => 10, 'status' => 'planifie', 'start_date' => '2026-11-01', 'end_date' => '2026-12-31',
        ]);
        if ($avecPanneau) {
            DB::table('campaign_panels')->insert(['campaign_id' => $id, 'panel_id' => 100, 'type' => 'interne']);
        }

        return $id;
    }

    private function activationRefusee(string $erreur): void
    {
        $this->mock(CampaignService::class, fn ($m) => $m->shouldReceive('activate')->andReturn(['ok' => false, 'error' => $erreur]));
    }

    public function test_un_conflit_de_panneaux_est_annonce_comme_tel_une_seule_fois(): void
    {
        $id = $this->campagne('Fin d\'année Orange', avecPanneau: true);
        $erreur = 'Conflit de panneaux : ABJ-100 déjà engagés sur la période par une autre campagne ou réservation.';
        $this->activationRefusee($erreur);

        $this->artisan('campaigns:activate-planned')->assertSuccessful();
        $this->artisan('campaigns:activate-planned')->assertSuccessful(); // nuit suivante

        $alertes = Alert::all();
        $this->assertCount(1, $alertes); // mise à jour, pas recréée
        $alerte = $alertes->first();
        $this->assertSame('conflit_reservation', $alerte->type);
        $this->assertSame('⚡ Campagne planifiée bloquée par un conflit de panneaux — Fin d\'année Orange', $alerte->title);
        $this->assertStringContainsString($erreur, $alerte->message);
        $this->assertStringNotContainsString('aucun panneau', $alerte->message);
        $this->assertSame($id, (int) $alerte->related_id);
    }

    public function test_sans_panneau_l_alerte_historique_est_conservee(): void
    {
        $this->campagne('Lancement MTN', avecPanneau: false);
        $this->activationRefusee('Ajoutez au moins un panneau à la campagne avant de l\'activer.');

        $this->artisan('campaigns:activate-planned')->assertSuccessful();

        $alerte = Alert::sole();
        $this->assertSame('campagne_sans_panneau', $alerte->type);
        $this->assertSame('⚠️ Campagne planifiée sans panneau — Lancement MTN', $alerte->title);
        $this->assertSame('La date de début est atteinte mais aucun panneau n\'est attaché. Ajoutez des panneaux puis activez manuellement.', $alerte->message);
    }

    public function test_une_autre_raison_est_rapportee_telle_quelle(): void
    {
        $this->campagne('Rentrée Moov', avecPanneau: true);
        $this->activationRefusee('Raison imprévue.');

        $this->artisan('campaigns:activate-planned')->assertSuccessful();

        $alerte = Alert::sole();
        $this->assertSame('campagne_statut', $alerte->type);
        $this->assertSame('⚠️ Campagne planifiée non démarrée — Rentrée Moov', $alerte->title);
        $this->assertStringContainsString('Raison imprévue.', $alerte->message);
    }
}
