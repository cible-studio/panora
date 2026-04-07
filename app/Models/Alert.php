<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'type', 'niveau', 'title', 'message',
        'related_type', 'related_id',
        'user_id', 'lien',
        'is_read', 'triggered_at'
    ];

    protected $casts = [
        'is_read'      => 'boolean',
        'triggered_at' => 'datetime',
    ];

    // ── RELATION POLYMORPHIQUE ──
    // Une alerte peut concerner n'importe quel model
    public function related()
    {
        return $this->morphTo();
    }
}
