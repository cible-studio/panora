<?php
namespace App\Models;

use App\Services\AlertService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Alert — notifications système ciblées.
 *
 * Le champ `type` est l'identifiant **fermé** du motif (cf. AlertService::TYPES
 * pour la liste des 20 motifs supportés). `niveau` reste libre (info/warning/danger).
 *
 * `dedup_key` permet d'éviter les doublons : si une alerte non lue avec la
 * même clé existe déjà, AlertService bumpe le `triggered_at` au lieu d'en
 * créer une nouvelle.
 */
class Alert extends Model
{
    protected $fillable = [
        'type', 'niveau', 'title', 'message',
        'related_type', 'related_id', 'dedup_key',
        'user_id', 'lien',
        'is_read', 'triggered_at', 'archived_at',
    ];

    protected $casts = [
        'is_read'      => 'boolean',
        'triggered_at' => 'datetime',
        'archived_at'  => 'datetime',
    ];

    // ── RELATIONS ──────────────────────────────────────────────

    public function related()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── SCOPES ─────────────────────────────────────────────────

    public function scopeUnread(Builder $q): Builder
    {
        return $q->where('is_read', false)->whereNull('archived_at');
    }

    public function scopeRead(Builder $q): Builder
    {
        return $q->where('is_read', true);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->whereNotNull('archived_at');
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeOfNiveau(Builder $q, string $niveau): Builder
    {
        return $q->where('niveau', $niveau);
    }

    // ── HELPERS PRÉSENTATION ───────────────────────────────────

    /**
     * Retourne la config UI (icon, color, label) pour ce type d'alerte.
     * Fallback générique si type inconnu.
     */
    public function getMetaAttribute(): array
    {
        return AlertService::TYPES[$this->type] ?? AlertService::DEFAULT_META;
    }

    public function markRead(): bool
    {
        if ($this->is_read) return false;
        return $this->update(['is_read' => true]);
    }

    public function archive(): bool
    {
        return $this->update(['archived_at' => now()]);
    }
}
