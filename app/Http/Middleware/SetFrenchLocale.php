<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force la locale 'fr' pour les routes client + auth client.
 *
 * Sur cet environnement, APP_LOCALE peut rester sur 'en' pour les besoins
 * dev/admin mais le client final voit toujours du français (validation
 * messages, auth.failed, mot de passe oublié, etc.).
 *
 * Garde-fou : ne touche que la requête courante, ne modifie pas
 * APP_LOCALE global. Sortie de la requête = locale restauré.
 */
class SetFrenchLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('fr');
        return $next($request);
    }
}
