<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'client_id',
        'reservation_id',
        'start_date',
        'end_date',
        'status',
        'total_panels',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_amount' => 'decimal:2',
        'total_panels' => 'integer',
    ];

    // ── Relations ──────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    // Panneaux internes liés à la campagne
    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'campaign_panels')
                    ->withTimestamps();
    }

    // Panneaux externes liés à la campagne
    public function externalPanels()
    {
        return $this->belongsToMany(ExternalPanel::class, 'campaign_panels')
                    ->withTimestamps();
    }

    // Piges photos de la campagne
    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    // Factures de la campagne
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Scopes ─────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'actif');
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'termine');
    }

    // ── Helpers ────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'actif';
    }

    public function durationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    public function durationInMonths(): int
    {
        return $this->start_date->diffInMonths($this->end_date);
    }
}