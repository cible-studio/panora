<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'agent_code', 'is_active',
        'two_fa_enabled', 'last_login_at',
        'reservations_last_seen_at',
        'whatsapp_number',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'role'           => UserRole::class,
        'is_active'      => 'boolean',
        'two_fa_enabled' => 'boolean',
        'last_login_at'  => 'datetime',
        'reservations_last_seen_at' => 'datetime',
    ];

    /**
     * Génère un code agent unique au format "AGT-{YEAR}-{SEQ}".
     * Pris à la création d'un utilisateur quand l'admin n'en saisit pas
     * (le champ reste libre pour les codes hérités d'un autre système).
     *
     * Le compteur SEQ est calé sur le nombre d'utilisateurs créés cette
     * année + une marge à la collision (max 50 essais avant fallback
     * timestamp pour rester strictement unique).
     */
    public static function generateAgentCode(): string
    {
        $year = (int) date('Y');
        $base = static::whereYear('created_at', $year)->count() + 1;

        for ($i = 0; $i < 50; $i++) {
            $candidate = sprintf('AGT-%d-%03d', $year, $base + $i);
            if (!static::where('agent_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fallback peu probable : tous les slots de l'année occupés.
        // Suffixe avec timestamp court pour garantir l'unicité.
        return sprintf('AGT-%d-%s', $year, substr((string) microtime(true) * 1000, -5));
    }

    public function panelsCreated()
    {
        return $this->hasMany(Panel::class, 'created_by');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class, 'assigned_user_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isCommercial(): bool
    {
        return $this->role === UserRole::COMMERCIAL;
    }

    public function isMediaPlanner(): bool
    {
        return $this->role === UserRole::MEDIAPLANNER;
    }

    public function isTechnique(): bool
    {
        return $this->role === UserRole::TECHNIQUE;
    }
}