<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Panel;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout (créer / éditer / supprimer)
 *   MP          → tout sur l'inventaire (créer / éditer / supprimer / statut)
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
            UserRole::COMPTABLE,
            UserRole::TECHNIQUE,
        ], true);
    }

    public function view(User $user, Panel $panel): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function update(User $user, Panel $panel): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /**
     * Éditer uniquement le tarif catalogue (monthly_rate) du panneau.
     * Ouvert au Comptable pour qu'il coordonne les futures factures
     * sans avoir besoin du MP (cf. User::canEditPrices()).
     *
     * update() reste MP-only pour photos, GPS, dimensions, statut.
     */
    public function updatePrice(User $user, Panel $panel): bool
    {
        return $user->canEditPrices();
    }

    /** Changer manuellement le statut libre <-> maintenance : MP + Admin. */
    public function updateStatus(User $user, Panel $panel): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    public function delete(User $user, Panel $panel): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
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
