<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    protected $fillable = [
        'panel_id', 'technicien_id', 'signale_par',
        'type_panne', 'priorite', 'statut',
        'date_signalement', 'date_resolution',
        'description', 'solution'
    ];

    protected $casts = [
        'date_signalement' => 'date',
        'date_resolution'  => 'date',
    ];

    // ── RELATIONS ──

    // Une maintenance concerne un panneau
    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    // Une maintenance est assignée à un technicien
    public function technicien()
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }

    // Une maintenance est signalée par un user
    public function signaledBy()
    {
        return $this->belongsTo(User::class, 'signale_par');
    }
}
