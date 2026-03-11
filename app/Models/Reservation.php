<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'client_id',
        'user_id',
        'start_date',
        'end_date',
        'status',
        'total_amount',
        'notes',
        'confirmed_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'confirmed_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    // Statuts qui bloquent un panneau
    const BLOCKING_STATUSES = ['en_attente', 'confirme'];

    // Statuts qui libèrent un panneau
    const FREE_STATUSES = ['refuse', 'annule'];

    // ── Relations ──────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Panneaux liés via la table pivot
    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'reservation_panels')
                    ->withPivot(['unit_price', 'total_price'])
                    ->withTimestamps();
    }

    // Une réservation confirmée génère une campagne
    public function campaign()
    {
        return $this->hasOne(Campaign::class);
    }

    // ── Scopes ─────────────────────────────────

    // Réservations actives (qui bloquent les panneaux)
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    // Réservations en attente de confirmation
    public function scopePending($query)
    {
        return $query->where('status', 'en_attente');
    }

    // Réservations confirmées
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirme');
    }

    // ── Helpers ────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, self::BLOCKING_STATUSES);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirme';
    }
}