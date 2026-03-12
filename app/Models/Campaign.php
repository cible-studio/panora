<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'client_id', 'reservation_id',
        'start_date', 'end_date', 'status',
        'total_panels', 'total_amount', 'notes',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_amount' => 'decimal:2',
        'total_panels' => 'integer',
        'status'       => CampaignStatus::class,
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'campaign_panels')
                    ->withTimestamps();
    }

    public function externalPanels()
    {
        return $this->belongsToMany(ExternalPanel::class, 'campaign_panels')
                    ->withTimestamps();
    }

    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::ACTIF->value);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', CampaignStatus::TERMINE->value);
    }

    public function isActive(): bool
    {
        return $this->status === CampaignStatus::ACTIF;
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