<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Service annexe d'une facture — libellé libre + prix HT + flag TVA.
 *
 * Par défaut chaque service est soumis à TVA 18 % (comportement
 * historique). Depuis 2026-08-03 (demande patronne) : le comptable
 * peut décocher `tva_applicable` sur un service donné — le service
 * est alors facturé HT strict (prix_ht = TTC), cas typique des
 * frais annexes refacturés sans TVA re-appliquée (impression
 * fournisseur externe déjà taxée, etc.).
 *
 * Ex : "Frais d'impression", "Frais de pose et dépose", "Conception
 * créa", "Reportage photo livraison", etc.
 */
class InvoiceService extends Model implements Auditable
{
    use AuditableTrait;
    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'invoice_id', 'label', 'prix_ht', 'tva_applicable', 'order_index',
    ];

    protected $casts = [
        'prix_ht'        => 'integer',
        'tva_applicable' => 'boolean',
        'order_index'    => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
