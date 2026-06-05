<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajoute les headers de sécurité standards sur toutes les réponses HTTP.
 * Couvre les attaques courantes : clickjacking, MIME sniffing, XSS legacy,
 * fuite de Referer, contrôle du chargement de ressources cross-origin.
 *
 * NB : CSP non activée ici (risque de casser DomPDF inline + Vite HMR en dev).
 * Pour activer une CSP stricte plus tard, on ajoutera un nonce sur les <script>
 * inline et on autorisera 'self' + cdnjs (Select2) + Google Fonts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Empêche l'inclusion du site dans une <iframe> tierce (clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Empêche le navigateur de "deviner" le content-type (MIME sniffing)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Restreint l'envoi du Referer aux requêtes cross-origin
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Bloque les API navigateur potentiellement dangereuses non utilisées
        $response->headers->set('Permissions-Policy',
            'camera=(), microphone=(), geolocation=(self), payment=(), usb=()'
        );

        // HSTS — force HTTPS pendant 1 an (uniquement si déjà servi en HTTPS,
        // sinon un client HTTP n'a pas l'occasion de lire le header). Le
        // serveur web (Apache/Nginx) le fera tout de même de son côté en prod.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
