<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                   | Request::HEADER_X_FORWARDED_HOST
                   | Request::HEADER_X_FORWARDED_PORT
                   | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->replace(
            \Illuminate\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\ValidateLargePostSize::class
        );

        $middleware->alias([
            'role'                  => \App\Http\Middleware\CheckRole::class,
            'audit'                 => \App\Http\Middleware\AuditLogger::class,
            'client.auth'           => \App\Http\Middleware\EnsureClientIsAuthenticated::class,
            'client.must-change-pw' => \App\Http\Middleware\ForceClientPasswordChange::class,
            'sync.reactive'         => \App\Http\Middleware\SyncReactiveState::class,
        ]);

        // Routes publiques techniciens (page pige terrain) : exempter du
        // CSRF — la sécurité est assurée par le token secret dans l'URL
        // (32 chars random) + throttle agressif (60 req/min). Le CSRF
        // standard Laravel ne s'adapte pas aux sessions longues du
        // terrain (tech avec la page ouverte 1h+ entre 2 panneaux).
        // Idem pour les redirections legacy /pose/{token} et l'espace
        // technicien personnel /tech/{token}/poses.
        $middleware->validateCsrfTokens(except: [
            'pige/*',
            'pose/*',
            'tech/*',
            // Webhook Brevo (2026-09-29) : c'est un serveur qui appelle, pas
            // un navigateur. Authentifié par le jeton secret de l'URL.
            'webhooks/brevo/*',
            // Bouton « Envoyer à Panora » du Google Sheets (2026-10-28) :
            // authentifié par le jeton secret de l'en-tête Authorization.
            'api/contacts-gmail/*',
            // Désinscription (2026-10-21) : Gmail / Yahoo l'appellent en un
            // clic, sans jeton CSRF. Authentifiée par la signature du lien.
            'desinscription/*',
        ]);

        // Sync auto des statuts (campagnes, options) à chaque request web —
        // évite d'attendre le cron 01h30 pour voir une campagne passer en
        // "terminé" ou une option expirer. Mutex 60s côté middleware.
        $middleware->web(append: [
            \App\Http\Middleware\SyncReactiveState::class,
        ]);

        // Empêche le navigateur de cacher les pages HTML — corrige le bug
        // où le HTML caché référence d'anciens noms d'assets Vite après
        // un déploiement, donnant un site sans CSS jusqu'à vidage manuel
        // du cache par l'utilisateur. S'applique GLOBALEMENT (web + api)
        // pour couvrir aussi les pages publiques /pige, /proposition, etc.
        $middleware->append(\App\Http\Middleware\PreventStaleHtmlCache::class);

        // Headers de sécurité standards (clickjacking, MIME-sniffing, HSTS,
        // Referrer-Policy, Permissions-Policy) — appliqués partout.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Jeton de sécurité périmé (2026-10-26) : session expirée, ou application
        // mise à jour pendant que la page était ouverte. Au lieu de la page
        // « 419 Page Expired », que les utilisateurs prenaient pour une panne :
        //   - session perdue → page de connexion, puis retour sur la page d'avant ;
        //   - encore connecté, ou page publique → page d'avant, saisie conservée.
        // Laravel a déjà converti TokenMismatchException en HttpException 419.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Votre session a expiré. Rechargez la page.'], 419);
            }
            $public = $request->is('satisfaction/*', 'proposition/*', 'renouvellement/*', 'renouvellement-essai/*');
            $client = $request->is('client', 'client/*');
            if (! $public && ! Auth::guard($client ? 'client' : 'web')->check()) {
                return redirect()->guest(route($client ? 'client.login' : 'login'))
                    ->with('warning', 'Votre session a expiré. Reconnectez-vous : vous reviendrez sur la page où vous étiez.');
            }
            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->with('error', 'La page avait expiré : vérifiez puis renvoyez le formulaire.');
        });
    })->create();