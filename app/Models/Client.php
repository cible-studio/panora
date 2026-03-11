<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'sector', 'contact_name',
        'email', 'phone', 'address', 'user_id'
    ];

    // ── RELATIONS ──

    // Un client peut avoir un compte user
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
