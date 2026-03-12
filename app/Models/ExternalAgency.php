<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExternalAgency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact', 'email', 'address',
    ];

    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class, 'agency_id');
    }
}