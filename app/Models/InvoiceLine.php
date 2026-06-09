<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de facture FNE.
 *
 * Une ligne = un emplacement panneau (interne ou externe) facturé pour
 * une durée donnée. Tous les montants sont stockés en base (pas
 * recalculés au vol) : la facture est un document fiscal figé.
 *
 * Quand la facture passe à l'état "envoyée" la ligne est verrouillée
 * via invoice.locked_at (cf. trait/policy à venir).
 */
class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'panel_id', 'commune_id',
        'designation', 'snapshot_commune_name', 'dimension_m2',
        'pu_ht_mensuel', 'quantite', 'duree_mois',
        'montant_ht_ligne',
        'odp_rate_applique', 'tm_rate_applique',
        'odp_ligne', 'tm_ligne',
        'order_index',
    ];

    /**
     * Casts FNE — RÈGLE D'OR #4 :
     *   - Montants FCFA → integer (pas de centimes)
     *   - Surface m² + durée mois → decimal (12.5 m², 0.5 mois possibles)
     */
    protected $casts = [
        'dimension_m2'      => 'decimal:2',
        'pu_ht_mensuel'     => 'integer',
        'quantite'          => 'integer',
        'duree_mois'        => 'decimal:2',
        'montant_ht_ligne'  => 'integer',
        'odp_rate_applique' => 'integer',
        'tm_rate_applique'  => 'integer',
        'odp_ligne'         => 'integer',
        'tm_ligne'          => 'integer',
        'order_index'       => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
