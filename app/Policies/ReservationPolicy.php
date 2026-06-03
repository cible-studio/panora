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

    /** Créer une réservation : Media Planner et Commercial. */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::MEDIAPLANNER,
            UserRole::COMMERCIAL,
        ], true);
    }

    /** Modifier (panneaux, période, prix) : MP et Commercial (sur les
     *  siennes, le scope view filtre déjà), seulement si éditable. */
    public function update(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isEditable()) return false;
        if ($reservation->client?->trashed()) return false;
        return in_array($user->role, [
            UserRole::MEDIAPLANNER,
            UserRole::COMMERCIAL,
        ], true);
    }

    /**
     * Changer statut (Confirmer/Refuser) : ADMIN UNIQUEMENT.
     * Action de fallback exceptionnel quand le client ne peut pas
     * confirmer/refuser via le lien proposition. Le MP ne valide pas
     * une signature à la place du client.
     */
    public function updateStatus(User $user, Reservation $reservation): bool
    {
        return false;
    }

    /**
     * Annuler une réservation :
     *   - Admin (via before)  : toujours
     *   - MP                  : oui, tant que pas encore confirmée par
     *                           le client (status='confirme' = signée).
     *                           Cela couvre les résa en_attente que le
     *                           MP veut annuler avant ou après envoi
     *                           commercial (ex: erreur, client refuse
     *                           verbalement, demande de remplacement).
     *   - Commercial          : non — il n'a pas la responsabilité
     *                           d'annuler, il relance.
     *
     * Cf. docs/ROLES_VALIDES.md : "Annuler proposition déjà signée par
     * le client (admin only)" — donc avant signature, MP autorisé.
     */
    public function annuler(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isCancellable()) return false;
        if ($reservation->client?->trashed()) return false;
        // Si status = 'confirme' (signée client), seul admin (before).
        if ($reservation->status?->value === 'confirme') return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /**
     * Supprimer dur (DELETE BDD) :
     *   - Admin (via before)  : toujours quand isDeletable().
     *   - MP                  : seulement les résa qu'il vient de créer
     *                           ET qui sont annulées/refusées (jamais
     *                           transmises au client en cours de
     *                           validité). Permet la suppression d'une
     *                           erreur de saisie.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isDeletable()) return false;
        if ($user->role !== UserRole::MEDIAPLANNER) return false;
        // MP : uniquement ses propres résa
        return $reservation->user_id === $user->id;
    }
}
