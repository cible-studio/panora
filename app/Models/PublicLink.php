<?php

namespace App\Models;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * PublicLink — Lien public sécurisé envoyé par email aux clients.
 *
 * Cas d'usage :
 *   - Facture à consulter sans compte
 *   - Pige photo validée
 *   - Confirmation de réservation
 *   - Récap décapage
 *
 * Sécurité : token 256 bits + expiration + révocation + audit + throttle.
 * Voir migration 2026_05_21_120000_create_public_links_table.
 */
class PublicLink extends Model
{
    protected $fillable = [
        'token', 'type', 'target_type', 'target_id', 'client_id',
        'expires_at', 'revoked_at', 'revoked_by_user_id', 'revoked_reason',
        'max_uses', 'use_count', 'used_at',
        'access_count', 'last_accessed_at', 'last_accessed_ip', 'last_accessed_ua',
        'created_by_user_id', 'metadata',
    ];

    protected $casts = [
        'expires_at'       => 'datetime',
        'revoked_at'       => 'datetime',
        'used_at'          => 'datetime',
        'last_accessed_at' => 'datetime',
        'metadata'         => 'array',
        'max_uses'         => 'integer',
        'use_count'        => 'integer',
        'access_count'     => 'integer',
    ];

    // ── RELATIONS ──────────────────────────────────────────────

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    // ── ÉTAT ───────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->use_count >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return !$this->isExpired() && !$this->isRevoked() && !$this->isExhausted();
    }

    public function statusReason(): ?string
    {
        if ($this->isRevoked())  return 'Ce lien a été révoqué' . ($this->revoked_reason ? ' : ' . $this->revoked_reason : '.');
        if ($this->isExpired())  return 'Ce lien a expiré le ' . $this->expires_at->format('d/m/Y') . '.';
        if ($this->isExhausted())return 'Ce lien a déjà été utilisé.';
        return null;
    }

    // ── URL ────────────────────────────────────────────────────

    public function publicUrl(): string
    {
        return url('/p/' . $this->token);
    }

    // ── SCOPES ─────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
