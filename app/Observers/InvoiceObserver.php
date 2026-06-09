<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoiceStatusHistory;
use Illuminate\Support\Facades\Log;

/**
 * Historise les changements de statut de facture (§3 et §13 du cahier
 * Recouvrement).
 *
 * Chaque transition (manuelle ou auto) génère une ligne dans
 * invoice_status_history :
 *   {from_status, to_status, user_id, reason, auto}
 *
 * La détection se fait via $invoice->getOriginal('status') vs la nouvelle
 * valeur. Comme l'observer écoute updated() ET created() :
 *   - created()  → ligne avec from_status=NULL (initialisation)
 *   - updated()  → ligne avec from_status=ancien si le statut a changé
 *
 * Le champ "auto" est posé à true par défaut. Les contrôleurs qui font
 * une transition manuelle peuvent passer une variable temporaire via
 * $invoice->_transitionMeta = ['reason'=>..., 'auto'=>false] pour
 * surcharger (cf. PaymentService et controller actions).
 */
class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        InvoiceStatusHistory::create([
            'invoice_id'  => $invoice->id,
            'from_status' => null,
            'to_status'   => $invoice->status,
            'user_id'     => auth()->id(),
            'reason'      => 'Création',
            'auto'        => false,
        ]);
    }

    public function updated(Invoice $invoice): void
    {
        if (!$invoice->wasChanged('status')) return;

        $from = $invoice->getOriginal('status');
        $to   = $invoice->status;

        // Métadonnées posées par le code appelant (controller, service…)
        $meta = $invoice->_transitionMeta ?? [];
        $reason = $meta['reason'] ?? null;
        $auto   = $meta['auto']   ?? true;
        $userId = $meta['user_id'] ?? auth()->id();

        InvoiceStatusHistory::create([
            'invoice_id'  => $invoice->id,
            'from_status' => $from,
            'to_status'   => $to,
            'user_id'     => $userId,
            'reason'      => $reason,
            'auto'        => $auto,
        ]);

        Log::info('invoice.status.transition', [
            'invoice_id' => $invoice->id,
            'reference'  => $invoice->reference,
            'from'       => $from,
            'to'         => $to,
            'auto'       => $auto,
            'user_id'    => $userId,
            'reason'     => $reason,
        ]);

        // Nettoyage du marqueur temporaire pour éviter la fuite sur le
        // prochain save() de la même instance.
        unset($invoice->_transitionMeta);
    }
}
