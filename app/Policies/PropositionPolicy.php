<?php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

/**
 * Policy dédiée au workflow Proposition (construction → envoi → décision).
 *
 * Une "Proposition" est un état métier d'une Reservation (la même entité
 * BDD). On y associe des actions distinctes de la gestion technique de
 * la résa pour pouvoir donner des droits différents :
 *
 *   Admin       → tout
 *   MP          → construire (panneaux/tarif/option-ferme) + soumettre
 *                 au commercial ; PAS d'envoi direct au client.
 *   Commercial  → envoyer la proposition préparée au client par mail
 *                 (signe avec son nom + ses coordonnées) ; relancer.
 *
 * Le client signe / refuse → ce n'est pas géré ici (espace client séparé).
 *
 * Statuts proposition (cf. Reservation->proposition_status — à étendre
 * dans le commit 3) :
 *   draft        — en construction par MP
 *   prepared     — MP a terminé, ready
 *   pending_send — assignée à un commercial pour envoi
 *   envoyee      — commercial a envoyé l'email
 *   vue / signee / refusee / expiree
 */
class PropositionPolicy
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

    /**
     * Construire / modifier la proposition (panneaux, tarifs, option vs
     * ferme, période). Réservé au MP, et seulement tant que la résa
     * n'est pas encore envoyée au client.
     */
    public function build(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;

        $status = $reservation->proposition_status ?? null;
        // Non-éditable une fois envoyée — sécurité de cohérence avec le client.
        if (in_array($status, ['envoyee', 'vue', 'signee', 'refusee', 'expiree'], true)) {
            return false;
        }
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /**
     * Marquer la proposition comme "prête à envoyer" (transition draft →
     * prepared/pending_send). Réservé au MP.
     */
    public function markReady(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /**
     * Envoyer la proposition par email au client. Réservé au Commercial
     * (signe avec son nom + coordonnées). Le MP n'a pas accès au bouton.
     *
     * On accepte aussi un envoi depuis l'état "draft" si le commercial
     * intervient sur ses propres anciens dossiers — c'est l'admin qui
     * tranche au cas par cas via le rôle qu'il porte.
     */
    public function send(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        return $user->role === UserRole::COMMERCIAL;
    }

    /**
     * Relancer un client (mail de rappel). Commercial uniquement.
     */
    public function relancer(User $user, Reservation $reservation): bool
    {
        return $user->role === UserRole::COMMERCIAL;
    }

    /**
     * Annuler une proposition (avant envoi) : MP. Après envoi : Admin.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        $status = $reservation->proposition_status ?? null;
        $sent = in_array($status, ['envoyee', 'vue', 'signee'], true);
        if ($sent) return false; // admin only via before()
        return $user->role === UserRole::MEDIAPLANNER;
    }
}
