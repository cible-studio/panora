<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = [
        'commune_id', 'year', 'type',
        'amount', 'due_date',
        'paid_at', 'status'
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'paid_at'  => 'date',
    ];

    // ── RELATIONS ──

    // Une taxe appartient à une commune
    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }
}
