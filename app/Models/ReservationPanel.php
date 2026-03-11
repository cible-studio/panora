<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPanel extends Model
{
    protected $fillable = [
        'reservation_id', 'panel_id',
        'unit_price', 'total_price'
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // ── RELATIONS ──

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }
}
