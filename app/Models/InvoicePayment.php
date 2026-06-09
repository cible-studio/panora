<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Versement enregistré sur une facture (acompte, mensualité, solde…).
 *
 * Plusieurs paiements possibles par facture. Le statut de la facture
 * est dérivé de la somme des paiements (cf. Invoice::computePaymentStatus).
 *
 * Modes acceptés (matche l'enum migration) :
 *   especes / cheque / virement / mobile_money / compensation / autre
 */
class InvoicePayment extends Model implements Auditable
{
    use AuditableTrait;
    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'invoice_id', 'paid_at', 'montant',
        'mode', 'reference', 'bank',
        'is_acompte', 'attachment_path', 'attachment_original_name',
        'note', 'created_by',
    ];

    protected $casts = [
        'paid_at'    => 'date',
        'montant'    => 'integer',
        'is_acompte' => 'boolean',
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
            'especes'        => 'Espèces',
            'cheque'         => 'Chèque',
            'virement'       => 'Virement bancaire',
            'mobile_money'   => 'Mobile money',
            'carte_bancaire' => 'Carte bancaire',
            'compensation'   => 'Compensation (avoir)',
            default          => 'Autre',
        };
    }

    public function getModeIconAttribute(): string
    {
        return match ($this->mode) {
            'especes'        => '💵',
            'cheque'         => '📝',
            'virement'       => '🏦',
            'mobile_money'   => '📱',
            'carte_bancaire' => '💳',
            'compensation'   => '🔄',
            default          => '💰',
        };
    }

    /** Liste des modes acceptés (matche l'enum migration + cahier §4). */
    public const MODES = [
        'especes', 'cheque', 'virement', 'mobile_money',
        'carte_bancaire', 'compensation', 'autre',
    ];
}
