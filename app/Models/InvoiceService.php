<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Service annexe d'une facture — libellé libre + prix HT.
 * Chaque service est soumis à TVA 18 %.
 *
 * Ex : "Frais d'impression", "Frais de pose et dépose", "Conception
 * créa", "Reportage photo livraison", etc.
 */
class InvoiceService extends Model implements Auditable
{
    use AuditableTrait;
    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'invoice_id', 'label', 'prix_ht', 'order_index',
    ];

    protected $casts = [
        'prix_ht'     => 'integer',
        'order_index' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
