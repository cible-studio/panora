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
        if (!in_array($reservation->proposition_status, Reservation::PROPOSITION_STATUSES_BEFORE_SEND, true)) {
            return false;
        }
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /**
     * Soumettre au commercial : draft|prepared → pending_send.
     * Réservé au MP — son action principale.
     */
    public function submit(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        // Doit être avant envoi.
        if ($reservation->proposition_status === Reservation::PROPOSITION_SENT) {
            return false;
        }
        return $user->role === UserRole::MEDIAPLANNER;
    }

    /** Alias historique pour compat (anciens appels). */
    public function markReady(User $user, Reservation $reservation): bool
    {
        return $this->submit($user, $reservation);
    }

    /**
     * Envoyer la proposition par email au client.
     * Réservé au Commercial : signe avec son nom + ses coordonnées.
     * Le statut doit être pending_send (soumis par le MP) ou sent
     * (renvoi possible). En draft/prepared, le commercial n'est pas
     * encore censé recevoir le dossier.
     */
    public function send(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        if ($user->role !== UserRole::COMMERCIAL) return false;
        return in_array($reservation->proposition_status, [
            Reservation::PROPOSITION_PENDING_SEND,
            Reservation::PROPOSITION_SENT, // autorise renvoi
        ], true);
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
