<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Proposition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'user_id',
        'reservation_id',
        'status',
        'notes',
        'sent_at',
        'confirmed_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    // ── Helpers ────────────────────────────────

    public function isConfirmed(): bool
    {
        return $this->status === 'confirme';
    }
}