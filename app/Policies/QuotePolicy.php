<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;

/**
 * RBAC devis :
 *
 *   Admin       → tout (via before)
 *   Commercial  → création + édition + envoi + conversion RESTREINTS à
 *                 SES devis (ownership commercial_user_id)
 *   Comptable   → lecture seule TOUS devis (visibilité pipeline
 *                 commercial pour anticiper la facturation à venir)
 *   MP          → lecture seule TOUS devis (visibilité besoins terrain
 *                 futurs — anticipation planification poses)
 *   Technique   → aucun accès
 */
class QuotePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    /** Voir la liste des devis. */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::COMMERCIAL,
            UserRole::COMPTABLE,
            UserRole::MEDIAPLANNER,
        ], true);
    }

    /** Voir un devis précis. Commercial : ownership. Autres staff : oui. */
    public function view(User $user, Quote $quote): bool
    {
        if (in_array($user->role, [UserRole::COMPTABLE, UserRole::MEDIAPLANNER], true)) {
            return true;
        }
        if ($user->role === UserRole::COMMERCIAL) {
            return $quote->belongsToCommercialUser((int) $user->id);
        }
        return false;
    }

    /** Créer un devis : Commercial (via before pour admin). */
    public function create(User $user): bool
    {
        return $user->role === UserRole::COMMERCIAL;
    }

    /**
     * Modifier un devis :
     * - Statut modifiable (brouillon / en_negociation) obligatoire
     * - Ownership pour commercial
     */
    public function update(User $user, Quote $quote): bool
    {
        if (!$quote->isEditable()) return false;
        if ($user->role === UserRole::COMMERCIAL) {
            return $quote->belongsToCommercialUser((int) $user->id);
        }
        return false;
    }

    /** Envoyer au client. */
    public function send(User $user, Quote $quote): bool
    {
        if (!$quote->isSendable()) return false;
        if ($user->role === UserRole::COMMERCIAL) {
            return $quote->belongsToCommercialUser((int) $user->id);
        }
        return false;
    }

    /** Convertir en réservation ferme (après acceptation client). */
    public function convert(User $user, Quote $quote): bool
    {
        if (!$quote->isConvertible()) return false;
        if ($user->role === UserRole::COMMERCIAL) {
            return $quote->belongsToCommercialUser((int) $user->id);
        }
        return false;
    }

    /** Télécharger le PDF (même règle que view). */
    public function exportPdf(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote);
    }

    /**
     * Prolonger la validité (nouveau expires_at = now + valid_days).
     * Autorisé pour devis envoyé pas encore décidé (non expiré, non refusé, non accepté).
     */
    public function extend(User $user, Quote $quote): bool
    {
        if ($quote->status !== QuoteStatus::ENVOYE) return false;
        if ($user->role === UserRole::COMMERCIAL) {
            return $quote->belongsToCommercialUser((int) $user->id);
        }
        return false;
    }

    /** Dupliquer un devis (pour créer une variante v2). */
    public function duplicate(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $user->role === UserRole::COMMERCIAL;
    }

    /** Archiver un devis terminé (refusé/expiré) — retirer de la vue active. */
    public function archive(User $user, Quote $quote): bool
    {
        if (in_array($quote->status, [
            QuoteStatus::REFUSE,
            QuoteStatus::EXPIRE,
            QuoteStatus::ACCEPTE,
        ], true)) {
            if ($user->role === UserRole::COMMERCIAL) {
                return $quote->belongsToCommercialUser((int) $user->id);
            }
        }
        return false;
    }

    /** Supprimer physiquement : Admin uniquement. */
    public function delete(User $user, Quote $quote): bool { return false; }
}
