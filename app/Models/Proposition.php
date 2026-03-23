<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Client;
use App\Models\User;

class Proposition extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'created_by', 'numero',
        'nb_panneaux', 'date_debut', 'date_fin',
        'montant', 'statut', 'notes',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'montant'    => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAccepted(): bool
    {
        return $this->statut === 'acceptee';
    }

    public function creator()
{
    return $this->belongsTo(User::class, 'created_by');
}
}
