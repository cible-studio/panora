<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pige extends Model
{
    protected $fillable = [
        'panel_id', 'campaign_id', 'user_id',
        'photo_path', 'taken_at',
        'gps_lat', 'gps_lng',
        'is_verified', 'verified_by',
        'verified_at', 'notes'
    ];

    protected $casts = [
        'taken_at'    => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'gps_lat'     => 'decimal:7',
        'gps_lng'     => 'decimal:7',
    ];

    // ── RELATIONS ──

    // Une pige concerne un panneau
    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    // Une pige est liée à une campagne
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // Une pige est prise par un user
    public function takenBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Une pige est vérifiée par un user
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
