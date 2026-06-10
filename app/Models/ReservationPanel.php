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

    // RÈGLE 4 — entier FCFA (audit Phase 8E : ce pivot alimente
    // InvoiceLine.pu_ht_mensuel qui est BIGINT depuis Phase 1).
    protected $casts = [
        'unit_price'  => 'integer',
        'total_price' => 'integer',
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