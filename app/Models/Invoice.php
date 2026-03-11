<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'client_id',
        'campaign_id',
        'amount',
        'issued_at',
        'paid_at',
        'status',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'issued_at' => 'date',
        'paid_at'   => 'date',
    ];

    // ── Relations ──────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // ── Scopes ─────────────────────────────────

    public function scopePaid($query)
    {
        return $query->where('status', 'paye');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'en_attente');
    }

    // ── Helpers ────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paye';
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid() && $this->issued_at->isPast();
    }
}