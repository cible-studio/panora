<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'city', 'region', 'odp_rate', 'tm_rate'
    ];

    protected $casts = [
        'odp_rate' => 'decimal:2',
        'tm_rate'  => 'decimal:2',
    ];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function panels()
    {
        return $this->hasMany(Panel::class);
    }

    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }

    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class);
    }
}