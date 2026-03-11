<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalPanel extends Model
{
    protected $fillable = [
        'agency_id', 'commune_id',
        'code_panneau', 'designation', 'type'
    ];

    // ── RELATIONS ──

    // Un panneau externe appartient à une régie
    public function agency()
    {
        return $this->belongsTo(ExternalAgency::class, 'agency_id');
    }

    // Un panneau externe est dans une commune
    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    // Un panneau externe est dans plusieurs campagnes
    public function campaigns()
    {
        return $this->belongsToMany(
            Campaign::class,
            'campaign_panels',
            'external_panel_id',
            'campaign_id'
        )->wherePivot('type', 'externe');
    }
}
