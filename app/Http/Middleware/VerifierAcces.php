<?php

namespace App\Http\Middleware;

use App\Support\Acces;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware acces:<module> (2026-11-17) — pour un module de
 * config/acces.php qui nomme des personnes en plus des rôles (baromètre :
 * les admins + BAROMETRE_ACCES). Applique Acces::peut(), la même règle que
 * le menu. À poser APRÈS « auth » et « role:… » (compte actif, personnel).
 */
class VerifierAcces
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!Acces::peut($module)) {
            Log::warning('rbac.acces_refuse', [
                'user_id' => $request->user()?->id,
                'module'  => $module,
                'path'    => $request->path(),
            ]);
            abort(403, 'Cette page est réservée.');
        }

        return $next($request);
    }
}
