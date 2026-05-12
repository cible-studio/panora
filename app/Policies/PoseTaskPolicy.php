<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PoseTask;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout
 *   MP          → tout (planning des poses : créer, assigner, replanifier,
 *                 suivre, marquer effectuée)
 *   Commercial  → aucun accès (le commercial ne s'occupe pas du terrain)
 *   Technique   → ses propres interventions uniquement via le lien public
 *                 /pige/{token} (PAS de login web). Ces accès passent par
 *                 PoseTaskPublicController + token, pas par cette policy.
 */
class PoseTaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function view(User $user, PoseTask $poseTask): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function update(User $user, PoseTask $poseTask): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Actions groupées (bulk update) — même règle que update. */
    public function bulkUpdate(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function delete(User $user, PoseTask $poseTask): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function restore(User $user, PoseTask $poseTask): bool
    {
        return false; // admin only via before()
    }

    public function forceDelete(User $user, PoseTask $poseTask): bool
    {
        return false; // admin only via before()
    }
}
