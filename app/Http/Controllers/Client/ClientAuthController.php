<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
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

        if (!Auth::guard('client')->attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email ou mot de passe incorrect.']);
        }

        $client = Auth::guard('client')->user();

        // Vérifier que le compte est activé (password défini par l'admin)
        if (!$client->hasAccount()) {
            Auth::guard('client')->logout();
            return back()->withErrors([
                'email' => 'Votre compte n\'est pas encore activé. Contactez votre commercial.',
            ]);
        }

        // Enregistrer dernier login
        $client->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $request->session()->regenerate();

        // Si doit changer de mot de passe → rediriger
        if ($client->must_change_password) {
            return redirect()->route('client.password.change')
                ->with('warning', 'Bienvenue ! Veuillez définir votre mot de passe personnel.');
        }

        return redirect()->intended(route('client.dashboard'))
            ->with('success', 'Bienvenue, ' . $client->name . ' !');
    }

    // ══════════════════════════════════════════════════════════════
    // LOGOUT
    // ══════════════════════════════════════════════════════════════

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
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
        return view('client.auth.change-password');
    }

    public function updatePassword(Request $request)
    {
        $client = Auth::guard('client')->user();

        $rules = [
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];

        // Si c'est un changement volontaire (pas première connexion) → demander l'ancien
        if (!$client->must_change_password) {
            $rules['current_password'] = 'required|string';
        }

        $request->validate($rules, [
            'password.required'         => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed'        => 'Les mots de passe ne correspondent pas.',
            'password.min'              => 'Le mot de passe doit faire au moins 8 caractères.',
            'current_password.required' => 'Votre mot de passe actuel est requis.',
        ]);

        // Vérifier l'ancien mot de passe si changement volontaire
        if (!$client->must_change_password) {
            if (!Hash::check($request->current_password, $client->password)) {
                return back()->withErrors([
                    'current_password' => 'Mot de passe actuel incorrect.',
                ]);
            }
        }

        $client->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ]);

        return redirect()->route('client.dashboard')
            ->with('success', 'Mot de passe mis à jour avec succès.');
    }
}