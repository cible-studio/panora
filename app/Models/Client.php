<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sector',
        'contact_name',
        'email',
        'phone',
        'address',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────

    // Un client peut avoir un compte utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Un client a plusieurs réservations
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    // Un client a plusieurs campagnes
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    // Un client a plusieurs factures
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Un client a plusieurs propositions
    public function propositions()
    {
        return $this->hasMany(Proposition::class);
    }
}