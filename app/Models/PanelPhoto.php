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
        return $this->belongsTo(Panel::class);
    }
}
