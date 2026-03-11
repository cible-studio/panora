<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelFormat extends Model
{
    protected $fillable = [
        'name', 'width', 'height',
        'surface', 'print_type'
    ];

    // ── RELATIONS ──

    // Un format a plusieurs panneaux
    public function panels()
    {
        return $this->hasMany(Panel::class, 'format_id');
    }
}
