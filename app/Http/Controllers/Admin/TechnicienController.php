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
        return view('admin.poses.techniciens.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'nullable|email|unique:users,email',
            'whatsapp_number' => 'nullable|string|max:20|regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
            'agent_code'      => 'nullable|string|max:50|unique:users,agent_code',
        ], [
            'whatsapp_number.regex' => 'Format WhatsApp invalide (ex: 0707070707 ou +2250707070707).',
        ]);

        // Normalisation WhatsApp en format international (sans + ni espaces)
        if (!empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = $this->normalizeWhatsApp($data['whatsapp_number']);
        }

        // Génère un mot de passe random (jamais utilisé : le technicien ne
        // se connecte pas via /login) — mais Laravel exige un password hash
        // non-null dans la colonne. On stocke aussi un email auto si vide
        // pour respecter la contrainte unique (suffixe avec tech_public_token).
        $tech = User::create([
            'name'             => $data['name'],
            'email'            => $data['email'] ?: 'tech_' . Str::random(8) . '@cible-ci.com',
            'password'         => Hash::make(Str::random(40)),
            'role'             => UserRole::TECHNIQUE,
            'agent_code'       => $data['agent_code'] ?? null,
            'whatsapp_number'  => $data['whatsapp_number'] ?? null,
            'is_active'        => true,
        ]);

        // Génère le token public à la création (utilisé pour /tech/{token}/poses)
        $tech->ensureTechPublicToken();

        return redirect()->route('admin.pose-tasks.techniciens.index')
            ->with('success', "Technicien {$tech->name} créé. Lien public généré.");
    }

    public function edit(User $technicien)
    {
        $this->ensureIsTechnician($technicien);
        return view('admin.poses.techniciens.edit', compact('technicien'));
    }

    public function update(Request $request, User $technicien)
    {
        $this->ensureIsTechnician($technicien);

        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'nullable|email|unique:users,email,' . $technicien->id,
            'whatsapp_number' => 'nullable|string|max:20|regex:/^[\+\d\s\-\(\)\.]{6,20}$/',
            'agent_code'      => 'nullable|string|max:50|unique:users,agent_code,' . $technicien->id,
            'is_active'       => 'nullable|boolean',
        ], [
            'whatsapp_number.regex' => 'Format WhatsApp invalide.',
        ]);

        if (!empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = $this->normalizeWhatsApp($data['whatsapp_number']);
        }

        $technicien->update([
            'name'             => $data['name'],
            'email'            => $data['email'] ?: $technicien->email,
            'agent_code'       => $data['agent_code'] ?? null,
            'whatsapp_number'  => $data['whatsapp_number'] ?? null,
            'is_active'        => $request->boolean('is_active', $technicien->is_active),
        ]);

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
