<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'client_id', 'user_id',
        'start_date', 'end_date', 'status',
        'total_amount', 'notes', 'confirmed_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'confirmed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'status'       => ReservationStatus::class,
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'reservation_panels')
                    ->withPivot(['unit_price', 'total_price'])
                    ->withTimestamps();
    }

    public function campaign()
    {
        return $this->hasOne(Campaign::class);
    }

    public function proposition()
    {
        return $this->hasOne(Proposition::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            ReservationStatus::EN_ATTENTE->value,
            ReservationStatus::CONFIRME->value,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', ReservationStatus::EN_ATTENTE->value);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', ReservationStatus::CONFIRME->value);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            ReservationStatus::EN_ATTENTE,
            ReservationStatus::CONFIRME,
        ]);
    }

    public function isConfirmed(): bool
    {
        return $this->status === ReservationStatus::CONFIRME;
    }
}