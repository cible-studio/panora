<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pige extends Model
{
    use HasFactory;

    protected $fillable = [
        'panel_id',
        'campaign_id',
        'user_id',
        'photo_path',
        'taken_at',
        'gps_lat',
        'gps_lng',
        'is_verified',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'verified_at' => 'datetime',
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
        'is_verified' => 'boolean',
    ];

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getPhotoUrlAttribute(): string
    {
        return asset('storage/' . $this->photo_path);
    }

    public function hasGps(): bool
    {
        return !is_null($this->gps_lat) && !is_null($this->gps_lng);
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    

}
