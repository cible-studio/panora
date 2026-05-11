<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ClientAuthController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // LOGIN
    // ══════════════════════════════════════════════════════════════

    public function showLogin()
    {
        // Si déjà connecté → rediriger vers dashboard
        if (Auth::guard('client')->check()) {
            return redirect()->route('client.dashboard');
        }

        return view('client.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'L\'email est obligatoire.',
            'email.email'       => 'Format d\'email invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        // ── Tentative 1 : compte principal Client ────────────────────
        if (Auth::guard('client')->attempt($credentials, $remember)) {
            $client = Auth::guard('client')->user();

            if (!$client->hasAccount()) {
                Auth::guard('client')->logout();
                return back()->withErrors([
                    'email' => 'Votre compte n\'est pas encore activé. Contactez votre commercial.',
                ]);
            }

            $client->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);
            session()->forget(['client_user_id', 'client_user_name', 'client_user_role']);
            $request->session()->regenerate();

            if ($client->must_change_password) {
                return redirect()->route('client.password.change')
                    ->with('warning', 'Bienvenue ! Veuillez définir votre mot de passe personnel.');
            }

            return redirect()->intended(route('client.dashboard'))
                ->with('success', 'Bienvenue, ' . $client->name . ' !');
        }

        // ── Tentative 2 : sous-utilisateur ClientUser ────────────────
        $clientUser = ClientUser::where('email', $credentials['email'])
            ->where('is_active', true)
            ->with('client')
            ->first();

        if ($clientUser && Hash::check($credentials['password'], $clientUser->password)) {
            $client = $clientUser->client;

            if (!$client || !$client->hasAccount()) {
                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => 'Compte parent désactivé. Contactez votre administrateur.']);
            }

            Auth::guard('client')->login($client, $remember);
            session([
                'client_user_id'   => $clientUser->id,
                'client_user_name' => $clientUser->name,
                'client_user_role' => $clientUser->role,
            ]);
            $clientUser->update(['last_login_at' => now()]);
            $request->session()->regenerate();

            return redirect()->intended(route('client.dashboard'))
                ->with('success', 'Bienvenue, ' . $clientUser->name . ' !');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    // ══════════════════════════════════════════════════════════════
    // LOGOUT
    // ══════════════════════════════════════════════════════════════

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        session()->forget(['client_user_id', 'client_user_name', 'client_user_role']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.login')
            ->with('success', 'Vous êtes déconnecté.');
    }

    // ══════════════════════════════════════════════════════════════
    // CHANGER MOT DE PASSE (première connexion ou volontaire)
    // ══════════════════════════════════════════════════════════════

    public function showChangePassword()
    {
        $client = Auth::guard('client')->user();
         return view('client.auth.change-password', compact('client'));
    }
    
    public function updatePassword(Request $request)
    {
        $client = Auth::guard('client')->user();
        
        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
        
        if (!$client->must_change_password) {
            $rules['current_password'] = ['required', function ($attribute, $value, $fail) use ($client) {
                if (!Hash::check($value, $client->password)) {
                    $fail('Le mot de passe actuel est incorrect.');
                }
            }];
        }
        
        $request->validate($rules);
        
        $client->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        
        // Redirection intelligente : retour à la page précédente ou dashboard
        $previousUrl = url()->previous();
        $currentUrl = url()->current();
        
        if ($previousUrl && $previousUrl !== $currentUrl && !str_contains($previousUrl, 'password')) {
            return redirect($previousUrl)->with('success', 'Mot de passe mis à jour avec succès.');
        }
        
        return redirect()->route('client.dashboard')->with('success', 'Mot de passe mis à jour avec succès.');
    }
}
