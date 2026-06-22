<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Gestion des TECHNICIENS — déplacée hors de /admin/users (qui restait
 * trop technique pour la patronne) vers la section Gestion de Pose OOH
 * (qui est leur domaine métier).
 *
 * Particularités vs UserController :
 *   • Pas de mot de passe (les techniciens ne se connectent JAMAIS à
 *     l'app via /login). Ils utilisent uniquement leur lien public
 *     /tech/{token}/poses où le token sert d'authentification.
 *   • Email optionnel (un tech terrain peut ne pas en avoir).
 *   • WhatsApp recommandé fortement (notifications de poses).
 *   • Rôle figé à 'technique' (non modifiable depuis ce module).
 *
 * Sécurité : route protégée par role:admin,mediaplanner.
 */
class TechnicienController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'technique');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('whatsapp_number', 'like', "%{$q}%")
                  ->orWhere('agent_code', 'like', "%{$q}%");
            });
        }

        $techniciens = $query->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.poses.techniciens.index', compact('techniciens'));
    }

    public function create()
    {
        // 2026-06-18 (feedback patronne) : expose la liste des équipes actives
        // pour permettre l'affectation directe à la création d'un technicien.
        $teams = \App\Models\PoseTeam::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        return view('admin.poses.techniciens.create', compact('teams'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'nullable|email|unique:users,email',
            'whatsapp_number' => 'nullable|string|max:20|regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
            'agent_code'      => 'nullable|string|max:50|unique:users,agent_code',
            // 2026-06-18 : équipe optionnelle à la création.
            'pose_team_id'    => 'nullable|integer|exists:pose_teams,id',
        ], [
            'whatsapp_number.regex' => 'Format WhatsApp invalide (ex: 0707070707 ou +2250707070707).',
            'pose_team_id.exists'   => 'Équipe inconnue.',
        ]);

        // Normalisation WhatsApp en format international (sans + ni espaces)
        if (!empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = $this->normalizeWhatsApp($data['whatsapp_number']);
        }

        // Code agent — auto-généré au format TT-001, TT-002… si vide.
        // Cohérent avec UserController et la convention historique du parc
        // (User::generateAgentCode est la source unique de vérité).
        $agentCode = !empty($data['agent_code'])
            ? $data['agent_code']
            : User::generateAgentCode('technique');

        // Génère un mot de passe random (jamais utilisé : le technicien ne
        // se connecte pas via /login) — mais Laravel exige un password hash
        // non-null dans la colonne. On stocke aussi un email auto si vide
        // pour respecter la contrainte unique (suffixe avec tech_public_token).
        $tech = User::create([
            'name'             => $data['name'],
            'email'            => $data['email'] ?: 'tech_' . Str::random(8) . '@cible-ci.com',
            'password'         => Hash::make(Str::random(40)),
            'role'             => UserRole::TECHNIQUE,
            'agent_code'       => $agentCode,
            'whatsapp_number'  => $data['whatsapp_number'] ?? null,
            // Champ legacy conservé pour rétro-compat lecture (vues non migrées).
            // La vraie source de vérité multi-équipe est la pivot pose_team_user.
            'pose_team_id'     => $data['pose_team_id'] ?? null,
            'is_active'        => true,
        ]);

        // 2026-06-19 — Multi-équipe : si une équipe est choisie, on l'attache
        // via la pivot. Le tech peut ensuite être ajouté à d'autres équipes
        // depuis la fiche équipe sans perdre celle-ci.
        if (!empty($data['pose_team_id'])) {
            $tech->poseTeams()->syncWithoutDetaching([
                (int) $data['pose_team_id'] => ['joined_at' => now()],
            ]);
        }

        // Génère le token public à la création (utilisé pour /tech/{token}/poses)
        $tech->ensureTechPublicToken();

        return redirect()->route('admin.pose-tasks.techniciens.index')
            ->with('success', "Technicien {$tech->name} créé. Lien public généré.");
    }

    public function edit(User $technicien)
    {
        $this->ensureIsTechnician($technicien);
        $teams = \App\Models\PoseTeam::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        return view('admin.poses.techniciens.edit', compact('technicien', 'teams'));
    }

    public function update(Request $request, User $technicien)
    {
        $this->ensureIsTechnician($technicien);

        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'nullable|email|unique:users,email,' . $technicien->id,
            'whatsapp_number' => 'nullable|string|max:20|regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
            'agent_code'      => 'nullable|string|max:50|unique:users,agent_code,' . $technicien->id,
            'pose_team_id'    => 'nullable|integer|exists:pose_teams,id',
            'is_active'       => 'nullable|boolean',
        ], [
            'whatsapp_number.regex' => 'Format WhatsApp invalide.',
            'pose_team_id.exists'   => 'Équipe inconnue.',
        ]);

        if (!empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = $this->normalizeWhatsApp($data['whatsapp_number']);
        }

        // 2026-06-19 — Multi-équipe : le select pose_team_id de cette vue
        // représente "l'équipe à AJOUTER" pour ce tech (l'admin retire les
        // équipes individuellement via PoseTeamController::removeMember).
        // Le champ legacy users.pose_team_id est aligné sur la valeur choisie
        // pour rétro-compat lecture.
        $newTeamId = $request->has('pose_team_id') ? ($data['pose_team_id'] ?? null) : $technicien->pose_team_id;
        $technicien->update([
            'name'             => $data['name'],
            'email'            => $data['email'] ?: $technicien->email,
            'agent_code'       => $data['agent_code'] ?? null,
            'whatsapp_number'  => $data['whatsapp_number'] ?? null,
            'pose_team_id'     => $newTeamId,
            'is_active'        => $request->boolean('is_active', $technicien->is_active),
        ]);
        if (!empty($newTeamId)) {
            $technicien->poseTeams()->syncWithoutDetaching([
                (int) $newTeamId => ['joined_at' => now()],
            ]);
        }

        return redirect()->route('admin.pose-tasks.techniciens.index')
            ->with('success', "Technicien {$technicien->name} mis à jour.");
    }

    public function destroy(User $technicien)
    {
        $this->ensureIsTechnician($technicien);

        $name = $technicien->name;
        $technicien->delete();

        return redirect()->route('admin.pose-tasks.techniciens.index')
            ->with('success', "Technicien {$name} supprimé.");
    }

    /** Régénère un nouveau token public (invalide l'ancien lien). */
    public function regenerateToken(User $technicien)
    {
        $this->ensureIsTechnician($technicien);
        $technicien->forceFill(['tech_public_token' => Str::random(32)])->save();

        return redirect()->route('admin.pose-tasks.techniciens.index')
            ->with('success', "Nouveau lien public généré pour {$technicien->name}. L'ancien lien ne fonctionne plus.");
    }

    /** Bascule actif/inactif sans passer par edit (un clic). */
    public function toggleActive(User $technicien)
    {
        $this->ensureIsTechnician($technicien);
        $technicien->update(['is_active' => !$technicien->is_active]);

        return back()->with('success',
            $technicien->is_active ? "Technicien réactivé." : "Technicien désactivé."
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────

    protected function ensureIsTechnician(User $u): void
    {
        if ($u->role !== UserRole::TECHNIQUE) {
            abort(404, "Cet utilisateur n'est pas un technicien.");
        }
    }

    /** Normalise un numéro WhatsApp en format international sans + ni espaces. */
    protected function normalizeWhatsApp(string $raw): string
    {
        $digits = preg_replace('/[^\d]/', '', $raw);
        // Si pas de préfixe pays (10 chiffres = numéro CI local) → ajoute 225
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '225' . substr($digits, 1);
        }
        return $digits;
    }
}
