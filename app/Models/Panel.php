<?php

namespace App\Models;

use App\Enums\PanelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Panel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'name',
        'commune_id',
        'zone_id',
        'format_id',
        'category_id',
        'latitude',
        'longitude',
        'gps_source',
        'gps_dispersion_flag',
        'gps_computed_at',
        'status',
        'is_lit',
        'is_vip',
        'monthly_rate',
        'daily_traffic',
        'maintenance_status',
        'zone_description',
        'created_by',

        // Champs Dev A
        'nombre_faces',

        // TX-10 (2026-09-23) — Départ du calcul ODP (cf. booted() plus bas)
        'odp_start_date',
        'type_support',
        'orientation',
        'adresse',
        'quartier',
        'axe_routier',
    ];

    protected $casts = [
        'is_lit'       => 'boolean',
        'is_vip'       => 'boolean',
        'monthly_rate' => 'decimal:2',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'gps_dispersion_flag' => 'boolean',
        'gps_computed_at'     => 'datetime',
        'status'       => PanelStatus::class,
        'odp_start_date'      => 'date',
    ];

    /**
     * TX-10 (2026-09-23) — Auto-remplissage de la date de départ ODP.
     *
     * Règle métier validée par la patronne :
     *   - Les panneaux déjà saisis dans Panora (odp_start_date = NULL) sont
     *     des panneaux qui existaient physiquement AVANT l'app. L'ODP leur
     *     est due sur toute la période demandée (cf. TaxCalculationService).
     *   - Tout panneau créé À PARTIR DE MAINTENANT est un nouveau panneau :
     *     son ODP ne court qu'à partir de sa date de création.
     *
     * ⚠ Si un jour on importe un lot de panneaux HISTORIQUES, il faut
     *   passer odp_start_date explicitement (ou la remettre à null après
     *   import), sinon ils hériteront de la date du jour.
     */
    protected static function booted(): void
    {
        static::creating(function (self $panel) {
            if (empty($panel->odp_start_date)) {
                $panel->odp_start_date = now()->toDateString();
            }
        });
    }

    // ───────────── Relations principales ─────────────

    public function photos()
    {
        return $this->hasMany(PanelPhoto::class);
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

    public function category()
    {
        return $this->belongsTo(PanelCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Maintenance actuellement ouverte (signale OU en_cours) la plus récente.
     * Permet aux vues de récupérer l'état actuel d'un panneau en un eager-load
     * — pas de N+1 ni de calcul applicatif sur chaque ligne.
     */
    public function activeMaintenance()
    {
        return $this->hasOne(Maintenance::class)
            ->whereIn('statut', Maintenance::STATUTS_OUVERTS)
            ->latestOfMany('date_signalement');
    }

    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class);
    }

    // ───────────── Relations commerciales ─────────────

    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'reservation_panels')
            ->withPivot(['unit_price', 'total_price'])
            ->withTimestamps();
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_panels')
            ->withTimestamps();
    }

    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    // ───────────── Helpers ─────────────

    public function isAvailable(): bool
    {
        return $this->status === PanelStatus::LIBRE;
    }
}
