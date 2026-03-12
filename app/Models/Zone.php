<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'commune_id', 'name', 'description', 'demand_level'
    ];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function panels()
    {
        return $this->hasMany(Panel::class);
    }
}