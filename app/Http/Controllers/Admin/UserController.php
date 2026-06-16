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
            'password'        => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()],
            'role'            => 'required|in:admin,commercial,mediaplanner,comptable,technique',
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

        // Code agent : si l'admin n'en saisit pas, on génère un code par
        // rôle (Lot 10.1) au format SC-001 (commercial), TT-001 (technique),
        // MP-001 (mediaplanner), AD-001 (admin). Si l'admin saisit un code
        // manuellement, on respecte tel quel.
        $agentCode = $request->agent_code ?: User::generateAgentCode($request->role);

        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($plainPassword),
            'role'            => $request->role,
            'agent_code'      => $agentCode,
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
            'role'            => 'required|in:admin,commercial,mediaplanner,comptable,technique',
            'agent_code'      => 'nullable|string|unique:users,agent_code,'.$user->id,
            'password'        => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()],
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

        $plainPassword = null;
        if ($request->filled('password')) {
            $plainPassword    = $request->password; // gardé pour l'email AVANT hash
            $data['password'] = Hash::make($plainPassword);
        }

        $user->update($data);

        // Alerte modification utilisateur
        $newRoleLabel = UserRole::labelFor($request->role);

        AlertService::create(
            'utilisateur',
            'info',
            '✏️ Utilisateur modifié — ' . $request->name,
            auth()->user()->name . ' a modifié le compte de ' . $oldName . ' (rôle: ' . $newRoleLabel . ')'
                . ($plainPassword ? ' — mot de passe réinitialisé' : ''),
            $user
        );

        // Si le mot de passe a été changé, on envoie un mail au user avec
        // ses nouveaux identifiants. C'est l'équivalent du flow "Nouveau
        // mot de passe temporaire" que le client a aussi côté ClientAccountMail.
        $passwordMailSent = false;
        if ($plainPassword && $user->email) {
            try {
                app(\App\Services\NotificationMailer::class)->sendNow(
                    [$user->email],
                    new UserWelcomeMail($user->fresh(), $plainPassword, 'password_reset'),
                    context: [
                        'user_id' => $user->id,
                        'action'  => 'user.password_reset',
                    ],
                );
                $passwordMailSent = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('user.password_reset.mail_failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $msg = 'Utilisateur modifié avec succès.';
        if ($plainPassword) {
            $msg .= $passwordMailSent
                ? ' 📧 Nouveau mot de passe envoyé à ' . $user->email . '.'
                : ' ⚠️ Mot de passe changé mais envoi email échoué — vérifiez les logs.';
        }

        return redirect()->route('admin.users.index')->with('success', $msg);
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

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }
        if ($request->filled('kind')) {
            // Filtre rapide par famille d'actions (déclenché par les KPI cards)
            $kind = $request->input('kind');
            $query->where('action', 'like', '%' . $kind . '%');
        }

        $logs  = $query->paginate(50)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        // KPIs : compteurs par famille d'action sur l'ensemble des logs
        // (indépendants des filtres pour garder une vue globale).
        $kpis = [
            'total'   => AuditLog::count(),
            'created' => AuditLog::where('action', 'like', '%created%')->count(),
            'updated' => AuditLog::where('action', 'like', '%updated%')->count(),
            'deleted' => AuditLog::where('action', 'like', '%deleted%')->count(),
        ];

        return view('admin.audit.logs', compact('logs', 'users', 'kpis'));
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

    // ══════════════════════════════════════════════════════════════
    // BULK ACTION — actions groupées sur plusieurs utilisateurs
    //
    // Actions :
    //   - 'activate'   : passe N comptes en is_active=true
    //   - 'deactivate' : passe N comptes en is_active=false (avec garde)
    //
    // GARDES CRITIQUES :
    //   - Jamais se désactiver soi-même
    //   - Jamais désactiver le DERNIER admin actif (vérif AVANT et
    //     APRÈS chaque toggle pour rester safe en concurrence)
    // ══════════════════════════════════════════════════════════════
    public function bulkAction(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:activate,deactivate',
            'ids'    => 'required|array|min:1|max:200',
            'ids.*'  => 'integer|exists:users,id',
        ]);

        $users = User::whereIn('id', $data['ids'])->get();
        $applied   = 0;
        $skipped   = [];
        $mailsSent = 0;
        $newActive = $data['action'] === 'activate';

        foreach ($users as $u) {
            // 1. Pas soi-même (sur deactivate uniquement — l'activer ne casse rien)
            if (!$newActive && $u->id === auth()->id()) {
                $skipped[] = $u->name . ' (vous-même)';
                continue;
            }
            // 2. Pas le dernier admin actif (sur deactivate uniquement)
            if (!$newActive && $u->role?->value === 'admin' && $u->is_active) {
                $remainingActiveAdmins = User::where('role', 'admin')
                    ->where('is_active', true)
                    ->where('id', '!=', $u->id)
                    ->count();
                if ($remainingActiveAdmins < 1) {
                    $skipped[] = $u->name . ' (dernier admin actif)';
                    continue;
                }
            }
            // 3. Skip silencieux si déjà dans l'état cible
            if ((bool) $u->is_active === $newActive) {
                continue;
            }
            $u->update(['is_active' => $newActive]);
            $applied++;

            // 4. Pour les RÉACTIVATIONS : envoyer le mail UserWelcomeMail
            // au user pour qu'il sache qu'il peut se reconnecter.
            // Symétrique de toggleActive() qui le fait pour 1 user.
            // Sur les DÉSACTIVATIONS : pas de mail (silence — choix
            // produit, l'admin n'a pas besoin de prévenir un compte
            // qu'on coupe ; sinon on le ferait dans toggleActive aussi).
            if ($newActive && $u->email) {
                try {
                    app(\App\Services\NotificationMailer::class)->sendNow(
                        [$u->email],
                        new UserWelcomeMail($u->fresh(), null, 'reactivated'),
                        context: [
                            'user_id' => $u->id,
                            'action'  => 'user.reactivated.bulk',
                        ],
                    );
                    $mailsSent++;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('user.reactivated.bulk.mail_failed', [
                        'user_id' => $u->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        AlertService::create(
            'utilisateur',
            $newActive ? 'info' : 'warning',
            ($newActive ? '✅' : '🔒') . ' Action groupée — ' . $applied . ' utilisateur(s)',
            auth()->user()->name . ' a ' . ($newActive ? 'activé' : 'désactivé') . ' ' . $applied . ' compte(s).'
                . ($mailsSent > 0 ? ' ' . $mailsSent . ' mail(s) envoyé(s).' : '')
                . (!empty($skipped) ? ' Ignorés : ' . count($skipped) . '.' : ''),
            null
        );

        $msg = "{$applied} compte(s) " . ($newActive ? 'activé(s)' : 'désactivé(s)') . '.';
        if ($newActive && $mailsSent > 0) {
            $msg .= " 📧 {$mailsSent} mail(s) envoyé(s) aux utilisateurs.";
        }
        if (!empty($skipped)) {
            $msg .= ' ' . count($skipped) . ' ignoré(s) : ' . implode(', ', array_slice($skipped, 0, 5))
                  . (count($skipped) > 5 ? '…' : '');
        }
        return redirect()->route('admin.users.index')->with('success', $msg);
    }
}
