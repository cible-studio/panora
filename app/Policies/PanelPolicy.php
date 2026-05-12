<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Panel;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout (créer / éditer / supprimer)
 *   MP          → voir + changer le statut manuel (libre / maintenance)
 *   Commercial  → voir uniquement (catalogue)
 *   Technique   → voir uniquement (consultation terrain)
 */
class PanelPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::COMMERCIAL,
            UserRole::MEDIAPLANNER,
            UserRole::TECHNIQUE,
        ], true);
    }

    public function view(User $user, Panel $panel): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false; // admin only via before()
    }

    public function update(User $user, Panel $panel): bool
    {
        return false; // admin only via before()
    }

    /** Changer manuellement le statut libre <-> maintenance : MP + Admin. */
    public function updateStatus(User $user, Panel $panel): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function delete(User $user, Panel $panel): bool
    {
        return false; // admin only via before()
    }

    public function restore(User $user, Panel $panel): bool
    {
        return false;
    }

    public function forceDelete(User $user, Panel $panel): bool
    {
        return false;
    }
}
