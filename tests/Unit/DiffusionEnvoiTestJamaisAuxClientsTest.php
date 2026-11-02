<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Services\Brevo\BrevoClient;
use App\Services\Brevo\BrevoException;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Tests\TestCase;

/**
 * Incident du 2026-11-02 : avec DIFFUSION_MODE=production, le bouton
 * « Envoyer un test » visait la liste CLIENTS (la liste ne dépendait que du
 * mode du serveur). Panora y a recopié 830 clients et le catalogue « [TEST] »
 * est parti à 300 d'entre eux avant que Brevo ne suspende.
 *
 * Règle : un envoi de test ne vise que « Tests internes », et Panora n'y
 * écrit jamais.
 */
class DiffusionEnvoiTestJamaisAuxClientsTest extends TestCase
{
    private const CLIENTS = 3;
    private const TESTS = 4;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'diffusion.mode'      => 'production',
            'brevo.lists.clients' => self::CLIENTS,
            'brevo.lists.tests'   => self::TESTS,
        ]);
    }

    private function prive(DiffusionDisponibilitesService $s, string $methode, ...$args)
    {
        return (new \ReflectionMethod($s, $methode))->invoke($s, ...$args);
    }

    public function test_le_bouton_test_reste_un_test_meme_en_production(): void
    {
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertTrue($s->estEnvoiTest(new DiffusionEnvoi(['mode' => DiffusionEnvoi::MODE_TEST])));
        $this->assertFalse($s->estEnvoiTest(new DiffusionEnvoi(['mode' => DiffusionEnvoi::MODE_MANUEL])));

        $this->assertSame(self::TESTS, $this->prive($s, 'listeCible', true));
        $this->assertSame(self::CLIENTS, $this->prive($s, 'listeCible', false));
    }

    public function test_un_test_ne_recopie_jamais_les_clients(): void
    {
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('emailsDeLaListe')->with(self::TESTS)->andReturn(['commercial@cible-ci.com', 'romulad.cible@gmail.com']);
            $m->shouldNotReceive('enregistrerContact');
            $m->shouldNotReceive('retirerDeLaListe');
        });
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertSame(2, $this->prive($s, 'synchroniserDestinataires', self::TESTS, true));
    }

    public function test_un_test_refuse_de_viser_la_liste_des_clients(): void
    {
        config(['brevo.lists.tests' => self::CLIENTS]);
        $s = app(DiffusionDisponibilitesService::class);

        $this->expectException(BrevoException::class);
        $this->prive($s, 'listeCible', true);
    }

    public function test_l_envoi_decide_avec_la_regle_unique(): void
    {
        $source = file_get_contents(base_path('app/Services/Diffusion/DiffusionDisponibilitesService.php'));
        $envoyer = substr($source, strpos($source, 'public function envoyer('), 1500);

        $this->assertStringContainsString('$test = $this->estEnvoiTest($envoi);', $envoyer);
        $this->assertStringContainsString('$this->listeCible($test)', $envoyer);
        $this->assertStringContainsString('$this->synchroniserDestinataires($listeId, $test)', $envoyer);
    }
}
