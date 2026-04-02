<?php

namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
 
/**
 * Force le client à changer son mot de passe initial.
 * Sauf sur la page de changement elle-même.
 */
class ForceClientPasswordChange
{
    // Routes exemptées
    private const EXEMPT_ROUTES = [
        'client.password.change',
        'client.password.update',
        'client.logout',
    ];
 
    public function handle(Request $request, Closure $next)
    {
        $client = Auth::guard('client')->user();
 
        if (
            $client &&
            $client->must_change_password &&
            !in_array($request->route()?->getName(), self::EXEMPT_ROUTES)
        ) {
            return redirect()->route('client.password.change')
                ->with('warning', 'Vous devez définir un nouveau mot de passe avant de continuer.');
        }
 
        return $next($request);
    }
}
 
