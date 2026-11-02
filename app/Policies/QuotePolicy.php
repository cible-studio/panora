<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;
use App\Support\Acces;

/**
 * RBAC devis :
 *
 *   Admin       → tout (via before)
 *   Commercial  → création + édition + envoi + conversion RESTREINTS à
 *                 SES devis (ownership commercial_user_id)
 *   Comptable   → aucun accès depuis le 2026-11-02 (config/acces.php,
 *                 module « devis » — les devis restent à l'espace commercial)
 *   MP          → lecture seule TOUS devis (des devis bloquent des
 *                 panneaux — anticipation planification poses)
 *   Technique   → aucun accès
 */
class QuotePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // La suppression suit sa propre règle, admin compris (jamais un devis accepté).
        if ($user->role === UserRole::ADMIN && $ability !== 'delete') return true;
        return null;
    }

    /** Voir la liste des devis : rôles du module « devis » (config/acces.php). */
    public function viewAny(User $user): bool
    {
        return Acces::peut('devis', $user);
    }

    /** Voir un devis précis. Commercial : ownership. MP : tous, en lecture. */
    public function view(User $user, Quote $quote): bool
    {
        if (!Acces::peut('devis', $user)) {
            return false;
        }
        if ($user->role === UserRole::COMMERCIAL) {
            return $quote->belongsToCommercialUser((int) $user->id);
        }
        return true;
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

    /**
     * Modifier UNIQUEMENT les prix (pu_ht_mensuel des lignes panneau
     * + prix_ht des services) d'un devis modifiable : commercial
     * propriétaire (admin via before). 2026-11-02 : plus le comptable ni le
     * MP (User::canEditPrices les incluait) — MP en lecture seule, comptable
     * sans accès aux devis.
     *
     * Distinct de update() qui reste réservé au commercial owner
     * pour la structure du devis (ajout/retrait panneaux, période,
     * client, remise, envoi). updatePrice n'expose que le tarif.
     */
    public function updatePrice(User $user, Quote $quote): bool
    {
        if (!$quote->isEditable()) return false;

        // Commercial owner : mêmes règles que update()
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

    /**
     * Mettre à la corbeille (2026-10-29, direction) : jamais un devis accepté
     * ou devenu réservation ; l'admin, tout le reste ; le commercial, ses
     * brouillons.
     */
    public function delete(User $user, Quote $quote): bool
    {
        if ($quote->aEteAccepte()) return false;
        if ($user->role === UserRole::ADMIN) return true;

        return $user->role === UserRole::COMMERCIAL
            && $quote->status === QuoteStatus::BROUILLON
            && $quote->belongsToCommercialUser((int) $user->id);
    }

    /** Restaurer depuis la corbeille : admin uniquement (via before). */
    public function restore(User $user, Quote $quote): bool { return false; }
}
