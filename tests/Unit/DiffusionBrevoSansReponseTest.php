<?php

namespace Tests\Unit;

use App\Mail\DiffusionDisponibilitesMail;
use App\Models\DiffusionEnvoi;
use App\Services\Brevo\BrevoClient;
use App\Services\Brevo\BrevoException;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use App\Services\NotificationMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Brevo ne répond pas à temps (2026-11-03).
 *
 * Constaté le 3/11 à 15h05 : Brevo a mis plus de 30 s à créer la campagne
 * d'un test, l'a créée quand même (deux brouillons), et Panora affichait
 * « Erreur interne : cURL error 28 ». Le vrai danger : un ordre d'envoi
 * sans réponse a pu partir ; un « Échec » inviterait à relancer, et les
 * clients recevraient le catalogue deux fois. Désormais :
 *   • les appels qui envoient attendent 120 s, les lectures 30 s ;
 *   • création sans réponse → « Échec », rien n'est parti, brouillon à supprimer ;
 *   • ordre d'envoi sans réponse → « À vérifier », boutons bloqués ;
 *   • dispos:verifier-envois tranche : envoyé, suspendu ou échec.
 *
 * NB : sous Xdebug, une connexion coupée simulée par Http::fake fait
 * planter PHP sur le poste Windows ; ici Brevo est simulé par un mock.
 */
class DiffusionBrevoSansReponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 15:05:00');
        Cache::flush();

        config([
            'diffusion.mode'               => 'test',
            'diffusion.controle_quota'     => true,
            'diffusion.confirmation_email' => 'commercial@cible-ci.com',
            'brevo.lists.clients'          => 3,
            'brevo.lists.tests'            => 4,
            'brevo.template_id'            => 14,
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
            'mode' => DiffusionEnvoi::MODE_TEST, 'statut' => DiffusionEnvoi::STATUT_A_VERIFIER,
            'nb_destinataires' => 2, 'nb_panneaux' => 258, 'brevo_campaign_id' => 21,
            'envoye_at' => now()->subMinutes(5),
        ]);
    }

    /** Un test prêt à partir : PDF faits, liste de test à 2 adresses, quota suffisant. */
    private function envoiPret(): DiffusionEnvoi
    {
        return $this->envoi(['statut' => DiffusionEnvoi::STATUT_PREPARE, 'nb_destinataires' => 0, 'brevo_campaign_id' => null, 'envoye_at' => null]);
    }

    private function brevoPret($m): void
    {
        $m->shouldReceive('emailsDeLaListe')->with(4)->andReturn(['commercial@cible-ci.com', 'romulad.cible@gmail.com']);
        $m->shouldReceive('envoisRestants')->andReturn(300);
    }

    private function delaiDepasse(): ConnectionException
    {
        return new ConnectionException('cURL error 28: Operation timed out after 120001 milliseconds with 0 bytes received');
    }

    /** L'équipe doit être prévenue exactement $fois fois ; renvoie les mails reçus. */
    private function equipePrevenue(int $fois): \ArrayObject
    {
        $mails = new \ArrayObject();
        $this->mock(NotificationMailer::class, function ($m) use ($fois, $mails) {
            $m->shouldReceive('sendSilently')->times($fois)
                ->withArgs(function ($email, $mail) use ($mails) {
                    $mails->append($mail);

                    return $mail instanceof DiffusionDisponibilitesMail;
                })
                ->andReturn(true);
        });

        return $mails;
    }

    private function service(): DiffusionDisponibilitesService
    {
        return app(DiffusionDisponibilitesService::class);
    }

    // ── Délais et détection ─────────────────────────────────────────

    public function test_les_appels_qui_envoient_attendent_120_s_les_lectures_30_s(): void
    {
        config(['brevo.api_key' => 'cle-de-test', 'brevo.sender.email' => 'commercial@cible-ci.com']);
        $client = new \ReflectionMethod(BrevoClient::class, 'client');
        $brevo = app(BrevoClient::class);

        $this->assertSame(120, $client->invoke($brevo, true)->getOptions()['timeout']);
        $this->assertSame(30, $client->invoke($brevo)->getOptions()['timeout']);

        // Les trois envois, et eux seuls, passent par le délai long.
        $source = file_get_contents(base_path('app/Services/Brevo/BrevoClient.php'));
        $this->assertSame(3, substr_count($source, '$this->client(envoi: true)'));
        $this->assertStringContainsString("\$this->client(envoi: true)->post('/emailCampaigns', \$payload)", $source);
        $this->assertStringContainsString('$this->client(envoi: true)->post("/emailCampaigns/{$campaignId}/sendNow")', $source);
        $this->assertStringContainsString("\$this->client(envoi: true)->post('/smtp/email', \$payload)", $source);
    }

    public function test_sans_reponse_ne_vise_que_le_silence_de_brevo(): void
    {
        $this->assertTrue(BrevoClient::sansReponse($this->delaiDepasse()));
        $this->assertFalse(BrevoClient::sansReponse(new BrevoException('Brevo a refusé (HTTP 400)')));
        $this->assertFalse(BrevoClient::sansReponse(new \RuntimeException('autre')));
    }

    // ── Création sans réponse ───────────────────────────────────────

    public function test_creation_sans_reponse_echec_clair_et_rien_ne_part(): void
    {
        $envoi = $this->envoiPret();
        $this->mock(BrevoClient::class, function ($m) {
            $this->brevoPret($m);
            $m->shouldReceive('creerCampagne')->once()->andThrow($this->delaiDepasse());
            $m->shouldNotReceive('envoyerCampagne');
        });
        $this->equipePrevenue(1);

        $envoi = $this->service()->envoyer($envoi);

        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $envoi->statut);
        $this->assertSame(
            "Brevo n'a pas répondu à temps à la création de la campagne : rien n'est parti. "
            . "Brevo a pu créer quand même le brouillon « Disponibilités du 15 au 30 novembre 2026 — test #{$envoi->id} » : "
            . 'le supprimer dans Brevo, puis relancer.',
            $envoi->erreur
        );
        $this->assertNull($this->service()->demandeEnAttente());   // on peut relancer
    }

    // ── Ordre d'envoi sans réponse ──────────────────────────────────

    public function test_ordre_d_envoi_sans_reponse_a_verifier_jamais_echec(): void
    {
        $envoi = $this->envoiPret();
        $this->mock(BrevoClient::class, function ($m) {
            $this->brevoPret($m);
            $m->shouldReceive('creerCampagne')->once()->andReturn(21);
            $m->shouldReceive('envoyerCampagne')->with(21)->once()->andThrow($this->delaiDepasse());
        });
        $mails = $this->equipePrevenue(1);

        $envoi = $this->service()->envoyer($envoi);

        $this->assertSame(DiffusionEnvoi::STATUT_A_VERIFIER, $envoi->statut);
        $this->assertSame('21', (string) $envoi->brevo_campaign_id);
        $this->assertSame(2, $envoi->nb_destinataires);
        $this->assertTrue($envoi->envoye_at->equalTo(now()));
        $this->assertStringStartsWith("Brevo n'a pas répondu à l'ordre d'envoi : la campagne est peut-être partie.", $envoi->erreur);
        $this->assertSame(0, DiffusionEnvoi::filtre('echecs')->count());
        $this->assertStringContainsString('non confirmé par Brevo', $mails[0]->envelope()->subject);

        // Les boutons d'envoi sont bloqués tant que Brevo n'a pas tranché.
        $this->assertSame($envoi->id, $this->service()->demandeEnAttente()?->id);
    }

    public function test_un_refus_net_de_l_ordre_d_envoi_reste_un_echec(): void
    {
        $envoi = $this->envoiPret();
        $this->mock(BrevoClient::class, function ($m) {
            $this->brevoPret($m);
            $m->shouldReceive('creerCampagne')->andReturn(21);
            $m->shouldReceive('envoyerCampagne')->andThrow(new BrevoException('Brevo a refusé la envoi de la campagne 21 (HTTP 400) : campaign not ready'));
        });
        $this->equipePrevenue(1);

        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $this->service()->envoyer($envoi)->statut);
    }

    public function test_un_envoi_a_verifier_compte_comme_parti_et_bloque_24_h(): void
    {
        $manuel = $this->envoi(['mode' => DiffusionEnvoi::MODE_MANUEL, 'nb_destinataires' => 465, 'envoye_at' => now()->subHours(2)]);

        $this->assertContains(DiffusionEnvoi::STATUT_A_VERIFIER, DiffusionEnvoi::STATUTS_PARTIS);
        $this->assertSame($manuel->id, $this->service()->dernierEnvoiReussi()?->id);   // « tapez ENVOYER »
        $this->assertSame($manuel->id, $this->service()->demandeEnAttente()?->id);

        Carbon::setTestNow(now()->addHours(21));   // lancé il y a 23 h
        $this->assertSame($manuel->id, $this->service()->demandeEnAttente()?->id);
        Carbon::setTestNow(now()->addHours(2));    // il y a 25 h : Panora ne le relit plus
        $this->assertNull($this->service()->demandeEnAttente());
    }

    // ── Tranché par dispos:verifier-envois ──────────────────────────

    public function test_brevo_dit_parti_l_envoi_passe_envoye(): void
    {
        $envoi = $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->with(21)->once()->andReturn(['statut' => 'in_process', 'partis' => 0, 'invalides' => 0]);
        });
        $mails = $this->equipePrevenue(1);

        $this->assertSame(0, $this->service()->verifierEnvoisRecents());

        $envoi->refresh();
        $this->assertSame(DiffusionEnvoi::STATUT_ENVOYE, $envoi->statut);
        $this->assertNull($envoi->erreur);
        $this->assertSame('[TEST] ✅ Test réussi — disponibilités du 15 au 30 novembre 2026', $mails[0]->envelope()->subject);
    }

    public function test_encore_brouillon_15_minutes_apres_rien_n_est_parti(): void
    {
        $envoi = $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->with(21)->twice()->andReturn(['statut' => 'draft', 'partis' => null, 'invalides' => null]);
        });
        $this->equipePrevenue(1);

        // 5 minutes après : Brevo traite peut-être encore l'ordre, on attend.
        $this->service()->verifierEnvoisRecents();
        $this->assertSame(DiffusionEnvoi::STATUT_A_VERIFIER, $envoi->fresh()->statut);

        Carbon::setTestNow(now()->addMinutes(10));
        $this->service()->verifierEnvoisRecents();

        $envoi->refresh();
        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $envoi->statut);
        $this->assertSame(
            "Brevo n'a pas lancé l'envoi : rien n'est parti. Supprimer le brouillon n° 21 dans Brevo, puis relancer depuis Panora.",
            $envoi->erreur
        );
        $this->assertNull($this->service()->demandeEnAttente());
    }

    public function test_brevo_l_a_suspendu(): void
    {
        $envoi = $this->envoi(['nb_destinataires' => 465]);
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->andReturn(['statut' => 'suspended', 'partis' => 300, 'invalides' => 2]);
            $m->shouldReceive('envoisRestants')->andReturn(0);
        });
        $this->equipePrevenue(1);

        $this->assertSame(1, $this->service()->verifierEnvoisRecents());
        $this->assertSame(DiffusionEnvoi::STATUT_SUSPENDU, $envoi->fresh()->statut);
        $this->assertStringStartsWith("Cause probable : quota d'envois épuisé.", $envoi->fresh()->erreur);
    }

    public function test_campagne_annulee_dans_brevo(): void
    {
        $envoi = $this->envoi();
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->andReturn(['statut' => 'cancelled', 'partis' => 12, 'invalides' => 0]);
        });
        $this->equipePrevenue(1);

        $this->service()->verifierEnvoisRecents();

        $envoi->refresh();
        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $envoi->statut);
        $this->assertStringStartsWith('La campagne n° 21 a été annulée dans Brevo.', $envoi->erreur);
    }

    public function test_brevo_toujours_muet_l_envoi_reste_a_verifier(): void
    {
        $envoi = $this->envoi(['envoye_at' => now()->subHour()]);
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('campagne')->andThrow($this->delaiDepasse());
        });
        $this->equipePrevenue(0);

        $this->service()->verifierEnvoisRecents();

        $this->assertSame(DiffusionEnvoi::STATUT_A_VERIFIER, $envoi->fresh()->statut);
    }

    // ── Mail à l'équipe ─────────────────────────────────────────────

    public function test_le_mail_dit_de_ne_pas_relancer(): void
    {
        $envoi = $this->envoi([
            'mode' => DiffusionEnvoi::MODE_MANUEL, 'nb_destinataires' => 465,
            'erreur' => "Brevo n'a pas répondu à l'ordre d'envoi : la campagne est peut-être partie. Ne pas relancer : Panora vérifie chez Brevo toutes les 15 minutes.",
        ]);
        config(['diffusion.mode' => 'production']);
        $mail = new DiffusionDisponibilitesMail($envoi, BrevoClient::lienRapportCampagne(21));

        $this->assertSame('⏳ Envoi des disponibilités non confirmé par Brevo — du 15 au 30 novembre 2026', $mail->envelope()->subject);

        $html = $mail->render();
        $this->assertStringContainsString("Brevo n&#039;a pas confirmé le départ", $html);
        $this->assertStringContainsString('Ne pas relancer : si la campagne est partie', $html);
        $this->assertStringContainsString('Destinataires prévus', $html);
        $this->assertStringContainsString('Lancé le', $html);
        $this->assertStringContainsString('marketing-reports/email/21/overview', $html);
        $this->assertStringNotContainsString('Cliquer sur « Envoyer maintenant »', $html);
    }
}
