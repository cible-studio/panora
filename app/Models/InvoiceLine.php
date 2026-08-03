<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

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
class InvoiceLine extends Model implements Auditable
{
    use AuditableTrait;
    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'invoice_id', 'panel_id', 'external_panel_id', 'commune_id',
        'designation', 'snapshot_commune_name', 'dimension_m2',
        'pu_ht_mensuel', 'quantite', 'duree_mois',
        'campaign_start', 'campaign_end',   // TX-9 (2026-07-29) — calcul auto TM/ODP
        'montant_ht_ligne',
        'odp_rate_applique', 'tm_rate_applique',
        'odp_ligne', 'tm_ligne',
        // Overrides montant total ODP/TM par ligne (ajout 2026-08-03).
        // Si présents, remplacent la valeur auto-calculée. NULL = auto.
        'odp_amount_override', 'tm_amount_override',
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
        'campaign_start'    => 'date',
        'campaign_end'      => 'date',
        'montant_ht_ligne'  => 'integer',
        'odp_rate_applique' => 'integer',
        'tm_rate_applique'  => 'integer',
        'odp_ligne'         => 'integer',
        'tm_ligne'          => 'integer',
        'odp_amount_override' => 'integer',
        'tm_amount_override'  => 'integer',
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

    public function externalPanel(): BelongsTo
    {
        return $this->belongsTo(ExternalPanel::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
