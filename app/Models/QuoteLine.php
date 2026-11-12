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
        'campaign_start', 'campaign_end',   // TX-9 (2026-07-29) — calcul auto TM/ODP
        'montant_ht_ligne',
        'odp_rate_applique', 'tm_rate_applique',
        'odp_ligne', 'tm_ligne',
        'order_index',
        // Axes Lumipub (2026-11-12) : pieds proposés, ODP/TM saisies à la main.
        'pieds', 'odp_amount_override', 'tm_amount_override',
    ];

    protected $casts = [
        'dimension_m2'      => 'decimal:2',
        'pu_ht_mensuel'     => 'integer',
        'quantite'          => 'integer',
        'duree_mois'        => 'decimal:2',
        'campaign_start'    => 'date',
        'campaign_end'      => 'date',
        'montant_ht_ligne'  => 'integer',
        'odp_rate_applique' => 'integer',
        'tm_rate_applique'  => 'integer',
        'odp_ligne'         => 'integer',
        'tm_ligne'          => 'integer',
        'order_index'       => 'integer',
        'pieds'               => 'array',
        'odp_amount_override' => 'integer',
        'tm_amount_override'  => 'integer',
    ];

    /** Ids des pieds proposés sur cette ligne d'axe (vide = ligne ordinaire). */
    public function idsPieds(): array
    {
        return array_values(array_map('intval', (array) ($this->pieds ?? [])));
    }

    public function quote(): BelongsTo         { return $this->belongsTo(Quote::class); }
    public function panel(): BelongsTo         { return $this->belongsTo(Panel::class); }
    public function externalPanel(): BelongsTo { return $this->belongsTo(ExternalPanel::class); }
    public function commune(): BelongsTo       { return $this->belongsTo(Commune::class); }
}
