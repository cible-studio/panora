<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PanelFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'width', 'height', 'surface', 'print_type'
    ];

    public function panels()
    {
        return $this->hasMany(Panel::class, 'format_id');
    }
}