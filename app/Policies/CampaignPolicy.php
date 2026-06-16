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
            UserRole::COMPTABLE,
        ], true);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        // MP voit tout — il pilote la production de toutes les campagnes.
        // Comptable voit tout — il consolide la vision financière de la régie.
        if (in_array($user->role, [UserRole::MEDIAPLANNER, UserRole::COMPTABLE], true)) {
            return true;
        }

        // Commercial : ownership obligatoire. Sans ça, c'était une IDOR
        // (l'index filtrait mais une URL directe /admin/campaigns/{id}
        // ouvrait n'importe quelle campagne, panneaux, montant, factures
        // liées, marges — leak métier majeur).
        if ($user->role === UserRole::COMMERCIAL) {
            return $campaign->belongsToCommercialUser((int) $user->id);
        }

        return false;
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
        // TERMINE autorisé : permet de corriger l'historique (anciennes
        // campagnes importées). ANNULE reste bloqué (campagne morte).
        if ($campaign->status === CampaignStatus::ANNULE)  return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Suppression : Admin uniquement (le before() ci-dessus capte). */
    public function delete(User $user, Campaign $campaign): bool
    {
        return false;
    }
}
