<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\AuditLog;

use App\Enums\UserRole;

use App\Services\AlertService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8|confirmed',
            'role'       => 'required|in:admin,commercial,mediaplanner,technique',
            'agent_code' => 'nullable|string|unique:users,agent_code',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
            'agent_code' => $request->agent_code,
            'is_active'  => true,
        ]);

        // Alerte création utilisateur
        $roleLabels = [
            'admin' => 'Administrateur',
            'commercial' => 'Commercial',
            'mediaplanner' => 'Media Planner',
            'technique' => 'Technicien',
        ];
        $roleLabel = $roleLabels[$request->role] ?? $request->role;
        
        AlertService::create(
            'utilisateur',
            'info',
            '👤 Nouvel utilisateur — ' . $request->name,
            auth()->user()->name . ' a créé un compte ' . $roleLabel . ' : ' . $request->name . ' (' . $request->email . ')',
            $user
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email,'.$user->id,
            'role'       => 'required|in:admin,commercial,mediaplanner,technique',
            'agent_code' => 'nullable|string|unique:users,agent_code,'.$user->id,
            'password'   => 'nullable|min:8|confirmed',
        ]);

        $oldName = $user->name;
        $oldRole = $user->role;
        
        $data = [
            'name'       => $request->name,
            'email'      => $request->email,
            'role'       => $request->role,
            'agent_code' => $request->agent_code,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Alerte modification utilisateur
        $roleLabels = [
            'admin' => 'Administrateur',
            'commercial' => 'Commercial',
            'mediaplanner' => 'Media Planner',
            'technique' => 'Technicien',
        ];
        $newRoleLabel = $roleLabels[$request->role] ?? $request->role;
        
        AlertService::create(
            'utilisateur',
            'info',
            '✏️ Utilisateur modifié — ' . $request->name,
            auth()->user()->name . ' a modifié le compte de ' . $oldName . ' (rôle: ' . $newRoleLabel . ')',
            $user
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur modifié avec succès !');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte !');
        }

        $userName = $user->name;
        $userRole = $user->role;
        
        $roleLabels = [
            'admin' => 'Administrateur',
            'commercial' => 'Commercial',
            'mediaplanner' => 'Media Planner',
            'technique' => 'Technicien',
        ];
        $roleLabel = $roleLabels[$userRole] ?? $userRole;
        
        $user->delete();
        
        // Alerte suppression utilisateur
        AlertService::create(
            'utilisateur',
            'danger',
            '🗑 Utilisateur supprimé — ' . $userName,
            auth()->user()->name . ' a supprimé le compte ' . $userName . ' (' . $roleLabel . ')',
            null
        );
        
        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur supprimé !');
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte !');
        }

        $oldStatus = $user->is_active ? 'actif' : 'désactivé';
        $newStatus = !$user->is_active;
        
        $user->update(['is_active' => $newStatus]);

        $statusText = $newStatus ? 'activé' : 'désactivé';
        $statusIcon = $newStatus ? '✅' : '🔒';
        
        // Alerte activation/désactivation utilisateur
        $roleLabels = [
            'admin' => 'Administrateur',
            'commercial' => 'Commercial',
            'mediaplanner' => 'Media Planner',
            'technique' => 'Technicien',
        ];
        $roleLabel = $roleLabels[$user->role] ?? $user->role;
        
        AlertService::create(
            'utilisateur',
            $newStatus ? 'info' : 'warning',
            $statusIcon . ' Compte ' . $statusText . ' — ' . $user->name,
            auth()->user()->name . ' a ' . $statusText . ' le compte de ' . $user->name . ' (' . $roleLabel . ')',
            $user
        );
        
        return back()->with('success', "Compte {$statusText} !");
    }
}
