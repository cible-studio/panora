<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PanelCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description'
    ];

    public function panels()
    {
        return $this->hasMany(Panel::class, 'category_id');
    }
}