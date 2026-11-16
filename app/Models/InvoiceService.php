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
        'invoice_id', 'label', 'quantite', 'prix_ht', 'tva_applicable', 'order_index',
    ];

    protected $casts = [
        'quantite'       => 'integer',
        'prix_ht'        => 'integer',
        'tva_applicable' => 'boolean',
        'order_index'    => 'integer',
    ];

    /**
     * Montant HT de la ligne (2026-11-16).
     *
     * `prix_ht` est le prix UNITAIRE depuis l'ajout de la quantité. Les
     * lignes antérieures ont quantite = 1 : leur montant ne change pas.
     * Même règle que QuoteService::montantHt() — un devis accepté devient
     * une facture, les deux doivent compter pareil.
     */
    public function montantHt(): int
    {
        return (int) ($this->prix_ht * max(1, (int) ($this->quantite ?: 1)));
    }

    /** Montant TTC : avec ou sans TVA selon `tva_applicable`. */
    public function montantTtc(float $tauxTva): int
    {
        return (int) round($this->montantHt() * ($this->tva_applicable ? 1 + $tauxTva / 100 : 1));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
