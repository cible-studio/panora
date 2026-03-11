<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalAgency extends Model
{
    protected $fillable = [
        'name', 'contact', 'email', 'address'
    ];

    // ── RELATIONS ──

    // Une régie a plusieurs panneaux externes
    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class, 'agency_id');
    }
}
