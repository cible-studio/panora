<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommuneTaxPayment extends Model
{
    use HasFactory, SoftDeletes;

    public const PERIOD_MENSUEL     = 'mensuel';
    public const PERIOD_TRIMESTRIEL = 'trimestriel';
    public const PERIOD_ANNUEL      = 'annuel';

    public const PERIOD_TYPES = [
        self::PERIOD_MENSUEL,
        self::PERIOD_TRIMESTRIEL,
        self::PERIOD_ANNUEL,
    ];

    // LOT 1 — Modes de paiement (cahier 2026-06-19). Alignés sur les
    // libellés métier officiels CIBLE CI (mêmes intitulés que sur
    // l'export Excel comptable des factures).
    public const MODE_VIREMENT     = 'virement';
    public const MODE_CHEQUE       = 'cheque';
    public const MODE_ESPECES      = 'especes';
    public const MODE_MOBILE_MONEY = 'mobile_money';
    public const MODE_AUTRE        = 'autre';

    public const MODES = [
        self::MODE_VIREMENT     => '🏦 Virement',
        self::MODE_CHEQUE       => '📝 Chèque',
        self::MODE_ESPECES      => '💵 Espèces',
        self::MODE_MOBILE_MONEY => '📱 Mobile Money',
        self::MODE_AUTRE        => '🌀 Autre',
    ];

    protected $fillable = [
        'commune_id', 'period_type', 'period_year', 'period_value',
        'odp_theorique', 'tm_theorique',
        'odp_paye', 'tm_paye',
        'paid_at',
        // LOT 1 — Traçabilité paiements (cahier 2026-06-19).
        'mode', 'reference', 'comment',
        'attestation_recue', 'attestation_date', 'attestation_path',
        'notes',
        'recorded_by',
    ];

    /**
     * Libellé human-readable du mode (avec emoji) — utilisé dans les
     * tableaux et exports. Fallback sur le slug brut si inconnu.
     */
    public function getModeLabelAttribute(): ?string
    {
        return $this->mode ? (self::MODES[$this->mode] ?? ucfirst($this->mode)) : null;
    }
    // Note 2026-06-19 — l'accesseur getTotalPayeAttribute() existe déjà
    // plus bas dans le fichier (return float). On ne le re-déclare pas
    // ici pour éviter "Cannot redeclare" fatal.

    // RÈGLE 4 — entiers FCFA (audit Phase 8E : cohérence avec
    // invoice_lines.odp_ligne/tm_ligne et commune.odp_rate/tm_rate).
    protected $casts = [
        'odp_theorique'     => 'integer',
        'tm_theorique'      => 'integer',
        'odp_paye'          => 'integer',
        'tm_paye'           => 'integer',
        'paid_at'           => 'date',
        'attestation_recue' => 'boolean',
        'attestation_date'  => 'date',
    ];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getStatusAttribute(): string
    {
        $theorique = (float) $this->odp_theorique + (float) $this->tm_theorique;
        $paye      = (float) $this->odp_paye      + (float) $this->tm_paye;

        if ($paye <= 0)              return 'non_paye';
        if ($paye >= $theorique - 1) return 'paye';
        return 'partiel';
    }

    public function getTotalTheoriqueAttribute(): float
    {
        return round(
            (float) $this->odp_theorique + (float) $this->tm_theorique,
            2
        );
    }

    public function getTotalPayeAttribute(): float
    {
        return round(
            (float) $this->odp_paye + (float) $this->tm_paye,
            2
        );
    }

    public function getSoldeAttribute(): float
    {
        return round($this->total_theorique - $this->total_paye, 2);
    }
}
