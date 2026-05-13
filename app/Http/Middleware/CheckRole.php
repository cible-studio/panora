<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware role:rôle1,rôle2,... — autorise l'accès à une route uniquement
 * si l'utilisateur connecté a un des rôles listés.
 *
 * Cas extrêmes gérés :
 *   - Pas authentifié                → redirect login
 *   - User désactivé (is_active=0)   → logout forcé + redirect login
 *   - User sans rôle (null)          → abort 403
 *   - Rôle non listé                 → abort 403 (avec log audit)
 *
 * Usage :
 *   Route::middleware('role:admin')->group(...);
 *   Route::middleware('role:admin,mediaplanner')->group(...);
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 1) Pas authentifié → login
        if (!Auth::check()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Authentification requise.'], 401)
                : redirect()->guest(route('login'));
        }

        $user = Auth::user();

        // 2) User désactivé → logout forcé + message
        if (property_exists($user, 'is_active') || isset($user->is_active)) {
            if ($user->is_active === false || $user->is_active === 0) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return $request->expectsJson()
                    ? response()->json(['message' => 'Compte désactivé. Contactez un administrateur.'], 403)
                    : redirect()->route('login')->withErrors([
                        'email' => 'Votre compte a été désactivé. Contactez un administrateur.',
                    ]);
            }
        }

        // 3) Rôle null ou inconnu
        $userRole = $user->role?->value ?? (is_string($user->role) ? $user->role : null);
        if (!$userRole) {
            Log::warning('rbac.user_without_role', [
                'user_id' => $user->id ?? null,
                'path'    => $request->path(),
            ]);
            abort(403, 'Aucun rôle attribué à votre compte. Contactez un administrateur.');
        }

        // 4) Rôle pas dans la whitelist de la route
        if (!in_array($userRole, $roles, true)) {
            Log::info('rbac.access_denied', [
                'user_id'        => $user->id,
                'user_role'      => $userRole,
                'required_roles' => $roles,
                'path'           => $request->path(),
                'method'         => $request->method(),
            ]);
            abort(403, 'Accès non autorisé pour votre rôle.');
        }

        return $next($request);
    }
}
