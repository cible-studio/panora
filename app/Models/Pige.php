<?php
// app/Models/Pige.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Pige extends Model
{
    use HasFactory;

    // ── Statuts possibles (pas d'Enum PHP pour rester compatible Laravel 10/11)
    public const STATUS_PENDING  = 'en_attente';
    public const STATUS_VERIFIED = 'verifie';
    public const STATUS_REJECTED = 'rejete';

    public const STATUSES = [
        self::STATUS_PENDING  => 'En attente',
        self::STATUS_VERIFIED => 'Vérifié',
        self::STATUS_REJECTED => 'Rejeté',
    ];

    protected $fillable = [
        'panel_id',
        'campaign_id',
        'user_id',       // technicien qui a pris la photo
        'photo_path',
        'taken_at',
        'gps_lat',
        'gps_lng',
        'status',        // en_attente | verifie | rejete
        'verified_by',
        'verified_at',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'taken_at'    => 'datetime',
        'verified_at' => 'datetime',
        'gps_lat'     => 'decimal:7',
        'gps_lng'     => 'decimal:7',
    ];

    // ── RELATIONS ──────────────────────────────────────────────────

    public function panel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // Technicien = user qui a uploadé
    public function takenBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Validateur = user qui a vérifié/rejeté
    public function verifiedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ── SCOPES ─────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_VERIFIED);
    }

    public function scopeRejected(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_REJECTED);
    }

    public function scopeForCampaign(Builder $q, int $campaignId): Builder
    {
        return $q->where('campaign_id', $campaignId);
    }

    public function scopeForPanel(Builder $q, int $panelId): Builder
    {
        return $q->where('panel_id', $panelId);
    }

    public function scopeForClient(Builder $q, int $clientId): Builder
    {
        return $q->whereHas('campaign', fn($c) => $c->where('client_id', $clientId));
    }

    // Filtre période (date de prise de vue)
    public function scopeInPeriod(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('taken_at', [$from, $to . ' 23:59:59']);
    }

    // ── ACCESSEURS ─────────────────────────────────────────────────

    // URL publique de la photo
    public function getPhotoUrlAttribute(): string
    {
        return asset('storage/' . $this->photo_path);
    }

    // Label lisible du statut
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    // Classe CSS Tailwind / variable CSS selon statut
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_VERIFIED => 'green',
            self::STATUS_REJECTED => 'red',
            default               => 'orange',
        };
    }

    // ── HELPERS ────────────────────────────────────────────────────

    public function hasGps(): bool
    {
        return $this->gps_lat !== null && $this->gps_lng !== null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    // Lien Google Maps si GPS disponible
    public function getMapsUrlAttribute(): ?string
    {
        if (!$this->hasGps()) return null;
        return "https://maps.google.com/?q={$this->gps_lat},{$this->gps_lng}";
    }
}