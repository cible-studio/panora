<?php

namespace Tests\Unit;

use App\Services\Promos\PromoService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Page d'arrivée des mails de promotion (2026-11-25) : le lien du mail mène
 * à /offre/{code}[/{format}] au lieu d'un « mailto: », pour que le clic soit
 * compté (Brevo et Panora). La page ouvre la réponse préremplie.
 */
class PromoPageTest extends TestCase
{
    private const NAVIGATEUR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_11_25_100000_create_promo_clics_table.php'))->up();
    }

    public function test_la_page_du_format_compte_le_clic_et_prepare_la_reponse(): void
    {
        $page = $this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35/50m2');

        $page->assertOk();
        $page->assertHeader('X-Robots-Tag', 'noindex');
        $html = html_entity_decode($page->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString("-35\u{00A0}% sur nos panneaux à l'intérieur du pays", $html);
        $this->assertStringContainsString('Vous avez choisi le <strong>50 m²</strong>', $html);
        $this->assertStringContainsString('mailto:commercial@cible-ci.com?subject=', $html);
        $this->assertStringContainsString(rawurlencode('Format souhaité (50 m², 12 m² ou 6 m²) : 50 m²'), $html, 'le format est déjà écrit');
        $this->assertStringContainsString('tel:+2250769304602', $html);

        $clic = DB::table('promo_clics')->first();
        $this->assertSame('interieur-35', $clic->promo);
        $this->assertSame('50m2', $clic->format);
        $this->assertEquals(0, $clic->robot);
        $this->assertSame(16, strlen($clic->visiteur), "empreinte courte, pas l'adresse IP");
    }

    /** PROMO_COPIE (25/11) : les adresses en copie sont déjà remplies dans le mail prérempli. */
    public function test_les_adresses_en_copie(): void
    {
        config(['promos.interieur-35.copie' => ['premier@exemple.ci', 'pas-une-adresse', 'second@exemple.ci']]);

        $html = html_entity_decode($this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35/12m2')->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('mailto:commercial@cible-ci.com?cc=premier@exemple.ci,second@exemple.ci&subject=', $html, 'une adresse invalide est écartée');
        $this->assertSame(4, substr_count($html, '?cc=premier@exemple.ci,second@exemple.ci&'), 'le bouton et les trois formats');

        config(['promos.interieur-35.copie' => []]);
        $this->assertStringNotContainsString('cc=', app(PromoService::class)->lienReponse(config('promos.interieur-35')));
    }

    public function test_sans_format_et_format_inconnu(): void
    {
        $this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35')->assertOk();
        $this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35/99m2')->assertOk();

        $this->assertSame([null, null], DB::table('promo_clics')->orderBy('id')->pluck('format')->all());
        $this->get('/offre/promo-inexistante')->assertNotFound();
    }

    public function test_les_robots_et_les_verifications_de_lien_sont_a_part(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Barracuda Sentinel)')->get('/offre/interieur-35/12m2')->assertOk();
        $this->withHeader('User-Agent', self::NAVIGATEUR)->head('/offre/interieur-35/12m2');
        $this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35/12m2');
        $this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35/6m2');

        $bilan = app(PromoService::class)->bilan('interieur-35');
        $this->assertSame(2, $bilan['clics'], 'le HEAD ne compte pas, le robot est à part');
        $this->assertSame(1, $bilan['robots']);
        $this->assertSame(1, $bilan['visiteurs'], 'même navigateur, même adresse : un visiteur');
        $this->assertSame(['12m2' => 1, '6m2' => 1], $bilan['formats']);
    }

    public function test_la_commande_donne_le_bilan(): void
    {
        $this->withHeader('User-Agent', self::NAVIGATEUR)->get('/offre/interieur-35/50m2');

        $this->artisan('promos:clics', ['code' => 'interieur-35'])
            ->expectsOutputToContain('1 clic(s), 1 visiteur(s) distinct(s)')
            ->assertSuccessful();
        $this->artisan('promos:clics', ['code' => 'inconnue'])->assertFailed();
    }

    public function test_une_offre_terminee_le_dit(): void
    {
        config(['promos.interieur-35.active' => false]);

        $html = html_entity_decode($this->get('/offre/interieur-35/50m2')->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Cette offre est terminée', $html);
        $this->assertStringNotContainsString('window.location.href', $html);
    }

    /** Le mail de la promotion ne contient plus de « mailto: » : chaque lien mène à la page. */
    public function test_le_mail_mene_a_la_page(): void
    {
        $mail = file_get_contents(base_path('docs/diffusion/promo-interieur-35-2026.html'));

        $this->assertStringNotContainsString('href="mailto:', $mail);
        foreach (['', '/50m2', '/12m2', '/6m2'] as $suffixe) {
            $this->assertStringContainsString('href="https://panora-cible.com/offre/interieur-35' . $suffixe . '"', $mail);
        }
    }
}
