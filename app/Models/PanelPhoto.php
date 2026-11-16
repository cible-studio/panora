<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelPhoto extends Model
{
    protected $fillable = ['panel_id', 'path', 'ordre'];

    // ── RELATIONS ──

    // Une photo appartient à un panneau
    public function panel()
    {
        // withTrashed() (2026-11-16) : un panneau démantelé est soft-deleted. Sans
        // cela la relation renvoie null, et toute vue qui construit
        // route('admin.panels.show', $x->panel) lève une UrlGenerationException
        // (500 sur /admin/piges). Un enregistrement d'historique doit rester
        // lisible même si le panneau a quitté le parc.
        // Même traitement que Maintenance::panel() depuis le 2026-10-08.
        return $this->belongsTo(Panel::class)->withTrashed();
    }
}
