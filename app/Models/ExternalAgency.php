<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExternalAgency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
    ];

    // ── Relations ──────────────────────────────

    // Une régie a plusieurs panneaux externes
    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class, 'agency_id');
    }
}