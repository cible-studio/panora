<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Session expirée / application mise à jour (2026-10-26) : plus de page
 * « 419 Page Expired » ni de page d'erreur brute (bootstrap/app.php, errors/*).
 *
 * Laravel ne vérifie pas le jeton CSRF pendant les tests : on fait rendre
 * l'exception directement par le gestionnaire, comme en production.
 */
class SessionExpireeTest extends TestCase
{
    private const PRECEDENTE = 'http://localhost/admin/campaigns';

    private function jetonPerime(string $uri, array $serveur = []): Response
    {
        $request = Request::create($uri, 'POST', ['nom' => 'Orange', 'password' => 'secret'], [], [], $serveur);
        $session = $this->app['session.store'];
        $session->start();
        $session->setPreviousUrl(self::PRECEDENTE);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        return $this->app->make(ExceptionHandler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));
    }

    public function test_session_perdue_renvoie_vers_la_connexion_puis_la_page_d_avant(): void
    {
        $r = $this->jetonPerime('/admin/campaigns/12/statut');

        $this->assertSame(302, $r->getStatusCode());
        $this->assertSame(route('login'), $r->headers->get('Location'));
        $this->assertStringContainsString('session a expiré', session('warning'));
        $this->assertSame(self::PRECEDENTE, session('url.intended')); // après la connexion : retour ici
    }

    public function test_espace_client_renvoie_vers_la_connexion_client(): void
    {
        $r = $this->jetonPerime('/client/contact');

        $this->assertSame(route('client.login'), $r->headers->get('Location'));
    }

    public function test_page_publique_revient_en_arriere_avec_la_saisie(): void
    {
        $r = $this->jetonPerime('/proposition/P-2026-001/orange/valider');

        $this->assertSame(self::PRECEDENTE, $r->headers->get('Location'));
        $this->assertStringContainsString('page avait expiré', session('error'));
        $this->assertSame(['nom' => 'Orange'], session('_old_input')); // jamais le mot de passe
    }

    public function test_encore_connecte_revient_en_arriere(): void
    {
        $this->actingAs(new User(['name' => 'Awa']));

        $r = $this->jetonPerime('/admin/campaigns/12/statut');

        $this->assertSame(self::PRECEDENTE, $r->headers->get('Location'));
        $this->assertStringContainsString('page avait expiré', session('error'));
    }

    public function test_appel_en_arriere_plan_recoit_un_419_json(): void
    {
        $r = $this->jetonPerime('/admin/piges/3/reject', ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSame(419, $r->getStatusCode());
        $this->assertStringContainsString('Rechargez la page', $r->getContent());
    }

    public function test_page_introuvable_propose_de_se_connecter(): void
    {
        $this->get('/adresse-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('Se connecter')
            ->assertSee(route('login'), false);
    }

    public function test_pages_erreur_500_et_503(): void
    {
        $this->app->instance('request', Request::create('/admin/campaigns'));
        $page500 = view('errors.500')->render();
        $this->assertStringContainsString('Réessayer', $page500);
        $this->assertStringContainsString(route('login'), $page500);

        $page503 = view('errors.503')->render();
        $this->assertStringContainsString('en cours de mise à jour', $page503);
        $this->assertStringContainsString('http-equiv="refresh"', $page503);
    }

    public function test_la_connexion_affiche_le_message_de_session_expiree(): void
    {
        $this->withoutVite()
            ->withSession(['warning' => 'Votre session a expiré. Reconnectez-vous : vous reviendrez sur la page où vous étiez.'])
            ->get('/login')
            ->assertOk()
            ->assertSee('Votre session a expiré');
    }
}
