<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * PoseTeam — équipe de techniciens terrain (M2 Performance).
 *
 * Décisions :
 *   - SoftDeletes : suppression détache les membres mais préserve l'historique.
 *   - Couleur stockée en SLUG (red, blue, indigo…) — résolue en hex
 *     via COLOR_PALETTE pour découpler la palette des données.
 *   - leader_user_id UNIQUE en BDD (Garde-fou B M2) — 1 user = leader
 *     d'une équipe maximum. Validation amont côté controller pour message
 *     d'erreur explicite avant que la BDD ne lève l'erreur d'unicité.
 */
class PoseTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'color_slug', 'leader_user_id',
        'is_active', 'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Palette de 8 couleurs prédéfinies (Q1 brief M2).
     * Le SLUG est stocké ; le HEX est résolu à l'affichage.
     * Si tu changes ces valeurs, toutes les équipes suivent automatiquement.
     */
    public const COLOR_PALETTE = [
        'red'    => '#dc2626',
        'orange' => '#ea580c',
        'amber'  => '#f59e0b',
        'green'  => '#16a34a',
        'blue'   => '#0ea5e9',
        'indigo' => '#6366f1',
        'purple' => '#a855f7',
        'pink'   => '#ec4899',
    ];

    /** Résout le hex depuis le slug. Fallback sur 'indigo' si slug inconnu. */
    public function colorHex(): string
    {
        return self::COLOR_PALETTE[$this->color_slug]
            ?? self::COLOR_PALETTE['indigo'];
    }

    /** Hex pâle (transparence) pour fonds badge. */
    public function colorBgHex(): string
    {
        $hex = $this->colorHex();
        // Convertit en rgba(.10)
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return sprintf('rgba(%d,%d,%d,0.10)', $r, $g, $b);
    }

    /** Auto-slug à la création si non fourni. */
    protected static function booted(): void
    {
        static::creating(function (PoseTeam $team) {
            if (empty($team->slug)) {
                $team->slug = self::generateUniqueSlug($team->name);
            }
        });
    }

    /** Génère un slug unique en cas de collision. */
    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;
        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // ── Scopes ──
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    // ── Relations ──
    /** Techniciens rattachés à cette équipe. */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'pose_team_id');
    }

    /** Membres actifs uniquement. */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    /** Chef d'équipe (User). */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }
}
