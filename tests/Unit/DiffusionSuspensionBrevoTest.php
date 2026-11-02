<?php

namespace Tests\Unit;

use App\Mail\DiffusionDisponibilitesMail;
use App\Models\DiffusionEnvoi;
use App\Services\Brevo\BrevoClient;
use App\Services\Brevo\BrevoException;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use App\Services\NotificationMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Envois suspendus par Brevo (règles validées le 2026-11-02).
 *
 * Constaté le 2/11 : Brevo accepte l'ordre d'envoi puis suspend la
 * campagne (quota gratuit épuisé, trop d'adresses invalides), et Panora
 * affichait « Envoyé ». Désormais :
 *   A. les envois des dernières 24 h sont relus chez Brevo, plus après la fin ;
 *   B. une suspension passe en « Échecs », l'équipe est prévenue ;
 *   C. la cause probable est déduite des chiffres de Brevo ;
 *   D. un envoi suspendu compte comme parti (anti-double envoi) ;
 *   E. si Brevo annonce moins d'envois restants que de destinataires,
 *      rien ne part.
 */
class DiffusionSuspensionBrevoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 17:00:00');
        Cache::flush();

        config([
            'diffusion.mode'               => 'test',
            'diffusion.controle_quota'     => true,
            'diffusion.confirmation_email' => 'commercial@cible-ci.com',
            'brevo.lists.clients'          => 3,
            'brevo.lists.tests'            => 4,
        ]);

        Schema::create('diffusion_envois', function (Blueprint $t) {
            $t->id(); $t->date('creneau'); $t->date('periode_debut'); $t->date('periode_fin'); $t->string('mode', 10);
            $t->unsignedBigInteger('declenche_par')->nullable(); $t->string('statut', 12)->default('prepare');
            $t->unsignedInteger('nb_destinataires')->default(0); $t->unsignedInteger('nb_panneaux')->default(0);
            $t->string('pdf_path')->nullable(); $t->string('pdf_token')->nullable();
            $t->unsignedInteger('nb_panneaux_abidjan')->default(0); $t->unsignedInteger('nb_panneaux_interieur')->default(0);
            $t->string('pdf_path_abidjan')->nullable(); $t->string('pdf_token_abidjan')->nullable();
            $t->string('pdf_path_interieur')->nullable(); $t->string('pdf_token_interieur')->nullable();
            $t->string('brevo_campaign_id')->nullable();
            $t->text('erreur')->nullable(); $t->timestamp('envoye_at')->nullable(); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envoi(array $attr = []): DiffusionEnvoi
    {
        return DiffusionEnvoi::create($attr + [
            'creneau' => '2026-11-15', 'periode_debut' => '2026-11-15', 'periode_fin' => '2026-11-30',
            'mode' => DiffusionEnvoi::MODE_TEST, 'statut' => DiffusionEnvoi::STATUT_ENVOYE,
            'nb_destinataires' => 2, 'nb_panneaux' => 258, 'brevo_campaign_id' => 20,
            'envoye_at' => now()->subMinutes(50),
        ]);
    }

    /** L'équipe doit être prévenue exactement $fois fois. */
    private function equipePrevenue(int $fois): void
    {
        $this->mock(NotificationMailer::class, function ($m) use ($fois) {
            $m->shouldReceive('sendSilently')->times($fois)
                ->withArgs(fn($email, $mail) => $mail instanceof DiffusionDisponibilitesMail)
                ->andReturn(true);
        });
    }

    private function service(): DiffusionDisponibilitesService
    {
        return app(DiffusionDisponibilitesService::class);
    }

    // ── A, B, C : repérage et cause ─────────────────────────────────

    public function test_le_test_de_16h10_suspendu_faute_de_quota(): void
    {
        $envoi = $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->with(20)->once()->andReturn(['statut' => 'suspended', 'partis' => 0, 'invalides' => 0]);
            $m->shouldReceive('envoisRestants')->andReturn(0);
        });
        $this->equipePrevenue(1);

        $this->assertSame(1, $this->service()->verifierEnvoisRecents());

        $envoi->refresh();
        $this->assertSame(DiffusionEnvoi::STATUT_SUSPENDU, $envoi->statut);
        $this->assertSame(
            "Cause probable : quota d'envois épuisé. Partis : 0 sur 2 · adresses invalides : 0 · envois restants sur le compte Brevo : 0.",
            $envoi->erreur
        );
        $this->assertSame(1, DiffusionEnvoi::filtre('echecs')->count());
    }

    public function test_l_envoi_du_matin_les_deux_causes(): void
    {
        $this->envoi(['nb_destinataires' => 830, 'brevo_campaign_id' => 8, 'envoye_at' => now()->subHours(7)]);
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->with(8)->andReturn(['statut' => 'suspended', 'partis' => 300, 'invalides' => 46]);
            $m->shouldReceive('envoisRestants')->andReturn(0);
        });
        $this->equipePrevenue(1);

        $this->service()->verifierEnvoisRecents();

        $this->assertSame(
            "Cause probable : quota d'envois épuisé et trop d'adresses invalides (Brevo exige moins de 2 %). "
            . 'Partis : 300 sur 830 · adresses invalides : 46 (15,3 %) · envois restants sur le compte Brevo : 0.',
            DiffusionEnvoi::first()->erreur
        );
    }

    public function test_sans_cause_visible_panora_le_dit(): void
    {
        $this->envoi(['nb_destinataires' => 465]);
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->andReturn(['statut' => 'suspended', 'partis' => 120, 'invalides' => 1]);
            $m->shouldReceive('envoisRestants')->andThrow(new BrevoException('Brevo injoignable'));
        });
        $this->equipePrevenue(1);

        $this->service()->verifierEnvoisRecents();

        $this->assertSame(
            'Brevo ne donne pas la raison : voir la campagne dans Brevo. Partis : 120 sur 465 · adresses invalides : 1 (0,8 %).',
            DiffusionEnvoi::first()->erreur
        );
    }

    public function test_une_campagne_terminee_n_est_plus_relue(): void
    {
        $envoi = $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->once()->andReturn(['statut' => 'sent', 'partis' => 2, 'invalides' => 0]);
        });
        $this->equipePrevenue(0);

        $this->assertSame(0, $this->service()->verifierEnvoisRecents());
        $this->assertSame(0, $this->service()->verifierEnvoisRecents());   // 2e passage : Brevo pas rappelé
        $this->assertSame(DiffusionEnvoi::STATUT_ENVOYE, $envoi->fresh()->statut);
    }

    public function test_une_campagne_encore_en_cours_est_relue_au_passage_suivant(): void
    {
        $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->twice()->andReturn(
                ['statut' => 'in_process', 'partis' => 0, 'invalides' => 0],
                ['statut' => 'suspended', 'partis' => 0, 'invalides' => 0],
            );
            $m->shouldReceive('envoisRestants')->andReturn(0);
        });
        $this->equipePrevenue(1);

        $this->assertSame(0, $this->service()->verifierEnvoisRecents());
        $this->assertSame(1, $this->service()->verifierEnvoisRecents());
    }

    public function test_seuls_les_envois_des_dernieres_24h_sauf_rattrapage(): void
    {
        $this->envoi(['envoye_at' => now()->subHours(30)]);
        $this->envoi(['brevo_campaign_id' => null]);                       // jamais parti chez Brevo
        $this->envoi(['statut' => DiffusionEnvoi::STATUT_ECHEC]);          // déjà en échec
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->with(20)->once()->andReturn(['statut' => 'sent', 'partis' => 2, 'invalides' => 0]);
        });
        $this->equipePrevenue(0);

        $this->service()->verifierEnvoisRecents();          // 24 h : rien à relire
        $this->service()->verifierEnvoisRecents(48);        // rattrapage : l'envoi de 30 h est relu
    }

    public function test_brevo_injoignable_rien_ne_change(): void
    {
        $envoi = $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->andThrow(new BrevoException('Délai dépassé'));
        });
        $this->equipePrevenue(0);

        $this->assertSame(0, $this->service()->verifierEnvoisRecents());
        $this->assertSame(DiffusionEnvoi::STATUT_ENVOYE, $envoi->fresh()->statut);
    }

    // ── D : un envoi suspendu compte comme parti ────────────────────

    public function test_un_envoi_suspendu_compte_comme_parti(): void
    {
        $this->envoi(['mode' => DiffusionEnvoi::MODE_MANUEL, 'statut' => DiffusionEnvoi::STATUT_SUSPENDU, 'nb_destinataires' => 830]);

        $this->assertSame(830, $this->service()->dernierEnvoiReussi()?->nb_destinataires);
        $this->assertContains(DiffusionEnvoi::STATUT_SUSPENDU, DiffusionEnvoi::STATUTS_PARTIS);

        // Mêmes statuts pour l'automatique déjà parti et les PDF à garder.
        $source = file_get_contents(base_path('app/Services/Diffusion/DiffusionDisponibilitesService.php'));
        $this->assertStringContainsString('[...DiffusionEnvoi::STATUTS_PARTIS, DiffusionEnvoi::STATUT_EN_COURS]', $source);
        $this->assertMatchesRegularExpression("/DiffusionEnvoi::whereIn\('statut', DiffusionEnvoi::STATUTS_PARTIS\)\s+->whereDate\('periode_fin'/", $source);
    }

    // ── E : quota contrôlé avant l'envoi ────────────────────────────

    public function test_quota_insuffisant_rien_ne_part(): void
    {
        $envoi = $this->envoi(['statut' => DiffusionEnvoi::STATUT_PREPARE, 'brevo_campaign_id' => null, 'envoye_at' => null]);
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('emailsDeLaListe')->with(4)->andReturn(['commercial@cible-ci.com', 'romulad.cible@gmail.com']);
            $m->shouldReceive('envoisRestants')->andReturn(0);
            $m->shouldNotReceive('creerCampagne');
            $m->shouldNotReceive('envoyerCampagne');
        });
        $this->equipePrevenue(1);

        $envoi = $this->service()->envoyer($envoi);

        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $envoi->statut);
        $this->assertStringStartsWith("Brevo n'a plus que 0 envoi(s) disponible(s), il en faut 2 : rien n'est parti.", $envoi->erreur);
    }

    public function test_quota_suffisant_illisible_ou_controle_coupe_ne_bloque_pas(): void
    {
        $restants = [500, null, new BrevoException('Brevo injoignable')];
        $this->mock(BrevoClient::class, function ($m) use (&$restants) {
            $m->shouldReceive('envoisRestants')->times(3)->andReturnUsing(function () use (&$restants) {
                $r = array_shift($restants);
                if ($r instanceof \Throwable) {
                    throw $r;
                }
                return $r;
            });
        });
        $s = $this->service();
        $verifier = new \ReflectionMethod($s, 'verifierQuota');

        $verifier->invoke($s, 465);   // 500 restants
        $verifier->invoke($s, 465);   // Brevo n'annonce rien
        $verifier->invoke($s, 465);   // Brevo ne répond pas

        config(['diffusion.controle_quota' => false]);
        $verifier->invoke($s, 465);   // contrôle coupé : Brevo pas interrogé (4e appel refusé par le mock)

        $this->assertTrue(true);
    }

    // ── Lecture des réponses Brevo ──────────────────────────────────

    public function test_lecture_de_la_campagne_et_des_envois_restants(): void
    {
        config(['brevo.api_key' => 'cle-de-test', 'brevo.sender.email' => 'commercial@cible-ci.com', 'brevo.base_url' => 'https://api.brevo.com/v3']);
        Http::fake([
            'api.brevo.com/v3/emailCampaigns/20*' => Http::response([
                'id' => 20, 'status' => 'suspended',
                'statistics' => ['globalStats' => ['sent' => 0, 'delivered' => 0, 'hardBounces' => 0, 'softBounces' => 0]],
            ]),
            'api.brevo.com/v3/account' => Http::response([
                'email' => 'commercial@cible-ci.com',
                'plan'  => [
                    ['type' => 'free', 'creditsType' => 'sendLimit', 'credits' => 0],
                    ['type' => 'sms', 'creditsType' => 'sendLimit', 'credits' => 15],
                ],
            ]),
        ]);
        $brevo = app(BrevoClient::class);

        $this->assertSame(['statut' => 'suspended', 'partis' => 0, 'invalides' => 0], $brevo->campagne(20));
        $this->assertSame(0, $brevo->envoisRestants());   // le forfait SMS ne compte pas
        Http::assertSent(fn($r) => str_contains($r->url(), '/emailCampaigns/20') && str_contains($r->url(), 'statistics=globalStats'));
    }

    // ── Mail d'alerte ───────────────────────────────────────────────

    public function test_le_mail_d_alerte_explique_la_suspension(): void
    {
        $envoi = $this->envoi([
            'mode' => DiffusionEnvoi::MODE_MANUEL, 'statut' => DiffusionEnvoi::STATUT_SUSPENDU,
            'erreur' => "Cause probable : quota d'envois épuisé. Partis : 300 sur 830.",
        ]);
        config(['diffusion.mode' => 'production']);
        $mail = new DiffusionDisponibilitesMail($envoi, BrevoClient::lienRapportCampagne(20));

        $this->assertSame("⚠️ Brevo a suspendu l'envoi des disponibilités — du 15 au 30 novembre 2026", $mail->envelope()->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Envoi suspendu par Brevo', $html);
        $this->assertStringContainsString('Partis : 300 sur 830.', $html);
        $this->assertStringContainsString('Voir la campagne dans Brevo', $html);
        $this->assertStringContainsString('marketing-reports/email/20/overview', $html);
        $this->assertStringContainsString('relancer depuis Panora renvoie le mail à tous les destinataires', $html);
    }
}
