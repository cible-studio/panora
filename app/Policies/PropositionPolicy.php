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
     * Soumettre / changer le commercial assigné au dossier.
     * Réservé au MP — son action principale.
     *
     * NOTE : autorisé MÊME APRÈS l'envoi au client (PROPOSITION_SENT) car
     * le bouton "Changer de commercial" reste permanent pour le MP — il
     * peut réassigner le suivi à un autre commercial à tout moment.
     * Le mail déjà parti au client n'est pas affecté.
     *
     * Bloqué uniquement si la résa est annulée/refusée/terminée (statut
     * final, plus de suivi commercial nécessaire).
     */
    public function submit(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        // Statut métier final : pas de réassignation utile.
        if (in_array($reservation->status?->value, ['annule', 'refuse'], true)) {
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
     *
     * Conditions :
     *   - Le user est l'un des commerciaux assignés au dossier (ou admin
     *     via before).
     *   - Le proposition_status est pending_send, sent (renvoi possible),
     *     OU draft/prepared MAIS avec un commercial assigné explicitement
     *     (cas où la résa a été créée avec commercial sans passer par
     *     "soumettre" — assignation directe à la création).
     */
    public function send(User $user, Reservation $reservation): bool
    {
        if ($reservation->client?->trashed()) return false;
        if ($user->role !== UserRole::COMMERCIAL) return false;

        // Statuts explicitement prêts pour envoi.
        if (in_array($reservation->proposition_status, [
            Reservation::PROPOSITION_PENDING_SEND,
            Reservation::PROPOSITION_SENT, // autorise renvoi
        ], true)) {
            return true;
        }

        // Statut draft/prepared mais un commercial est assigné : c'est le
        // cas des résa créées avec commercial direct (sans étape soumettre).
        // Le commercial peut envoyer immédiatement.
        if (!empty($reservation->commercial_user_id)
            && $reservation->commercial_user_id === $user->id) {
            return true;
        }

        return false;
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
