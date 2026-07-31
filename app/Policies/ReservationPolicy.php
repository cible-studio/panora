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
        // MP voit toutes les résas (consolidation production).
        if ($user->role === UserRole::MEDIAPLANNER) return true;

        // Commercial : ownership obligatoire. Avant : un commercial pouvait
        // ouvrir /admin/reservations/{id} de n'importe qui par URL directe
        // (panneaux, client, montant, propositions, historique) alors que
        // l'index lui filtrait correctement. Fermeture de l'IDOR.
        if ($user->role === UserRole::COMMERCIAL) {
            $uid = (int) $user->id;
            return (int) ($reservation->commercial_user_id ?? 0) === $uid
                || ($reservation->commercial_user_id === null
                    && (int) ($reservation->user_id ?? 0) === $uid);
        }

        return false;
    }

    /** Créer une réservation : Media Planner et Commercial. */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::MEDIAPLANNER,
            UserRole::COMMERCIAL,
        ], true);
    }

    /** Modifier (panneaux, période, prix) :
     *   - MP : toutes les réservations (consolidation production)
     *   - Commercial : UNIQUEMENT les siennes (ownership commercial_user_id ==
     *     uid, ou créateur fallback) — ferme IDOR sur les actions d'écriture
     *   - Toujours conditionné à isEditable() + client non supprimé
     */
    public function update(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isEditable()) return false;
        if ($reservation->client?->trashed()) return false;

        if ($user->role === UserRole::MEDIAPLANNER) return true;

        if ($user->role === UserRole::COMMERCIAL) {
            $uid = (int) $user->id;
            return (int) ($reservation->commercial_user_id ?? 0) === $uid
                || ($reservation->commercial_user_id === null
                    && (int) ($reservation->user_id ?? 0) === $uid);
        }

        return false;
    }

    /**
     * Modifier UNIQUEMENT les prix négociés (unit_price sur pivot
     * reservation_panels, internes + externes). Cible : le Comptable
     * qui coordonne les futures factures sans avoir à toucher aux
     * panneaux, dates ou statut de la résa.
     *
     * Élargit update() : garde les droits MP + Commercial owner, ajoute
     * le Comptable (User::canEditPrices()). Toujours borné par
     * isEditable() — une résa terminée / annulée reste verrouillée.
     */
    public function updatePrice(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isEditable()) return false;
        if ($reservation->client?->trashed()) return false;

        if ($user->canEditPrices()) return true;

        // Commercial owner : mêmes règles que update()
        if ($user->role === UserRole::COMMERCIAL) {
            $uid = (int) $user->id;
            return (int) ($reservation->commercial_user_id ?? 0) === $uid
                || ($reservation->commercial_user_id === null
                    && (int) ($reservation->user_id ?? 0) === $uid);
        }

        return false;
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
     *   - MP                  : oui, sur toute résa annulable (en_attente
     *                           ou confirmée). Cf. arbitrage patronne
     *                           2026-07-17 : le MP gère les cas où le
     *                           client refuse verbalement après signature,
     *                           demande un remplacement, ou annule avant
     *                           la pose. Le MP porte la responsabilité
     *                           métier de libérer les panneaux dès que
     *                           l'accord est rompu — inutile de faire
     *                           remonter chaque annulation à l'admin.
     *   - Commercial          : non — il n'a pas la responsabilité
     *                           d'annuler, il relance.
     *
     * Cf. docs/ROLES_VALIDES.md — section mise à jour après cet arbitrage.
     */
    public function annuler(User $user, Reservation $reservation): bool
    {
        if (!$reservation->isCancellable()) return false;
        if ($reservation->client?->trashed()) return false;
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
