<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExternalPanel extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id', 'commune_id', 'code_panneau',
        'designation', 'type',
    ];

    public function agency()
    {
        return $this->belongsTo(ExternalAgency::class, 'agency_id');
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_panels')
                    ->withTimestamps();
    }
}