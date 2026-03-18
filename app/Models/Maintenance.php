<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Maintenance extends Model
{
    use HasFactory;

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

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    public function technicien()
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }

    public function signaledBy()
    {
        return $this->belongsTo(User::class, 'signale_par');
    }
}
