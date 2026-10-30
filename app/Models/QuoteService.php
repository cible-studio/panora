<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service annexe du devis (impression, frais de pose, électricité…).
 * Miroir de InvoiceService.
 *
 * `tva_applicable` (2026-10-30) : chaque service est soumis à la TVA par
 * défaut, mais on peut la retirer sur un service donné — un frais
 * refacturé déjà taxé par le fournisseur, typiquement. Le devis annonce
 * ainsi le même montant que la facture qui en découlera.
 */
class QuoteService extends Model
{
    protected $fillable = ['quote_id', 'label', 'prix_ht', 'tva_applicable', 'order_index'];

    protected $casts = [
        'prix_ht'        => 'integer',
        'tva_applicable' => 'boolean',
        'order_index'    => 'integer',
    ];

    /** Montant TTC de ce service : avec ou sans TVA selon le choix. */
    public function montantTtc(float $tauxTva): int
    {
        return (int) round($this->prix_ht * ($this->tva_applicable ? 1 + $tauxTva / 100 : 1));
    }

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
}
