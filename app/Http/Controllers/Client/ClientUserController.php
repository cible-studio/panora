<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        ClientUser::create([
            'client_id' => $client->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Utilisateur ajouté avec succès.');
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
