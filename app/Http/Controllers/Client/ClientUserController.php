<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\ClientUserInvitationMail;
use App\Models\ClientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClientUserController extends Controller
{
    private function canManage(): bool
    {
        // Main client account (no sub-user session) OR sub-user with owner role
        $role = session('client_user_role');
        return $role === null || $role === 'owner';
    }

    public function index()
    {
        $client = Auth::guard('client')->user();
        $users  = ClientUser::where('client_id', $client->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('client.equipe', compact('client', 'users'));
    }

    public function store(Request $request)
    {
        if (!$this->canManage()) abort(403);

        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'email'                 => 'required|email|unique:client_users,email',
            'password'              => 'required|string|min:8|confirmed',
            'role'                  => 'required|in:owner,member',
        ], [
            'email.unique'          => 'Cette adresse email est déjà utilisée.',
            'password.min'          => 'Le mot de passe doit faire au moins 8 caractères.',
            'password.confirmed'    => 'Les mots de passe ne correspondent pas.',
        ]);

        $newUser = ClientUser::create([
            'client_id' => $client->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        // Envoi de l'email d'invitation avec les identifiants en clair —
        // best-effort : si l'envoi rate, le compte est créé quand même,
        // et l'owner sera averti dans le flash pour qu'il transmette
        // manuellement les accès.
        $mailSent = $this->sendInvitationEmail($newUser, $data['password'], $client);

        $flashMsg = $mailSent
            ? "Utilisateur ajouté. Un email d'invitation avec ses identifiants a été envoyé à {$data['email']}."
            : "Utilisateur ajouté MAIS l'envoi de l'email a échoué. Transmettez manuellement les identifiants à {$data['email']}.";

        return back()->with($mailSent ? 'success' : 'warning', $flashMsg);
    }

    /**
     * Envoie l'email d'invitation au nouvel utilisateur avec ses
     * identifiants en clair + le lien de connexion. Retourne true/false.
     *
     * Sécurité : on envoie le mot de passe en clair UNIQUEMENT au
     * moment de la création (canal email → propriétaire du compte
     * email). Il est recommandé au membre de le changer à la
     * première connexion (changement disponible via /client/password/change).
     */
    private function sendInvitationEmail(ClientUser $user, string $plainPassword, $client): bool
    {
        try {
            Mail::to($user->email, $user->name)
                ->send(new ClientUserInvitationMail($user, $plainPassword, $client));
            return true;
        } catch (\Throwable $e) {
            Log::warning('client.user.invite_mail_failed', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function update(Request $request, ClientUser $clientUser)
    {
        if (!$this->canManage()) abort(403);

        $client = Auth::guard('client')->user();
        if ($clientUser->client_id !== $client->id) abort(403);

        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'role'      => 'required|in:owner,member',
            'is_active' => 'boolean',
        ]);

        // Prevent disabling the only active owner
        if (!($data['is_active'] ?? true) || $data['role'] === 'member') {
            $activeOwners = ClientUser::where('client_id', $client->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->where('id', '!=', $clientUser->id)
                ->count();

            if ($activeOwners === 0 && $clientUser->role === 'owner') {
                return back()->withErrors(['role' => 'Impossible : il faut au moins un propriétaire actif.']);
            }
        }

        $clientUser->update([
            'name'      => $data['name'],
            'role'      => $data['role'],
            'is_active' => $data['is_active'] ?? $clientUser->is_active,
        ]);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(ClientUser $clientUser)
    {
        if (!$this->canManage()) abort(403);

        $client = Auth::guard('client')->user();
        if ($clientUser->client_id !== $client->id) abort(403);

        // Prevent deleting the last owner
        if ($clientUser->role === 'owner') {
            $otherOwners = ClientUser::where('client_id', $client->id)
                ->where('role', 'owner')
                ->where('id', '!=', $clientUser->id)
                ->count();
            if ($otherOwners === 0) {
                return back()->withErrors(['delete' => 'Impossible de supprimer le seul propriétaire.']);
            }
        }

        $clientUser->delete();
        return back()->with('success', 'Utilisateur supprimé.');
    }
}
