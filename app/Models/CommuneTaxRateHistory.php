<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historique tarifaire des taxes communales (ODP / TM / DB).
 *
 * Chaque modification d'un tarif sur la fiche commune génère une ligne
 * ici via un observer — permet de recalculer un montant passé en
 * utilisant les tarifs en vigueur à l'époque (essentiel pour la
 * justification auprès des administrations).
 *
 * Lookup d'un tarif à la date D :
 *   SELECT * FROM commune_tax_rate_history
 *   WHERE commune_id = ?
 *     AND effective_from <= D
 *     AND (effective_to IS NULL OR effective_to >= D)
 *   ORDER BY effective_from DESC LIMIT 1
 */
class CommuneTaxRateHistory extends Model
{
    protected $table = 'commune_tax_rate_history';

    protected $fillable = [
        'commune_id', 'odp_rate', 'tm_rate', 'db_rate',
        'effective_from', 'effective_to', 'notes', 'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'odp_rate'       => 'decimal:2',
        'tm_rate'        => 'decimal:2',
        'db_rate'        => 'decimal:2',
    ];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
