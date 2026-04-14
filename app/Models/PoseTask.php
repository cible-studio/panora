<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Panel;
use App\Models\Campaign;
use App\Models\User;
use App\Enums\PanelStatus;
use App\Enums\PoseTaskStatus;  // ← AJOUTER CETTE LIGNE


class PoseTask extends Model
{
    protected $fillable = [
        'panel_id', 'campaign_id',
        'assigned_user_id', 'team_name',
        'scheduled_at', 'done_at', 'status', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'done_at'      => 'datetime',
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

}
