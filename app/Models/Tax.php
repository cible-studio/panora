<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tax extends Model
{
    use HasFactory;

    protected $fillable = [
        'commune_id',
        'year',
        'type',
        'amount',
        'due_date',
        'paid_at',
        'status',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'paid_at'  => 'date',
    ];

    // ── Relations ──────────────────────────────

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    // ── Scopes ─────────────────────────────────

    public function scopeOdp($query)
    {
        return $query->where('type', 'odp');
    }

    public function scopeTm($query)
    {
        return $query->where('type', 'tm');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'en_attente')
                     ->where('due_date', '<', now());
    }

    // ── Helpers ────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paye';
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid() && $this->due_date->isPast();
    }
}