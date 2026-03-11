<?php
namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'agent_code', 'is_active',
        'two_fa_enabled', 'last_login_at'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'role'            => UserRole::class,
        'is_active'       => 'boolean',
        'two_fa_enabled'  => 'boolean',
        'last_login_at'   => 'datetime',
    ];

    // ── RELATIONS ──

    // Un user a créé plusieurs panneaux
    public function panelsCreated()
    {
        return $this->hasMany(Panel::class, 'created_by');
    }

    // Un user a plusieurs réservations
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    // Un user a plusieurs piges prises
    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    // Un user a plusieurs tâches de pose
    public function poseTasks()
    {
        return $this->hasMany(PoseTask::class, 'assigned_user_id');
    }

    // Un user a plusieurs logs d'audit
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── HELPERS RÔLES ──

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
