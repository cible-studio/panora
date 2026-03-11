<?php
namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'client_id', 'user_id',
        'start_date', 'end_date', 'status',
        'total_amount', 'notes', 'confirmed_at'
    ];

    protected $casts = [
        'status'       => ReservationStatus::class,
        'start_date'   => 'date',
        'end_date'     => 'date',
        'confirmed_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    // ── RELATIONS ──

    // Une réservation appartient à un client
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // Une réservation est faite par un agent
    public function agent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Une réservation a plusieurs panneaux
    public function panels()
    {
        return $this->belongsToMany(
            Panel::class,
            'reservation_panels'
        )->withPivot('unit_price', 'total_price')
         ->withTimestamps();
    }

    // Une réservation génère une campagne
    public function campaign()
    {
        return $this->hasOne(Campaign::class);
    }

    // ── SCOPES ──

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['refuse', 'annule']);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('status', 'en_attente');
    }

    public function scopeConfirme($query)
    {
        return $query->where('status', 'confirme');
    }
}
