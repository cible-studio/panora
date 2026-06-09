<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versement enregistré sur une facture (acompte, mensualité, solde…).
 *
 * Plusieurs paiements possibles par facture. Le statut de la facture
 * est dérivé de la somme des paiements (cf. Invoice::computePaymentStatus).
 *
 * Modes acceptés (matche l'enum migration) :
 *   especes / cheque / virement / mobile_money / compensation / autre
 */
class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'paid_at', 'montant',
        'mode', 'reference', 'note', 'created_by',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'montant' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Libellé humain du mode de paiement — source unique pour les vues
     * et le PDF (évite les divergences "Mobile money" vs "Mobile Money").
     */
    public function getModeLabelAttribute(): string
    {
        return match ($this->mode) {
            'especes'      => 'Espèces',
            'cheque'       => 'Chèque',
            'virement'     => 'Virement bancaire',
            'mobile_money' => 'Mobile money',
            'compensation' => 'Compensation (avoir)',
            default        => 'Autre',
        };
    }
}
