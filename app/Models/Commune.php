<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    protected $fillable = [
        'name', 'city', 'region',
        'odp_rate', 'tm_rate'
    ];

    protected $casts = [
        'odp_rate' => 'decimal:2',
        'tm_rate'  => 'decimal:2',
    ];

    // ── RELATIONS ──

    // Une commune a plusieurs zones
    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    // Une commune a plusieurs panneaux
    public function panels()
    {
        return $this->hasMany(Panel::class);
    }

    // Une commune a plusieurs taxes
    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }

    // Une commune a plusieurs panneaux externes
    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class);
    }
}
