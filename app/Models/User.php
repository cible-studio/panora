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
        'whatsapp_number', 'tech_public_token',
        'pose_team_id', // M2 — équipe de pose (techniciens)
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
     * Préfixe code agent par rôle métier (Lot 10.1).
     * Convention CIBLE CI :
     *   commercial   → SC  (Sales Commercial)
     *   technique    → TT  (Technicien Terrain)
     *   mediaplanner → MP  (Media Planner)
     *   admin        → AD  (Administrateur)
     */
    public const AGENT_CODE_PREFIXES = [
        'commercial'   => 'SC',
        'technique'    => 'TT',
        'mediaplanner' => 'MP',
        'admin'        => 'AD',
    ];

    /**
     * Génère un code agent unique au format "{PREFIXE}-{SEQ}" selon le
     * rôle (ex: SC-001, TT-014). Séquentiel global par préfixe (pas par
     * année) pour rester court et lisible — un même collaborateur garde
     * son code à vie.
     *
     * Si un rôle inconnu est fourni, retourne null (l'admin saisit
     * manuellement le code dans ce cas).
     *
     * Idempotent côté collisions : 50 essais avant fallback timestamp.
     */
    public static function generateAgentCode(?string $role = null): ?string
    {
        $prefix = self::AGENT_CODE_PREFIXES[$role] ?? null;
        if (!$prefix) return null;

        // Compte les agents existants avec ce préfixe (ignore casse pour
        // tolérer les codes saisis manuellement avec une autre forme).
        $base = static::where('agent_code', 'LIKE', $prefix . '-%')->count() + 1;

        for ($i = 0; $i < 50; $i++) {
            $candidate = sprintf('%s-%03d', $prefix, $base + $i);
            if (!static::where('agent_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fallback : timestamp court pour garantir l'unicité.
        return sprintf('%s-%s', $prefix, substr((string) (microtime(true) * 1000), -5));
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

    // ── M1 Performance Commerciale (mission 2026-06-17) ──
    /** Scope : tous les users avec role='commercial' actifs. */
    public function scopeCommerciaux($query)
    {
        return $query->where('role', UserRole::COMMERCIAL->value)
                     ->where('is_active', true)
                     ->orderBy('name');
    }

    /** Campagnes dont ce user est le commercial responsable. */
    public function commercialCampaigns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Campaign::class, 'commercial_user_id');
    }

    /** Factures dont ce user est le commercial responsable. */
    public function commercialInvoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Invoice::class, 'commercial_user_id');
    }

    // ── M2 Performance Technicien / Équipe (mission 2026-06-17) ──
    /** Scope : tous les users avec role='technique' actifs. */
    public function scopeTechniciens($query)
    {
        return $query->where('role', UserRole::TECHNIQUE->value)
                     ->where('is_active', true)
                     ->orderBy('name');
    }

    /** Équipe de pose à laquelle le tech est rattaché (nullable). */
    public function poseTeam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\PoseTeam::class, 'pose_team_id');
    }

    /** Équipe dont ce user est leader (HasOne — uniq côté BDD). */
    public function teamLeading(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\PoseTeam::class, 'leader_user_id');
    }

    // ══════════════════════════════════════════════════════════════
    // ESPACE TECHNICIEN — token public permanent
    // ══════════════════════════════════════════════════════════════

    /**
     * Génère (idempotent) le token public permanent du technicien.
     * Utilisé pour construire l'URL de son espace personnel :
     *   /tech/{token}/poses
     *
     * Le token est généré à la PREMIÈRE assignation d'une pose
     * (cf. PoseService::notifyTechnicianBatch / notifyTechnicianOnWhatsApp),
     * pas à la création du compte — évite des slots inutiles si le user
     * n'est jamais techncien terrain.
     */
    public function ensureTechPublicToken(): string
    {
        if (empty($this->tech_public_token)) {
            $this->update([
                'tech_public_token' => \Illuminate\Support\Str::random(32),
            ]);
            $this->refresh();
        }
        return $this->tech_public_token;
    }

    /**
     * URL publique de l'espace technicien. Génère le token si nécessaire.
     */
    public function techPublicUrl(): string
    {
        return route('tech.space', $this->ensureTechPublicToken());
    }
}