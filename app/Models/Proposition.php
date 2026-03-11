<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposition extends Model
{
    protected $fillable = [
        'client_id', 'created_by', 'numero',
        'nb_panneaux', 'date_debut', 'date_fin',
        'montant', 'statut', 'notes'
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'montant'    => 'decimal:2',
    ];

    // ── RELATIONS ──

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
