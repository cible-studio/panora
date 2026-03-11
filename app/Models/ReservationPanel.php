<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReservationPanel extends Model
{
    use HasFactory;

    protected $table = 'reservation_panels';

    protected $fillable = [
        'reservation_id',
        'panel_id',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // ── Relations ──────────────────────────────

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }
}