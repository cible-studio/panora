<?php
// app/Models/Pige.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Pige extends Model
{
    protected $fillable = [
        'panel_id', 'campaign_id', 'user_id', 'verified_by',
        'photo_path', 'photo_thumb',
        'gps_lat', 'gps_lng',
        'taken_at', 'verified_at',
        'status', 'rejection_reason', 'notes',
    ];

    protected $casts = [
        'taken_at'    => 'datetime',
        'verified_at' => 'datetime',
        'gps_lat'     => 'float',
        'gps_lng'     => 'float',
    ];

    // ══════════════════════════════════════════════════════════════
    // RELATIONS
    // ══════════════════════════════════════════════════════════════

    public function panel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function technicien(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verificateur(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ══════════════════════════════════════════════════════════════
    // SCOPES
    // ══════════════════════════════════════════════════════════════

    public function scopeEnAttente(Builder $q): Builder
    {
        return $q->where('status', 'en_attente');
    }

    public function scopeVerifiees(Builder $q): Builder
    {
        return $q->where('status', 'verifie');
    }

    public function scopeRejetees(Builder $q): Builder
    {
        return $q->where('status', 'rejete');
    }

    public function scopeForCampaign(Builder $q, int $campaignId): Builder
    {
        return $q->where('campaign_id', $campaignId);
    }

    public function scopeForPanel(Builder $q, int $panelId): Builder
    {
        return $q->where('panel_id', $panelId);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    public function isEnAttente(): bool { return $this->status === 'en_attente'; }
    public function isVerifiee(): bool  { return $this->status === 'verifie'; }
    public function isRejetee(): bool   { return $this->status === 'rejete'; }
    public function isTerminal(): bool  { return in_array($this->status, ['verifie', 'rejete']); }

    public function hasGps(): bool
    {
        return !is_null($this->gps_lat) && !is_null($this->gps_lng);
    }

    public function getPhotoUrl(): string
    {
        return Storage::url($this->photo_path);
    }

    public function getThumbUrl(): string
    {
        if ($this->photo_thumb && Storage::exists($this->photo_thumb)) {
            return Storage::url($this->photo_thumb);
        }
        return $this->getPhotoUrl();
    }

    public function getGoogleMapsUrl(): ?string
    {
        if (!$this->hasGps()) return null;
        return "https://maps.google.com/?q={$this->gps_lat},{$this->gps_lng}";
    }

    public function getStatusConfig(): array
    {
        return match($this->status) {
            'en_attente' => ['label'=>'En attente', 'color'=>'#f97316', 'bg'=>'rgba(249,115,22,.1)', 'bd'=>'rgba(249,115,22,.3)'],
            'verifie'    => ['label'=>'Vérifiée',   'color'=>'#22c55e', 'bg'=>'rgba(34,197,94,.1)',  'bd'=>'rgba(34,197,94,.3)'],
            'rejete'     => ['label'=>'Rejetée',    'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,.1)',  'bd'=>'rgba(239,68,68,.3)'],
            default      => ['label'=>$this->status, 'color'=>'#6b7280','bg'=>'rgba(107,114,128,.1)','bd'=>'rgba(107,114,128,.3)'],
        };
    }
}