<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $fillable = [
        'commune_id', 'name',
        'description', 'demand_level'
    ];

    // ── RELATIONS ──

    // Une zone appartient à une commune
    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    // Une zone a plusieurs panneaux
    public function panels()
    {
        return $this->hasMany(Panel::class);
    }
}
