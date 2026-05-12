<?php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

/**
 * Refonte selon docs/ROLES_VALIDES.md :
 *
 *   Admin       → tout
 *   MP          → crée + modifie + annule (créateur)
 *   Commercial  → voit ses dossiers, NE crée plus, NE modifie plus
 *                 (il intervient sur le statut côté Proposition uniquement)
 *
 * "view" reste large : tout le monde côté admin peut voir une résa
 * (filtres / portefeuilles côté UI). Les actions modifiantes sont
 * restreintes ici.
 */
class ReservationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return in_array($user->role, [
            UserRole::COMMERCIAL,
            UserRole::MEDIAPLANNER,
        ], true);
    }

    /** Créer une réservation : Media Planner uniquement. */
    public function create(User $user): bool
    {
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Modifier (panneaux, période, prix) : MP, et seulement si éditable. */
    public function update(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isEditable()) return false;
        if ($reservation->client?->trashed()) return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /**
     * Changer statut "interne" de la réservation (en_attente / confirme
     * etc. côté MP). Le statut visible client (envoyée, vue, signée) est
     * géré par PropositionPolicy.
     */
    public function updateStatus(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Annuler une réservation : MP (créateur) + Admin. */
    public function annuler(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isCancellable()) return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Supprimer : Admin uniquement (before() capte). */
    public function delete(User $user, Reservation $reservation): bool
    {
        return false;
    }
}
