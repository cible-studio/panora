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
        'reference', 'name',
        'commune_id', 'zone_id',
        'format_id', 'category_id',
        'latitude', 'longitude',
        'status', 'is_lit',
        'monthly_rate', 'daily_traffic',
        'maintenance_status',
        'zone_description', 'created_by',
    ];

    protected $casts = [
        'is_lit'       => 'boolean',
        'monthly_rate' => 'decimal:2',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'status'       => PanelStatus::class,
    ];

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

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === PanelStatus::LIBRE;
    }
}
