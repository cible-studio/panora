<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Mail\UserWelcomeMail;
use App\Models\User;
use App\Models\AuditLog;

use App\Enums\UserRole;

use App\Services\AlertService;
use App\Services\NotificationMailer;

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
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|min:8|confirmed',
            'role'            => 'required|in:admin,commercial,mediaplanner,technique',
            'agent_code'      => 'nullable|string|unique:users,agent_code',
            'whatsapp_number' => 'nullable|string|max:20|regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
        ], [
            'whatsapp_number.regex' => 'Format WhatsApp invalide (ex: 0707070707 ou +2250707070707).',
        ]);

        $plainPassword = $request->password; // gardé pour l'email AVANT hash

        // Normalisation du numéro WhatsApp si fourni → format international sans "+"
        $whatsapp = null;
        if ($request->filled('whatsapp_number')) {
            $whatsapp = app(\App\Services\WhatsAppService::class)
                ->normalizeNumber($request->input('whatsapp_number'));
            if ($whatsapp === null) {
                return back()->withInput()->withErrors([
                    'whatsapp_number' => 'Numéro WhatsApp invalide.',
                ]);
            }
        }

        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($plainPassword),
            'role'            => $request->role,
            'agent_code'      => $request->agent_code,
            'whatsapp_number' => $whatsapp,
            'is_active'       => true,
        ]);

        // Alerte création utilisateur
        $roleLabel = UserRole::labelFor($request->role);

        AlertService::create(
            'utilisateur',
            'info',
            '👤 Nouvel utilisateur — ' . $request->name,
            auth()->user()->name . ' a créé un compte ' . $roleLabel . ' : ' . $request->name . ' (' . $request->email . ')',
            $user
        );

        // ── Mail de bienvenue ────────────────────────────────────────────
        // sendNow() = envoi synchrone (bypass queue). On veut savoir
        // immédiatement si le mail est parti, car le mot de passe temporaire
        // n'est pas re-récupérable plus tard.
        $mailResult = app(NotificationMailer::class)->sendNow(
            $user->email,
            new UserWelcomeMail($user, $plainPassword, 'created'),
            context: ['action' => 'user.welcome', 'created_by' => auth()->id()]
        );

        $msg = 'Utilisateur créé avec succès !';
        if ($mailResult->ok) {
            $msg .= ' 📧 Un email de bienvenue a été envoyé à ' . $user->email . '.';
            return redirect()->route('admin.users.index')->with('success', $msg);
        }

        // Mail KO → on prévient l'admin sans bloquer
        return redirect()->route('admin.users.index')
            ->with('warning', $msg . ' ' . $mailResult->message
                . ' Vous pouvez communiquer manuellement les identifiants à ' . $user->email . '.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|unique:users,email,'.$user->id,
            'role'            => 'required|in:admin,commercial,mediaplanner,technique',
            'agent_code'      => 'nullable|string|unique:users,agent_code,'.$user->id,
            'password'        => 'nullable|min:8|confirmed',
            'whatsapp_number' => 'nullable|string|max:20|regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
        ], [
            'whatsapp_number.regex' => 'Format WhatsApp invalide (ex: 0707070707 ou +2250707070707).',
        ]);

        $oldName = $user->name;
        $oldRole = $user->role;

        // Normalisation WhatsApp : si vide → null, sinon E.164 sans +
        $whatsapp = null;
        if ($request->filled('whatsapp_number')) {
            $whatsapp = app(\App\Services\WhatsAppService::class)
                ->normalizeNumber($request->input('whatsapp_number'));
            if ($whatsapp === null) {
                return back()->withInput()->withErrors([
                    'whatsapp_number' => 'Numéro WhatsApp invalide.',
                ]);
            }
        }

        $data = [
            'name'            => $request->name,
            'email'           => $request->email,
            'role'            => $request->role,
            'agent_code'      => $request->agent_code,
            'whatsapp_number' => $whatsapp,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Alerte modification utilisateur
        $newRoleLabel = UserRole::labelFor($request->role);
        
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

        $userName  = $user->name;
        $roleLabel = UserRole::labelFor($user->role);

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
        $wasInactive = !$user->is_active;

        $user->update(['is_active' => $newStatus]);

        // Notifier l'utilisateur si son compte vient d'être (ré)activé
        if ($wasInactive && $newStatus === true) {
            app(NotificationMailer::class)->sendSilently(
                $user->email,
                new UserWelcomeMail($user, null, 'reactivated'),
                context: ['action' => 'user.reactivated', 'by' => auth()->id()]
            );
        }

        $statusText = $newStatus ? 'activé' : 'désactivé';
        $statusIcon = $newStatus ? '✅' : '🔒';
        
        // Alerte activation/désactivation utilisateur
        $roleLabel = UserRole::labelFor($user->role);
        
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
