<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Interlocuteur d'un compte client. Un client peut en avoir plusieurs ;
 * un seul est marqué `is_primary` à la fois (le contact par défaut pour
 * les communications commerciales / propositions).
 */
class ClientContact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id', 'name', 'email', 'phone',
        'role', 'position', 'is_primary',
        'receives_notifications', 'notes',
    ];

    protected $casts = [
        'is_primary'             => 'boolean',
        'receives_notifications' => 'boolean',
    ];

    /**
     * Liste blanche des rôles d'interlocuteur — sert à valider l'input
     * côté controller et à libellé côté UI (label avec emoji).
     */
    public const ROLES = [
        'decideur'  => '🎯 Décideur',
        'commercial'=> '🤝 Commercial / Achats',
        'comptable' => '💼 Comptabilité / Facturation',
        'creation'  => '🎨 Création / Design',
        'technique' => '🔧 Technique / Pose',
        'comms'     => '📢 Communication / Marketing',
        'autre'     => '👤 Autre',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * Bascule le drapeau is_primary sur un seul contact à la fois pour
     * un même client. Wrappé en transaction pour éviter qu'une erreur
     * laisse 0 ou 2+ contacts marqués primary.
     */
    public function makePrimary(): void
    {
        DB::transaction(function () {
            static::where('client_id', $this->client_id)
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false]);
            $this->update(['is_primary' => true]);
        });
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? self::ROLES['autre'];
    }
}
