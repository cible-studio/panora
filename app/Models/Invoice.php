<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'reference', 'client_id', 'campaign_id',
        'created_by', 'amount', 'tva',
        'amount_ttc', 'issued_at',
        'paid_at', 'status'
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'tva'        => 'decimal:2',
        'amount_ttc' => 'decimal:2',
        'issued_at'  => 'date',
        'paid_at'    => 'date',
    ];

    // ── RELATIONS ──

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
