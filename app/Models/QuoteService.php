<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service annexe du devis (impression, frais de pose, électricité…).
 * Miroir de InvoiceService.
 */
class QuoteService extends Model
{
    protected $fillable = ['quote_id', 'label', 'prix_ht', 'order_index'];

    protected $casts = [
        'prix_ht'     => 'integer',
        'order_index' => 'integer',
    ];

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
}
