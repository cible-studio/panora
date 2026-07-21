<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Ligne de devis — miroir d'InvoiceLine.
 * 1 ligne = 1 panneau proposé pour une durée donnée.
 */
class QuoteLine extends Model implements Auditable
{
    use AuditableTrait;
    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'quote_id', 'panel_id', 'external_panel_id', 'commune_id',
        'designation', 'snapshot_commune_name', 'dimension_m2',
        'pu_ht_mensuel', 'quantite', 'duree_mois',
        'montant_ht_ligne',
        'odp_rate_applique', 'tm_rate_applique',
        'odp_ligne', 'tm_ligne',
        'order_index',
    ];

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

    public function quote(): BelongsTo         { return $this->belongsTo(Quote::class); }
    public function panel(): BelongsTo         { return $this->belongsTo(Panel::class); }
    public function externalPanel(): BelongsTo { return $this->belongsTo(ExternalPanel::class); }
    public function commune(): BelongsTo       { return $this->belongsTo(Commune::class); }
}
