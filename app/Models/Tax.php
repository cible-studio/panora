<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tax extends Model
{
    use HasFactory;

    protected $fillable = [
        'commune_id', 'year', 'type',
        'amount', 'due_date', 'paid_at', 'status',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
        'paid_at'  => 'date',
    ];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'payee';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'en_retard';
    }
}