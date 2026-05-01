<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use App\Models\Panel;
use App\Models\Campaign;
use App\Models\User;
use App\Enums\PanelStatus;
use App\Enums\PoseTaskStatus;


class PoseTask extends Model
{
    protected $fillable = [
        'panel_id', 'campaign_id',
        'assigned_user_id', 'team_name',
        'scheduled_at', 'done_at', 'status', 'notes',
        // Module WhatsApp + progression temps réel
        'progress_percent',
        'estimated_minutes',
        'real_minutes',
        'started_at',
        'whatsapp_sent_at',
        'public_token',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'done_at'          => 'datetime',
        'started_at'       => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'progress_percent' => 'integer',
        'estimated_minutes'=> 'integer',
        'real_minutes'     => 'integer',
    ];

    // ── RELATIONS ──

    // Une tâche concerne un panneau
    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }

    // Une tâche est liée à une campagne
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    // Une tâche est assignée à un technicien
    public function technicien()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * ══ NOUVEAU — Piges liées à cette tâche (même panneau + campagne)
     * Permet d'utiliser withCount(['piges', 'piges as pige_verifie_count' => ...])
     * dans PoseController::index() sans requête N+1.
    */
    public function piges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Pige::class, 'panel_id', 'panel_id')
            ->when($this->campaign_id, fn($q) => $q->where('campaign_id', $this->campaign_id));
    }

    // ── HELPERS ───────────────────────────────────────────────────
 
    public function isLate(): bool
    {
        return $this->status === PoseTaskStatus::PLANNED->value
            && $this->scheduled_at?->isPast();
    }
 
    public function isEditable(): bool
    {
        return !in_array($this->status, [
            PoseTaskStatus::COMPLETED->value,
            PoseTaskStatus::CANCELLED->value,
        ]);
    }
 
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            PoseTaskStatus::COMPLETED->value,
            PoseTaskStatus::CANCELLED->value,
        ]);
    }
 
    // ── SCOPES ────────────────────────────────────────────────────
 
    public function scopeOverdue(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('status', PoseTaskStatus::PLANNED->value)
                 ->where('scheduled_at', '<', now());
    }
 
    public function scopeForCampaign(\Illuminate\Database\Eloquent\Builder $q, int $campaignId): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('campaign_id', $campaignId);
    }



    /**
     * Statuts autorisés pour créer une tâche de pose
     */
    public const ALLOWED_POSE_STATUSES = [
        PanelStatus::LIBRE,
        PanelStatus::OCCUPE,
        PanelStatus::CONFIRME,
        PanelStatus::MAINTENANCE,
    ];

    /**
     * Détermine si le panneau peut recevoir une tâche de pose
     */
    public function canBePosed(): bool
    {
        return in_array($this->status, self::ALLOWED_POSE_STATUSES);
    }

    /**
     * Récupère le message d'explication si la pose est bloquée
     */
    public function getPoseBlockReason(): ?string
    {
        return match($this->status) {
            PanelStatus::LIBRE       => null,
            PanelStatus::OCCUPE      => null,
            PanelStatus::CONFIRME    => null,
            PanelStatus::MAINTENANCE => null,
            default                  => 'Ce panneau ne peut pas recevoir de pose (statut invalide).',
        };
    }

    /**
     * Type de pose recommandé selon le statut
     */
    public function getRecommendedPoseType(): string
    {
        return match($this->status) {
            PanelStatus::LIBRE       => 'new_campaign',
            PanelStatus::OCCUPE      => 'renewal_or_change',
            PanelStatus::CONFIRME    => 'planned_campaign',
            PanelStatus::MAINTENANCE => 'technical_intervention',
            default                  => 'unknown',
        };
    }

    /**
     * Icône du type de pose recommandé
     */
    public function getRecommendedPoseIcon(): string
    {
        return match($this->getRecommendedPoseType()) {
            'new_campaign'          => '🆕',
            'renewal_or_change'     => '🔄',
            'planned_campaign'      => '📋',
            'technical_intervention'=> '🔧',
            default                 => '❓',
        };
    }

    // ══════════════════════════════════════════════════════════════
    // MODULE WHATSAPP + PROGRESSION (suivi temps réel)
    // ══════════════════════════════════════════════════════════════

    /**
     * Génère un token public unique pour l'URL technicien (32 chars).
     * Idempotent : retourne le token existant s'il est déjà défini.
     */
    public function ensurePublicToken(): string
    {
        if (!$this->public_token) {
            // Génère + vérifie l'unicité (cas de collision astronomique)
            do {
                $candidate = Str::random(32);
            } while (self::where('public_token', $candidate)->exists());

            $this->forceFill(['public_token' => $candidate])->saveQuietly();
        }
        return $this->public_token;
    }

    /** URL publique de mise à jour pour le technicien */
    public function publicUrl(): ?string
    {
        $token = $this->public_token;
        return $token ? route('pose.public.show', $token) : null;
    }

    /**
     * Couleur de la barre de progression selon le %.
     *   0-33   → rouge   (en retard / pas commencé)
     *   34-66  → orange  (en cours, milieu)
     *   67-99  → bleu    (bien avancé)
     *   100    → vert    (terminé)
     */
    public function progressColor(): string
    {
        $p = (int) ($this->progress_percent ?? 0);
        return match (true) {
            $p >= 100 => '#22c55e', // vert
            $p >= 67  => '#3b82f6', // bleu
            $p >= 34  => '#f59e0b', // orange
            default   => '#ef4444', // rouge
        };
    }

    /** True si la tâche est en cours (commencée mais pas finie) */
    public function isInProgress(): bool
    {
        $p = (int) ($this->progress_percent ?? 0);
        return $p > 0 && $p < 100 && !$this->isTerminal();
    }

    /**
     * Met à jour la progression et déclenche les transitions de statut adéquates.
     *   - Premier % > 0 → started_at = now()
     *   - 100 %         → done_at = now() + status COMPLETED + real_minutes
     */
    public function updateProgress(int $percent): bool
    {
        $percent = max(0, min(100, $percent));
        $changed = false;

        if ((int) $this->progress_percent !== $percent) {
            $this->progress_percent = $percent;
            $changed = true;
        }

        // Premier passage > 0 → marque started_at
        if ($percent > 0 && !$this->started_at) {
            $this->started_at = now();
            if ($this->status === PoseTaskStatus::PLANNED->value) {
                $this->status = PoseTaskStatus::IN_PROGRESS->value;
            }
            $changed = true;
        }

        // Atteint 100 % → terminé
        if ($percent === 100 && !$this->done_at) {
            $this->done_at = now();
            $this->status  = PoseTaskStatus::COMPLETED->value;
            if ($this->started_at) {
                $this->real_minutes = max(1, (int) round(
                    $this->started_at->diffInMinutes(now())
                ));
            }
            $changed = true;
        }

        if ($changed) {
            $this->save();
        }
        return $changed;
    }
}
