<?php
namespace App\Models;

use App\Enums\PanelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Panel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'name',
        'commune_id', 'zone_id',
        'format_id', 'category_id',
        'latitude', 'longitude',
        'status', 'is_lit',
        'monthly_rate', 'daily_traffic',
        'maintenance_status',
        'zone_description', 'created_by'
    ];

    protected $casts = [
        'status'       => PanelStatus::class,
        'is_lit'       => 'boolean',
        'monthly_rate' => 'decimal:2',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
    ];

    // ── RELATIONS ──

    // Un panneau appartient à une commune
    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    // Un panneau appartient à une zone
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    // Un panneau a un format
    public function format()
    {
        return $this->belongsTo(PanelFormat::class, 'format_id');
    }

    // Un panneau a une catégorie
    public function category()
    {
        return $this->belongsTo(PanelCategory::class, 'category_id');
    }

    // Un panneau a été créé par un user
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Un panneau a plusieurs photos
    public function photos()
    {
        return $this->hasMany(PanelPhoto::class);
    }

    // Un panneau a plusieurs réservations
    public function reservations()
    {
        return $this->belongsToMany(
            Reservation::class,
            'reservation_panels'
        )->withPivot('unit_price', 'total_price')
         ->withTimestamps();
    }

    // Un panneau est dans plusieurs campagnes
    public function campaigns()
    {
        return $this->belongsToMany(
            Campaign::class,
            'campaign_panels'
        )->withTimestamps();
    }

    // Un panneau a plusieurs piges
    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    // Un panneau a plusieurs tâches de pose
    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class);
    }

    // Un panneau a plusieurs maintenances
    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    // ── SCOPES ──

    // Panneaux disponibles sur une période
    public function scopeAvailableBetween(
        Builder $query,
        string $start,
        string $end
    ): Builder {
        return $query->whereDoesntHave('reservations', function ($q)
            use ($start, $end) {
                $q->whereNotIn('status', ['refuse', 'annule'])
                  ->where('start_date', '<=', $end)
                  ->where('end_date', '>=', $start);
            })->where('status', '!=', 'maintenance');
    }

    // Panneaux par commune
    public function scopeByCommune(
        Builder $query,
        int $communeId
    ): Builder {
        return $query->where('commune_id', $communeId);
    }

    // Panneaux éclairés
    public function scopeLit(Builder $query): Builder
    {
        return $query->where('is_lit', true);
    }

    // Panneaux actifs (non supprimés)
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('status', '!=', 'maintenance');
    }
}
