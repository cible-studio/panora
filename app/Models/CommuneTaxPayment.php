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

    protected $fillable = [
        'commune_id', 'period_type', 'period_year', 'period_value',
        'odp_theorique', 'tm_theorique', 'db_theorique',
        'odp_paye', 'tm_paye', 'db_paye',
        'paid_at',
        'attestation_recue', 'attestation_date', 'attestation_path',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'odp_theorique'     => 'decimal:2',
        'tm_theorique'      => 'decimal:2',
        'db_theorique'      => 'decimal:2',
        'odp_paye'          => 'decimal:2',
        'tm_paye'           => 'decimal:2',
        'db_paye'           => 'decimal:2',
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
        $theorique = (float) $this->odp_theorique + (float) $this->tm_theorique + (float) $this->db_theorique;
        $paye      = (float) $this->odp_paye      + (float) $this->tm_paye      + (float) $this->db_paye;

        if ($paye <= 0)              return 'non_paye';
        if ($paye >= $theorique - 1) return 'paye';
        return 'partiel';
    }

    public function getTotalTheoriqueAttribute(): float
    {
        return round(
            (float) $this->odp_theorique + (float) $this->tm_theorique + (float) $this->db_theorique,
            2
        );
    }

    public function getTotalPayeAttribute(): float
    {
        return round(
            (float) $this->odp_paye + (float) $this->tm_paye + (float) $this->db_paye,
            2
        );
    }

    public function getSoldeAttribute(): float
    {
        return round($this->total_theorique - $this->total_paye, 2);
    }
}
