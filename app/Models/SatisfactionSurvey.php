<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    protected $fillable = [
        'campaign_id', 'client_id', 'token',
        'sent_at', 'completed_at',
        'score_global', 'score_qualite', 'score_delais',
        'score_communication', 'score_rapport_qualite_prix',
        'would_renew', 'commentaire', 'completed_ip',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'completed_at' => 'datetime',
        'would_renew'  => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────
    public function campaign() { return $this->belongsTo(Campaign::class); }
    public function client()   { return $this->belongsTo(Client::class); }

    // ── Helpers ──────────────────────────────────────────────────

    /** Génère un token unique 64 chars */
    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());
        return $token;
    }

    /** URL publique du formulaire (pour le client) */
    public function publicUrl(): string
    {
        return route('satisfaction.show', $this->token);
    }

    /** True si déjà complété */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /** Score moyen calculé depuis les 5 critères (null si rien) */
    public function averageScore(): ?float
    {
        $scores = array_filter([
            $this->score_global,
            $this->score_qualite,
            $this->score_delais,
            $this->score_communication,
            $this->score_rapport_qualite_prix,
        ]);
        if (empty($scores)) return null;
        return round(array_sum($scores) / count($scores), 1);
    }
}
