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
        'notes',
    ];

    protected $casts = [
        'taken_at'    => 'datetime',
        'gps_lat'     => 'decimal:7',
        'gps_lng'     => 'decimal:7',
        'is_verified' => 'boolean',
    ];

    // ── Relations ──────────────────────────────

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

    // ── Scopes ─────────────────────────────────

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_verified', false);
    }

    // ── Helpers ────────────────────────────────

    public function getPhotoUrlAttribute(): string
    {
        return asset('storage/' . $this->photo_path);
    }

    public function hasGps(): bool
    {
        return !is_null($this->gps_lat) && !is_null($this->gps_lng);
    }
}