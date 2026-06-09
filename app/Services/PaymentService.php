<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PaymentService — source unique pour TOUTE opération de paiement.
 *
 * Centralise (cahier §4 et §5) :
 *   - Création/suppression de paiements (avec garde sur-paiement strict)
 *   - Gestion de la pièce jointe (storage local /storage/app/invoices/payments/{id}/)
 *   - Recalcul automatique du statut de facture après chaque mouvement :
 *       paymentStatus() = soldee  → invoice.status = 'payee'
 *       paymentStatus() = partielle → invoice.status = 'partiellement_payee'
 *       paymentStatus() = en_retard → invoice.status = 'en_retard'
 *   - Historisation des transitions via Invoice::transitionStatusTo(auto=true)
 *
 * Évite la dispersion de la logique entre InvoiceController, observers
 * et vues : un seul appel `app(PaymentService::class)->register(...)`.
 */
class PaymentService
{
    /** Sous-dossier de stockage des pièces jointes (local). */
    public const ATTACHMENT_DISK = 'local';
    public const ATTACHMENT_DIR  = 'invoices/payments';

    /**
     * Enregistre un versement sur la facture.
     *
     * @param array{
     *   paid_at: string,
     *   montant: int|float,
     *   mode: string,
     *   reference?: string,
     *   bank?: string,
     *   is_acompte?: bool,
     *   note?: string,
     * } $data
     * @param UploadedFile|null $attachment Pièce justificative (scan chèque, reçu, etc.)
     * @return InvoicePayment
     *
     * @throws \DomainException si sur-paiement strict (> total_a_payer + 1 FCFA)
     * @throws \DomainException si facture annulée
     */
    public function register(Invoice $invoice, array $data, ?UploadedFile $attachment = null): InvoicePayment
    {
        if ($invoice->status === 'annulee') {
            throw new \DomainException('Impossible d\'enregistrer un versement sur une facture annulée.');
        }

        $montant = (int) round((float) $data['montant']);
        if ($montant <= 0) {
            throw new \DomainException('Le montant doit être strictement positif.');
        }

        // Garde stricte (cahier §5 livrables : "total encaissé ≤ total facturé")
        // Tolérance de 1 FCFA pour arrondis. Au-delà : forcer émission d'avoir.
        $totalDue   = (int) ($invoice->total_a_payer ?: $invoice->amount_ttc ?: 0);
        $newTotal   = (int) $invoice->paidAmount() + $montant;
        if ($totalDue > 0 && $newTotal > $totalDue + 1) {
            $depassement = $newTotal - $totalDue;
            throw new \DomainException(
                'Sur-paiement bloqué : ce versement de '
                . number_format($montant, 0, ',', ' ') . ' FCFA porterait le total encaissé à '
                . number_format($newTotal, 0, ',', ' ') . ' FCFA, soit '
                . number_format($depassement, 0, ',', ' ') . ' FCFA au-dessus du total facturé. '
                . 'Si c\'est un trop-perçu réel, émets un avoir au lieu d\'ajouter ce versement.'
            );
        }

        return DB::transaction(function () use ($invoice, $data, $attachment, $montant) {
            $payment = $invoice->payments()->create([
                'paid_at'    => $data['paid_at'],
                'montant'    => $montant,
                'mode'       => $data['mode'],
                'reference'  => $data['reference'] ?? null,
                'bank'       => $data['bank'] ?? null,
                'is_acompte' => (bool) ($data['is_acompte'] ?? false),
                'note'       => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($attachment) {
                $this->storeAttachment($payment, $attachment);
            }

            // Recalcul auto du statut facture
            $this->syncInvoiceStatus($invoice->fresh(['payments', 'schedules']));

            Log::info('payment.registered', [
                'invoice_id' => $invoice->id,
                'reference'  => $invoice->reference,
                'payment_id' => $payment->id,
                'montant'    => $montant,
                'mode'       => $data['mode'],
                'is_acompte' => $payment->is_acompte,
            ]);

            return $payment;
        });
    }

    public function delete(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;
        DB::transaction(function () use ($payment, $invoice) {
            // Supprime la pièce jointe si elle existe
            if ($payment->attachment_path && Storage::disk(self::ATTACHMENT_DISK)->exists($payment->attachment_path)) {
                Storage::disk(self::ATTACHMENT_DISK)->delete($payment->attachment_path);
            }
            $payment->delete();
            $this->syncInvoiceStatus($invoice->fresh(['payments', 'schedules']));
        });

        Log::info('payment.deleted', [
            'invoice_id' => $invoice->id,
            'reference'  => $invoice->reference,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Synchronise invoice.status avec le statut dérivé des paiements.
     *
     * Cahier §3 :
     *   - "Émise → partiellement_payee dès qu'un paiement existe avec solde > 0"
     *   - "→ payee quand solde ≤ 0"
     *   - "→ en_retard quand une échéance est dépassée et solde > 0"
     *
     * Ne touche PAS aux statuts manuels :
     *   - brouillon, generee : avant émission, on ne synchronise pas
     *   - validee : en attente d'envoi, idem
     *   - litige, annulee : statuts manuels figés
     */
    public function syncInvoiceStatus(Invoice $invoice): void
    {
        // Statuts non synchronisables : aucune transition auto depuis ces états
        if (in_array($invoice->status, ['brouillon', 'generee', 'validee', 'litige', 'annulee'], true)) {
            return;
        }

        $paymentStatus = $invoice->paymentStatus(); // dérive
        $target = match ($paymentStatus) {
            'soldee'    => 'payee',
            'en_retard' => 'en_retard',
            'partielle' => 'partiellement_payee',
            'non_payee' => 'envoyee', // ⇐ pas de versement mais facture envoyée
            default     => null,
        };

        if ($target && $target !== $invoice->status) {
            $invoice->transitionStatusTo(
                $target,
                reason: "Auto (sync depuis paiements) : paymentStatus={$paymentStatus}",
                auto:   true
            );

            // Si soldée → poser paid_at si pas déjà défini
            if ($target === 'payee' && !$invoice->paid_at) {
                $invoice->paid_at = $invoice->payments()->max('paid_at') ?? now()->toDateString();
                $invoice->saveQuietly();
            }
        }
    }

    /**
     * Stocke la pièce jointe sur le disk local et met à jour le modèle
     * (attachment_path + attachment_original_name).
     */
    protected function storeAttachment(InvoicePayment $payment, UploadedFile $file): void
    {
        $dir = self::ATTACHMENT_DIR . '/' . $payment->id;
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = uniqid('pay_', true) . '.' . $ext;

        $path = $file->storeAs($dir, $filename, self::ATTACHMENT_DISK);

        $payment->update([
            'attachment_path'           => $path,
            'attachment_original_name'  => mb_substr($file->getClientOriginalName(), 0, 200),
        ]);
    }
}
