<?php
// app/Policies/ReservationPolicy.php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    // Qui peut voir une réservation ?
    public function view(User $user, Reservation $reservation): bool
    {
        return true; // tous les authentifiés
    }

    // Qui peut créer ?
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'commercial', 'mediaplanner']);
    }

    // Qui peut éditer ?
    public function update(User $user, Reservation $reservation): bool
    {
        // Statut bloquant
        if (! $reservation->isEditable()) return false;

        // Client supprimé → on bloque
        if ($reservation->client?->trashed()) return false;

        // Seul l'admin ou le créateur peut modifier
        return $user->role === 'admin' || $reservation->user_id === $user->id;
    }

    // Qui peut changer le statut ?
    public function updateStatus(User $user, Reservation $reservation): bool
    {
        // Client supprimé → inutile de changer le statut
        if ($reservation->client?->trashed()) return false;

        return in_array($user->role, ['admin', 'commercial']);
    }

    // Qui peut annuler ?
    public function annuler(User $user, Reservation $reservation): bool
    {
        if (! $reservation->isCancellable()) return false;

        return $user->role === 'admin' || $reservation->user_id === $user->id;
    }

    // Qui peut supprimer ? Seulement les dossiers clos
    public function delete(User $user, Reservation $reservation): bool
    {
        if (! in_array($reservation->status->value, ['annule', 'refuse'])) return false;

        return $user->role === 'admin';
    }
}