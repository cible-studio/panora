<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelCategory extends Model
{
    protected $fillable = ['name', 'description'];

    // ── RELATIONS ──

    // Une catégorie a plusieurs panneaux
    public function panels()
    {
        return $this->hasMany(Panel::class, 'category_id');
    }
}
