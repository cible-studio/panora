<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Pige;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout (peut surcharger un MP en cas de litige)
 *   MP          → voir + uploader + valider/rejeter
 *   Commercial  → ZIP download de ses propres campagnes uniquement
 *                 (consultation pas d'action sur les piges)
 *   Technique   → upload via /pige/{token} public (pas par cette policy)
 *   Client      → ses propres piges via espace client (pas par cette policy)
 */
class PigePolicy
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

    public function view(User $user, Pige $pige): bool
    {
        if ($user->role === UserRole::MEDIAPLANNER) return true;

        // Commercial : voit les piges de ses propres campagnes (vue ZIP
        // download). On filtre via la relation campagne → user_id.
        if ($user->role === UserRole::COMMERCIAL) {
            return $pige->campaign?->user_id === $user->id;
        }
        return false;
    }

    /** Uploader une pige depuis l'admin (cas rare : MP qui complète). */
    public function create(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Valider une pige (status → verifie). MP. */
    public function verify(User $user, Pige $pige): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Rejeter une pige (status → rejete). MP. */
    public function reject(User $user, Pige $pige): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Modifier (notes, statut). MP. */
    public function update(User $user, Pige $pige): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Supprimer : MP, mais pas une pige déjà validée (admin only). */
    public function delete(User $user, Pige $pige): bool
    {
        if ($pige->status === 'verifie') return false; // admin only via before()
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Télécharger ZIP : MP + Commercial (sur ses campagnes). */
    public function downloadZip(User $user): bool
    {
        return in_array($user->role, [
            UserRole::MEDIAPLANNER,
            UserRole::COMMERCIAL,
        ], true);
    }
}
