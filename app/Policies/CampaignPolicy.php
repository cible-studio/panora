<?php
namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout
 *   MP          → créer/modifier/annuler/activer/pause/terminer
 *   Commercial  → voir uniquement (lecture, plus de création)
 *   Technique   → aucun accès
 *
 * Supprimer une campagne reste exclusivement Admin.
 */
class CampaignPolicy
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
        ], true);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, [
            UserRole::COMMERCIAL,
            UserRole::MEDIAPLANNER,
        ], true);
    }

    /** Créer une campagne : Media Planner uniquement. Le commercial ne crée plus. */
    public function create(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Modifier (panneaux, dates) : MP, et seulement si non terminée/annulée. */
    public function update(User $user, Campaign $campaign): bool
    {
        if ($campaign->status === CampaignStatus::TERMINE) return false;
        if ($campaign->status === CampaignStatus::ANNULE)  return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Changer statut (activer / pause / terminer / annuler) : MP. */
    public function updateStatus(User $user, Campaign $campaign): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Ajout / retrait de panneaux : MP, tant que la campagne tourne. */
    public function managePanel(User $user, Campaign $campaign): bool
    {
        if ($campaign->status === CampaignStatus::TERMINE) return false;
        if ($campaign->status === CampaignStatus::ANNULE)  return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Suppression : Admin uniquement (le before() ci-dessus capte). */
    public function delete(User $user, Campaign $campaign): bool
    {
        return false;
    }
}
