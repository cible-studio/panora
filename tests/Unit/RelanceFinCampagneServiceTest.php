<?php

namespace Tests\Unit;

use App\Mail\CampaignEndingSoonMail;
use App\Models\Campaign;
use App\Models\RelanceFinCampagne;
use App\Services\Diffusion\RelanceFinCampagneService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Mail « Votre campagne se termine » (J-7) : déroulé complet — sélection,
 * journal, un seul mail par campagne, mode test, envoi par Brevo
 * uniquement, sans secours (2026-10-21, secours retiré le 2026-10-22).
 * L'API Brevo est simulée : aucun appel réseau.
 *
 * Les migrations du projet ne passent pas sous SQLite (MODIFY COLUMN, cf.
 * TECHNICAL_DEBT.md) : on crée ici les seules colonnes utiles, plus la
 * vraie migration du journal.
 */
class RelanceFinCampagneServiceTest extends TestCase
{
    /** Réponse simulée de l'API Brevo (modifiable par test). */
    private int $statutBrevo = 201;
    private array $reponseBrevo = ['messageId' => '<abc@smtp-relay.brevo.com>'];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('contact_name')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name'); $t->string('email')->nullable();
            $t->boolean('is_primary')->default(false); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('commercial_user_id')->nullable();
            $t->string('status'); $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('diffusion_desinscriptions', function (Blueprint $t) {
            $t->id(); $t->string('email')->unique(); $t->string('motif'); $t->string('source')->default('brevo');
            $t->timestamp('survenu_at')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_21_100000_create_relances_fin_campagne_table.php'))->up();

        Carbon::setTestNow('2026-10-21 10:00:00');

        config([
            'diffusion.fin_campagne.mode'  => 'production',
            'diffusion.fin_campagne.copie' => 'commercial@cible-ci.com',
            'diffusion.confirmation_email' => 'studio@cible-ci.com',
            'brevo.api_key'      => 'cle-de-test',
            'brevo.sender.email' => 'commercial@cible-ci.com',
            'brevo.lists.tests'  => null,
        ]);

        Mail::fake();
        Http::fake(fn() => Http::response($this->reponseBrevo, $this->statutBrevo));

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Créateur', 'email' => 'createur@cible-ci.com'],
            ['id' => 2, 'name' => 'K. Yao', 'email' => 'k.yao@cible-ci.com'],
        ]);
        DB::table('clients')->insert(['id' => 10, 'name' => 'ORANGE CI', 'email' => 'contact@orange.ci']);
        DB::table('client_contacts')->insert(['client_id' => 10, 'name' => 'Awa Koné', 'email' => 'awa@orange.ci', 'is_primary' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function campagne(array $attr = []): Campaign
    {
        $id = DB::table('campaigns')->insertGetId($attr + [
            'name' => 'Rentrée', 'client_id' => 10, 'user_id' => 1, 'commercial_user_id' => 2,
            'status' => 'actif', 'start_date' => '2026-09-01', 'end_date' => '2026-10-28',
        ]);

        return Campaign::findOrFail($id);
    }

    /** @return \Illuminate\Support\Collection<int, \Illuminate\Http\Client\Request>  mails demandés à Brevo */
    private function envoisBrevo(): \Illuminate\Support\Collection
    {
        return Http::recorded(fn($r) => str_ends_with($r->url(), '/smtp/email'))->map(fn($p) => $p[0])->values();
    }

    private function service(): RelanceFinCampagneService
    {
        return app(RelanceFinCampagneService::class);
    }

    public function test_selection_du_jour_campagne_active_a_j_moins_7_seulement(): void
    {
        $cible = $this->campagne();
        $this->campagne(['name' => 'J-6', 'end_date' => '2026-10-27']);
        $this->campagne(['name' => 'J-8', 'end_date' => '2026-10-29']);
        $this->campagne(['name' => 'Pause', 'status' => 'pause']);

        $this->assertSame([$cible->id], $this->service()->campagnesDuJour()->pluck('id')->all());
    }

    public function test_envoi_production_par_brevo_au_client_reponse_au_commercial_copie_commercial(): void
    {
        $ligne = $this->service()->traiter($this->campagne());

        $this->assertSame('envoye', $ligne->statut);
        $this->assertSame('brevo', $ligne->canal);
        $this->assertSame('<abc@smtp-relay.brevo.com>', $ligne->brevo_message_id);
        $this->assertSame('awa@orange.ci', $ligne->destinataire);
        $this->assertSame('k.yao@cible-ci.com', $ligne->repondre_a);

        $this->assertCount(1, $this->envoisBrevo());
        $r = $this->envoisBrevo()->first();
        $this->assertSame('awa@orange.ci', $r['to'][0]['email']);
        $this->assertSame('commercial@cible-ci.com', $r['cc'][0]['email']);
        $this->assertSame('k.yao@cible-ci.com', $r['replyTo']['email']);
        $this->assertSame(['fin-campagne'], $r['tags']);
        $this->assertStringContainsString('dans 7 jours', $r['textContent']);

        // Rien par le serveur de mail de Panora : tout se suit dans Brevo.
        Mail::assertNothingSent();
    }

    public function test_un_seul_mail_par_campagne(): void
    {
        $campagne = $this->campagne();
        $this->service()->traiter($campagne);
        $this->service()->traiter($campagne);

        $this->assertCount(1, $this->envoisBrevo());
        $this->assertSame(1, RelanceFinCampagne::count());
    }

    public function test_une_prolongation_redonne_droit_a_un_mail(): void
    {
        $campagne = $this->campagne();
        $this->service()->traiter($campagne);

        $campagne->update(['end_date' => '2026-11-30']);
        $this->service()->traiter($campagne->fresh());

        $this->assertCount(2, $this->envoisBrevo());
    }

    public function test_deja_prevenu_par_l_ancienne_version_pas_de_renvoi(): void
    {
        $campagne = $this->campagne();
        Cache::put("ending_notified.{$campagne->id}.7", true, now()->addDays(60));

        $ligne = $this->service()->traiter($campagne);

        $this->assertCount(0, $this->envoisBrevo());
        $this->assertSame('envoye', $ligne->statut);
    }

    public function test_mode_test_ecrit_aux_adresses_de_test_jamais_au_client(): void
    {
        config(['diffusion.fin_campagne.mode' => 'test']);

        $ligne = $this->service()->traiter($this->campagne());

        $this->assertSame('test', $ligne->mode);
        $this->assertCount(1, $this->envoisBrevo());
        $r = $this->envoisBrevo()->first();
        $this->assertSame('studio@cible-ci.com', $r['to'][0]['email']);
        $this->assertArrayNotHasKey('cc', $r->data());
        $this->assertStringStartsWith('[TEST]', $r['subject']);
    }

    public function test_un_test_n_empeche_pas_le_vrai_mail_ensuite(): void
    {
        $campagne = $this->campagne();
        $this->service()->traiter($campagne, forcerTest: true);
        $ligne = $this->service()->traiter($campagne);

        $this->assertSame('production', $ligne->mode);
        $this->assertSame('awa@orange.ci', $this->envoisBrevo()->last()['to'][0]['email']);
    }

    public function test_client_avec_une_autre_campagne_qui_continue_n_est_pas_prevenu(): void
    {
        $campagne = $this->campagne();
        $this->campagne(['name' => 'Fêtes', 'end_date' => '2026-12-31']);

        $ligne = $this->service()->traiter($campagne);

        $this->assertSame('exclu', $ligne->statut);
        $this->assertCount(0, $this->envoisBrevo());
    }

    public function test_adresse_desinscrite_pas_de_mail(): void
    {
        DB::table('diffusion_desinscriptions')->insert(['email' => 'awa@orange.ci', 'motif' => 'desinscription']);

        $this->assertSame('exclu', $this->service()->traiter($this->campagne())->statut);
        $this->assertCount(0, $this->envoisBrevo());
    }

    public function test_sans_adresse_le_mail_est_retente_les_jours_suivants(): void
    {
        DB::table('client_contacts')->delete();
        DB::table('clients')->update(['email' => null]);
        $campagne = $this->campagne();

        $this->assertSame('echec', $this->service()->traiter($campagne)->statut);

        // Le lendemain, l'adresse a été ajoutée : la campagne (J-6) est reprise.
        Carbon::setTestNow('2026-10-22 10:00:00');
        DB::table('clients')->update(['email' => 'direction@orange.ci']);
        $this->assertSame([$campagne->id], $this->service()->campagnesDuJour()->pluck('id')->all());

        $this->assertSame('envoye', $this->service()->traiter($campagne->fresh())->statut);
        $r = $this->envoisBrevo()->first();
        $this->assertSame('direction@orange.ci', $r['to'][0]['email']);
        $this->assertStringContainsString('dans 6 jours', $r['textContent']);
    }

    public function test_brevo_refuse_pas_de_secours_le_mail_est_retente_le_lendemain(): void
    {
        $this->statutBrevo  = 400;
        $this->reponseBrevo = ['message' => 'sender not valid'];
        $campagne = $this->campagne();

        $ligne = $this->service()->traiter($campagne);

        $this->assertSame('echec', $ligne->statut);
        $this->assertStringContainsString('sender not valid', $ligne->motif);
        Mail::assertNothingSent();

        // Le lendemain, Brevo accepte : le mail repart, par Brevo.
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->statutBrevo  = 201;
        $this->reponseBrevo = ['messageId' => '<def@smtp-relay.brevo.com>'];
        $this->assertSame([$campagne->id], $this->service()->campagnesDuJour()->pluck('id')->all());

        $ligne = $this->service()->traiter($campagne->fresh());
        $this->assertSame('envoye', $ligne->statut);
        $this->assertSame('brevo', $ligne->canal);
        $this->assertSame(2, $ligne->tentatives);
        Mail::assertNothingSent();
    }

    public function test_brevo_non_configure_aucun_envoi(): void
    {
        config(['brevo.api_key' => null]);

        $ligne = $this->service()->traiter($this->campagne());

        $this->assertSame('echec', $ligne->statut);
        $this->assertStringContainsString('BREVO_API_KEY', $ligne->motif);
        $this->assertCount(0, $this->envoisBrevo());
        Mail::assertNothingSent();
    }

    public function test_lien_de_desinscription_ouvrir_ne_desinscrit_pas_confirmer_desinscrit(): void
    {
        $lien = \App\Models\DiffusionDesinscription::lien('awa@orange.ci');
        $chemin = parse_url($lien, PHP_URL_PATH);
        // Synchro des statuts à chaque requête : tables absentes de ce schéma réduit.
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);

        // Les antivirus de messagerie ouvrent les liens : un GET ne doit rien changer.
        $this->get($chemin)->assertOk()->assertSee('Confirmer la désinscription');
        $this->assertDatabaseCount('diffusion_desinscriptions', 0);

        // Bouton de la page, ou désinscription en un clic de Gmail (sans jeton CSRF).
        $this->post($chemin, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee("C'est noté", false);
        $this->assertDatabaseHas('diffusion_desinscriptions', ['email' => 'awa@orange.ci', 'motif' => 'desinscription', 'source' => 'lien']);

        // Lien altéré : 404, rien d'enregistré.
        $this->post(preg_replace('#/[a-f0-9]{32}$#', '/' . str_repeat('0', 32), $chemin))->assertNotFound();
        $this->assertDatabaseCount('diffusion_desinscriptions', 1);

        // Le mail suivant ne part plus.
        $this->assertSame('exclu', $this->service()->traiter($this->campagne())->statut);
    }
}
