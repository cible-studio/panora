<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExternalPanel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id', 'commune_id', 'zone_id', 'format_id',
        'code_panneau', 'designation', 'type',
        'is_lit', 'daily_traffic', 'monthly_rate', 'zone_description',
        'availability_status', 'available_from', 'available_until', 'notes',
    ];

    protected $casts = [
        'is_lit'         => 'boolean',
        'monthly_rate'   => 'decimal:2',
        'available_from' => 'date',
        'available_until'=> 'date',
    ];

    // ── Relations — TOUTES conservées + nouvelles ─────────────────
    public function agency()
    {
        return $this->belongsTo(ExternalAgency::class, 'agency_id');
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function format()
    {
        return $this->belongsTo(PanelFormat::class, 'format_id');
    }

    // ── Relation campaigns — CONSERVÉE telle quelle ───────────────
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_panels')
                    ->withTimestamps();
    }

    // ── Helper : statut calculé sur une période ───────────────────
    public function getDisplayStatusForPeriod(
        ?string $startDate,
        ?string $endDate
    ): string {
        if ($this->availability_status === 'a_verifier') {
            return 'a_verifier';
        }

        if (!$startDate || !$endDate) {
            return $this->availability_status;
        }

        // Si période fournie, vérifier les dates de dispo saisies
        if ($this->available_from && $this->available_until) {
            $from  = $this->available_from->format('Y-m-d');
            $until = $this->available_until->format('Y-m-d');
            // Panneau dispo si sa période de dispo chevauche la période demandée
            if ($from <= $endDate && $until >= $startDate) {
                return 'disponible';
            }
            return 'occupe';
        }

        return $this->availability_status;
    }
}