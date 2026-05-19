<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Empêche le navigateur de cacher les pages HTML.
 *
 * Problème résolu :
 *   À chaque déploiement, Vite génère de nouveaux noms d'assets
 *   (hash dans le filename). Le HTML de la nouvelle page référence
 *   ces nouveaux fichiers. MAIS si le navigateur a en cache l'ancien
 *   HTML (avec heuristic caching par défaut), il référence l'ancien
 *   nom de fichier → 404 sur le CSS/JS → site rendu sans style.
 *
 * Stratégie en 2 couches :
 *   1. HTML responses → `Cache-Control: no-cache` : le navigateur
 *      DOIT vérifier avec le serveur à chaque requête (ETag/Last-Modified
 *      permettent un 304 si rien n'a changé, donc peu de charge serveur).
 *   2. Assets `/build/*` → `Cache-Control: public, max-age=31536000,
 *      immutable` : ces fichiers ont un hash dans le nom, donc on peut
 *      les cacher 1 an sans risque (le hash change = nouveau filename
 *      = nouvelle URL).
 *
 * Cette stratégie est l'industry standard (Webpack/Vite/Next.js/etc.).
 */
class PreventStaleHtmlCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = $response->headers->get('Content-Type', '');
        $path = $request->path();

        // 1. Assets versionnés Vite (build/) — cache long (1 an)
        // Le hash dans le filename garantit que tout changement = nouvelle
        // URL, donc on peut cacher sans risque. Réduit drastiquement les
        // round-trips après le premier chargement.
        if (str_starts_with($path, 'build/')
            || str_contains($path, '/build/')) {
            $response->headers->set(
                'Cache-Control',
                'public, max-age=31536000, immutable'
            );
            return $response;
        }

        // 2. Pages HTML — pas de cache (revalidation systématique)
        // 'no-cache' n'empêche PAS le cache local, mais oblige le
        // navigateur à valider avec le serveur (304 si inchangé).
        // C'est ce qu'on veut : pas de charge serveur inutile, mais
        // un asset périmé est immédiatement détecté.
        if (str_contains($contentType, 'text/html')) {
            $response->headers->set(
                'Cache-Control',
                'no-cache, must-revalidate, max-age=0'
            );
            // Pragma : pour les caches HTTP/1.0 (proxies legacy)
            $response->headers->set('Pragma', 'no-cache');
            // Expires : forcer expiration immédiate pour proxies anciens
            $response->headers->set('Expires', '0');
            return $response;
        }

        // 3. Autres responses (JSON API, downloads, etc.) — non touché
        // pour laisser Laravel/middlewares spécifiques décider.
        return $response;
    }
}
