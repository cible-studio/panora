<?php
namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use App\Enums\CampaignStatus;
use App\Enums\UserRole;

class CampaignPolicy
{
    // Admins voient tout
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::COMMERCIAL,
            UserRole::MEDIAPLANNER,
        ]);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::COMMERCIAL,
            UserRole::MEDIAPLANNER,
        ]);
    }

    public function create(User $user): bool
    {
         // Vérifier que le client passé en request n'est pas supprimé
        // La vérification complète se fait dans le controller
        return in_array($user->role, [UserRole::ADMIN, UserRole::COMMERCIAL]);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if ($campaign->status === CampaignStatus::TERMINE) return false;
        if ($campaign->status === CampaignStatus::ANNULE)  return false;
        return in_array($user->role, [UserRole::ADMIN, UserRole::COMMERCIAL]);
    }

    public function updateStatus(User $user, Campaign $campaign): bool
    {
        return in_array($user->role, [UserRole::ADMIN, UserRole::COMMERCIAL]);
    }

    public function managePanel(User $user, Campaign $campaign): bool
    {
        if ($campaign->status === CampaignStatus::TERMINE) return false;
        return in_array($user->role, [UserRole::ADMIN, UserRole::COMMERCIAL]);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $campaign->status !== CampaignStatus::ACTIF;
    }
}