<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Services\Brevo\BrevoClient;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Catalogue de test reçu le 2026-11-03 : il manquait le 2e numéro
 * commercial, le logo, et Stéphane n'était pas en copie.
 *   • numéros et logo : Panora les fournit au modèle Brevo (charte, donc
 *     .env) ; le modèle ne les écrit plus en dur ;
 *   • copie : MAIL_COPIE_COMMERCIALE reçoit chaque vrai catalogue comme un
 *     client (une campagne Brevo n'a pas de copie), sans compter parmi eux.
 */
class DiffusionSignatureCatalogueTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    private const CLIENTS = 3;
    private const TESTS = 4;
    private const STEPHANE = 'stephane.oponou@cible-ci.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        Cache::flush();

        config([
            'diffusion.mode'                => 'production',
            'diffusion.controle_quota'      => true,
            'brevo.lists.clients'           => self::CLIENTS,
            'brevo.lists.tests'             => self::TESTS,
            'brevo.template_id'             => 14,
            'mails.copie_commerciale'       => self::STEPHANE,
            'charte.nom'                    => 'CIBLE',
            'charte.coordonnees.telephones' => '+225 07 98 49 66 74 · +225 07 00 78 06 27 · +225 27 22 20 80 08',
            'charte.logos.clair'            => 'images/logol.png',
        ]);
        DB::table('users')->insert(['id' => 4, 'name' => 'Stéphane Oponou', 'email' => self::STEPHANE, 'role' => 'admin']);

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

    private function envoi(string $mode = DiffusionEnvoi::MODE_MANUEL): DiffusionEnvoi
    {
        return DiffusionEnvoi::create([
            'creneau' => '2026-11-15', 'periode_debut' => '2026-11-15', 'periode_fin' => '2026-11-30',
            'mode' => $mode, 'statut' => DiffusionEnvoi::STATUT_PREPARE, 'nb_panneaux' => 260,
            'pdf_path' => 'diffusions/complet.pdf', 'pdf_token' => str_repeat('a', 48),
        ]);
    }

    private function prive(DiffusionDisponibilitesService $s, string $methode, ...$args)
    {
        return (new \ReflectionMethod($s, $methode))->invoke($s, ...$args);
    }

    /** contenuCampagne() est privée : c'est le contrat avec Brevo qu'on vérifie. */
    private function params(DiffusionEnvoi $envoi): array
    {
        return $this->prive(app(DiffusionDisponibilitesService::class), 'contenuCampagne', $envoi, self::CLIENTS)['params'] ?? [];
    }

    // ── Numéros et logo ─────────────────────────────────────────────

    public function test_les_trois_numeros_et_le_logo_partent_a_brevo(): void
    {
        $params = $this->params($this->envoi());

        // Insécables DANS chaque numéro, jamais autour des « · ».
        $n = "\u{00A0}";
        $this->assertSame(
            "+225{$n}07{$n}98{$n}49{$n}66{$n}74 · +225{$n}07{$n}00{$n}78{$n}06{$n}27 · +225{$n}27{$n}22{$n}20{$n}80{$n}08",
            $params['TELEPHONES']
        );
        $this->assertStringStartsWith('http', $params['LOGO']);
        $this->assertStringEndsWith('/images/logol.png', $params['LOGO']);
    }

    public function test_sans_numero_ni_logo_dans_la_charte_rien_n_est_envoye(): void
    {
        config(['charte.coordonnees.telephones' => '', 'charte.logos.clair' => null]);
        $params = $this->params($this->envoi());

        // Le modèle teste leur présence : pas de ligne vide, pas d'image cassée.
        $this->assertArrayNotHasKey('TELEPHONES', $params);
        $this->assertArrayNotHasKey('LOGO', $params);
    }

    public function test_le_modele_brevo_lit_les_numeros_et_le_logo_de_panora(): void
    {
        $modele = file_get_contents(base_path('docs/diffusion/modele-brevo-disponibilites-leger.html'));

        $this->assertStringContainsString('{{ params.TELEPHONES }}', $modele);
        $this->assertSame(2, substr_count($modele, '<img src="{{ params.LOGO }}"'), 'Logo en haut et dans la signature.');
        // Plus aucun numéro en dur : un changement de numéro ne passe que par le .env.
        $this->assertDoesNotMatchRegularExpression('/\d{2} \d{2} \d{2} \d{2} \d{2}/', $modele);
    }

    // ── Copie à Stéphane ────────────────────────────────────────────

    public function test_stephane_recoit_le_catalogue_sans_compter_parmi_les_clients(): void
    {
        $clients = app(DiffusionDisponibilitesService::class)->destinataires()->pluck('email')->all();
        $this->assertNotEmpty($clients);
        $this->assertNotContains(self::STEPHANE, $clients);

        $inscrits = new \ArrayObject();
        $retires = new \ArrayObject();
        $this->mock(BrevoClient::class, function ($m) use ($clients, $inscrits, $retires) {
            $m->shouldReceive('enregistrerContact')->andReturnUsing(
                function ($email, $attributs, $listes) use ($inscrits) { $inscrits[$email] = [$attributs, $listes]; }
            );
            $m->shouldReceive('emailsDeLaListe')->with(self::CLIENTS)->andReturn([...$clients, self::STEPHANE, 'ancien@client.ci']);
            $m->shouldReceive('retirerDeLaListe')->andReturnUsing(
                function ($liste, $emails) use ($retires) { $retires->exchangeArray($emails); }
            );
        });
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertSame([self::STEPHANE], $s->copiesCatalogue());
        $this->assertSame(count($clients), $this->prive($s, 'synchroniserDestinataires', self::CLIENTS, false));
        $this->assertSame([['CONTACT' => 'Stéphane Oponou', 'SOCIETE' => 'CIBLE'], [self::CLIENTS]], $inscrits[self::STEPHANE]);
        // Stéphane reste dans la liste ; seul l'ancien client en sort.
        $this->assertSame(['ancien@client.ci'], $retires->getArrayCopy());
    }

    public function test_une_copie_desinscrite_n_est_pas_remise_dans_la_liste(): void
    {
        DB::table('diffusion_desinscriptions')->insert(['email' => self::STEPHANE, 'motif' => 'desinscription', 'source' => 'brevo']);

        $inscrits = new \ArrayObject();
        $this->mock(BrevoClient::class, function ($m) use ($inscrits) {
            $m->shouldReceive('enregistrerContact')->andReturnUsing(
                function ($email) use ($inscrits) { $inscrits[$email] = true; }
            );
            $m->shouldReceive('emailsDeLaListe')->andReturn([]);
        });
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertSame([], $s->copiesCatalogue());
        $this->prive($s, 'synchroniserDestinataires', self::CLIENTS, false);
        $this->assertArrayNotHasKey(self::STEPHANE, $inscrits->getArrayCopy());
    }

    public function test_un_test_n_ajoute_pas_la_copie(): void
    {
        // La liste « Tests internes » se gère à la main dans Brevo.
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('emailsDeLaListe')->with(self::TESTS)->andReturn(['commercial@cible-ci.com', 'romulad.cible@gmail.com']);
            $m->shouldNotReceive('enregistrerContact');
        });

        $this->assertSame(2, $this->prive(app(DiffusionDisponibilitesService::class), 'synchroniserDestinataires', self::TESTS, true));
    }

    public function test_le_quota_compte_la_copie(): void
    {
        $nbClients = app(DiffusionDisponibilitesService::class)->destinataires()->count();
        $this->mock(BrevoClient::class, function ($m) use ($nbClients) {
            $m->shouldReceive('enregistrerContact');
            $m->shouldReceive('emailsDeLaListe')->andReturn([]);
            $m->shouldReceive('envoisRestants')->andReturn($nbClients);   // juste assez pour les clients seuls
            $m->shouldNotReceive('creerCampagne');
        });

        $envoi = app(DiffusionDisponibilitesService::class)->envoyer($this->envoi());

        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $envoi->statut);
        $this->assertStringContainsString('il en faut ' . ($nbClients + 1), $envoi->erreur);
    }
}
