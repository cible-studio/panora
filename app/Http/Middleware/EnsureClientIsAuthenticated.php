<?php
// ══════════════════════════════════════════════════════════════
// app/Http/Middleware/EnsureClientIsAuthenticated.php
// ══════════════════════════════════════════════════════════════
 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
 
/**
 * Vérifie que le visiteur est connecté via le guard 'client'.
 * Redirige vers /client/login si non authentifié.
 */
class EnsureClientIsAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('client')->check()) {
            return redirect()->route('client.login')
                ->with('error', 'Veuillez vous connecter pour accéder à votre espace.');
        }
 
        return $next($request);
    }
}