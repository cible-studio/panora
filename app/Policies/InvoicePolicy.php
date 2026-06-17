<?php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

/**
 * RBAC factures :
 *
 *   Admin       → tout (view + manage + status + delete + export PDF)
 *   Comptable   → voir TOUTES, saisir paiements, marquer payé/litige,
 *                 générer/modifier échéancier, exporter PDF/rapports.
 *                 N'a PAS la création/édition/suppression de facture ni
 *                 markCancelled (réservés à l'admin pour éviter de fausser
 *                 la comptabilité par erreur).
 *   MP          → lecture seule (index/show/PDF/exports liste)
 *   Commercial  → lecture seule, RESTREINTE à SES factures (factures liées
 *                 à des campagnes qui lui sont assignées — cf.
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

    /** Création / édition / suppression / annulation : Admin uniquement. */
    public function create(User $user): bool                          { return false; }
    public function update(User $user, Invoice $invoice): bool        { return false; }
    public function delete(User $user, Invoice $invoice): bool        { return false; }
    public function markCancelled(User $user, Invoice $invoice): bool { return false; }
    public function revertDraft(User $user, Invoice $invoice): bool   { return false; }

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
