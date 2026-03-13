<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignPanel extends Model
{
    protected $fillable = [
        'campaign_id',
        'panel_id',
        'external_panel_id',
        'type'
    ];

    // ── RELATIONS ──

    // Appartient à une campagne
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // Appartient à un panneau interne
    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    // Appartient à un panneau externe
    public function externalPanel()
    {
        return $this->belongsTo(ExternalPanel::class, 'external_panel_id');
    }

    // ── SCOPES ──

    // Seulement les panneaux internes
    public function scopeInterne($query)
    {
        return $query->where('type', 'interne');
    }

    // Seulement les panneaux externes
    public function scopeExterne($query)
    {
        return $query->where('type', 'externe');
    }
}
