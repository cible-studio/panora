<?php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

/**
 * RBAC factures :
 *
 *   Admin       → tout (view + manage + status + delete + export PDF +
 *                 annulation + déverrouillage)
 *   Comptable   → 2026-07-21 périmètre élargi (demande patronne) :
 *                 - voir TOUTES les factures (consolidation compta)
 *                 - créer, modifier, envoyer une facture
 *                 - saisir versements, solder, marquer litige
 *                 - générer/modifier échéancier prévisionnel
 *                 - exporter PDF + rapports comptables
 *                 - envoyer relances
 *                 N'a PAS :
 *                 - Suppression physique d'une facture (admin only)
 *                 - Annulation d'une facture émise / passage litige avoir
 *                   (admin only — impact comptable lourd)
 *                 - Déverrouillage d'une facture envoyée (admin only —
 *                   la modification post-envoi est un cas exceptionnel
 *                   qui doit remonter à la direction)
 *   MP          → lecture seule (index/show/PDF/exports liste)
 *   Commercial  → lecture + création + édition + envoi RESTREINTS à SES
 *                 factures (campagnes assignées — cf.
 *                 Invoice::scopeForCommercialUser et belongsToCommercialUser)
 *   Technique   → aucun accès
 *
 * Cette policy ferme les IDOR factures : avant, n'importe quel commercial
 * pouvait taper /admin/invoices/{id} et accéder à la facture d'un autre
 * commercial (montant, client, PDF, lien public).
 */
class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::ADMIN) return true;
        return null;
    }

    /** Voir l'index facturation : staff sauf technique. */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::COMMERCIAL,
            UserRole::MEDIAPLANNER,
            UserRole::COMPTABLE,
        ], true);
    }

    /**
     * Voir une facture précise. Le commercial doit en être propriétaire
     * (via la campagne liée). MP + Comptable voient tout (consolidation
     * média / vision comptable globale).
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (in_array($user->role, [UserRole::MEDIAPLANNER, UserRole::COMPTABLE], true)) {
            return true;
        }
        if ($user->role === UserRole::COMMERCIAL) {
            return $invoice->belongsToCommercialUser((int) $user->id);
        }
        return false;
    }

    /**
     * Télécharger le PDF d'une facture. Même règle d'appartenance que view :
     * sans ça le commercial téléchargeait n'importe quelle facture par
     * URL directe alors qu'il ne l'avait pas dans son index filtré.
     */
    public function exportPdf(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    /**
     * Création d'une facture.
     * - Comptable : oui (2026-07-21 — élargissement périmètre patronne)
     * - Commercial : oui (déjà supporté ailleurs pour ses campagnes)
     * - Admin : oui via before()
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::COMPTABLE, UserRole::COMMERCIAL], true);
    }

    /**
     * Édition d'une facture.
     * - Comptable : oui sur toutes les factures cancellables (pas
     *   verrouillées). Une facture verrouillée nécessite un déverrouillage
     *   admin d'abord.
     * - Commercial : uniquement les siennes (ownership commercial).
     * - Admin : via before().
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Une facture verrouillée reste modifiable uniquement par l'admin
        // (via before, cette policy retourne false et l'admin passe outre).
        if ($invoice->isLocked()) return false;

        if ($user->role === UserRole::COMPTABLE) return true;

        if ($user->role === UserRole::COMMERCIAL) {
            return $invoice->belongsToCommercialUser((int) $user->id);
        }

        return false;
    }

    /**
     * Suppression physique : Admin uniquement (impact comptable irréversible).
     * L'annulation d'une facture émise passe par markCancelled (avoir).
     */
    public function delete(User $user, Invoice $invoice): bool { return false; }

    /**
     * Annulation d'une facture émise (crée un avoir en contrepartie) :
     * Admin uniquement. C'est une décision comptable lourde qui doit
     * remonter à la direction — le comptable qui veut annuler doit
     * demander à l'admin de valider.
     */
    public function markCancelled(User $user, Invoice $invoice): bool { return false; }

    /**
     * Revenir en brouillon depuis un état ultérieur : Admin uniquement.
     * (Idem markCancelled — casse la traçabilité, doit rester exceptionnel.)
     */
    public function revertDraft(User $user, Invoice $invoice): bool { return false; }

    /**
     * Transitions de statut (envoyer, marquer payé/litige) : Admin et
     * Comptable. Le comptable a besoin de pouvoir solder une facture
     * et l'enregistrer en litige (cf. cas migration + recouvrement).
     */
    public function markSent(User $user, Invoice $invoice): bool
    {
        return $user->role === UserRole::COMPTABLE;
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $user->role === UserRole::COMPTABLE;
    }
}
