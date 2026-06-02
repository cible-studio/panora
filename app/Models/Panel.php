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
    ];

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
