<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Maintenance;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout
 *   MP          → voir + créer ticket + assigner + résoudre
 *   Commercial  → aucun accès (pas son périmètre)
 *   Technique   → ses propres tickets (vue uniquement côté admin web,
 *                 mais l'exécution est sur le terrain via WhatsApp +
 *                 fiche maintenance qui ouvre les notifications)
 */
class MaintenancePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::MEDIAPLANNER,
            UserRole::TECHNIQUE,
        ], true);
    }

    public function view(User $user, Maintenance $maintenance): bool
    {
        if ($user->role === UserRole::MEDIAPLANNER) return true;
        // Technique : seulement ses propres tickets
        if ($user->role === UserRole::TECHNIQUE) {
            return $maintenance->technicien_id === $user->id;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function update(User $user, Maintenance $maintenance): bool
    {
        // Locked si résolu/annulé (cf. Maintenance::isLocked())
        if (method_exists($maintenance, 'isLocked') && $maintenance->isLocked()) {
            return false;
        }
        if ($user->role === UserRole::MEDIAPLANNER) return true;
        if ($user->role === UserRole::TECHNIQUE) {
            return $maintenance->technicien_id === $user->id;
        }
        return false;
    }

    public function resolve(User $user, Maintenance $maintenance): bool
    {
        return $this->update($user, $maintenance);
    }

    public function reopen(User $user, Maintenance $maintenance): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function delete(User $user, Maintenance $maintenance): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function restore(User $user, Maintenance $maintenance): bool
    {
        return false; // admin only via before()
    }

    public function forceDelete(User $user, Maintenance $maintenance): bool
    {
        return false; // admin only via before()
    }
}
