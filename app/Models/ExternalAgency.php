<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExternalAgency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'contact', 'phone', 'email',
        'address', 'city', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class, 'agency_id');
    }

    public function activePanels()
    {
        return $this->hasMany(ExternalPanel::class, 'agency_id')
                    ->whereNull('deleted_at');
    }
}