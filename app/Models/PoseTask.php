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
        // Identité du tech saisie via le lien public (cas tech non créé en User)
        'tech_name_self', 'tech_name_self_at', 'tech_name_self_ip',
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
        'scheduled_at'        => 'datetime',
        'done_at'             => 'datetime',
        'started_at'          => 'datetime',
        'whatsapp_sent_at'    => 'datetime',
        'tech_name_self_at'   => 'datetime',
        'progress_percent'    => 'integer',
        'estimated_minutes'   => 'integer',
        'real_minutes'        => 'integer',
    ];

    /**
     * Affiche le nom du technicien à présenter en UI (priorité formelle).
     *  1. assigned_user_id présent → relation technicien (User Panora)
     *  2. tech_name_self saisi via lien public (badge "déclaré")
     *  3. team_name si défini (équipe)
     *  4. Sinon — non assigné
     *
     * @return array{name: string, type: string, color: string}
     */
    public function technicianDisplay(): array
    {
        if ($this->technicien) {
            return ['name' => $this->technicien->name, 'type' => 'user', 'color' => '#16a34a'];
        }
        if ($this->tech_name_self) {
            return ['name' => $this->tech_name_self, 'type' => 'declared', 'color' => '#3b82f6'];
        }
        if ($this->team_name) {
            return ['name' => $this->team_name, 'type' => 'team', 'color' => '#8b5cf6'];
        }
        return ['name' => '— Non assigné —', 'type' => 'none', 'color' => '#9ca3af'];
    }

    /**
     * Enregistre le nom du technicien saisi via le lien public — appelé
     * par PoseTaskPublicController dès qu'on reçoit un tech_name non vide.
     * Idempotent : si déjà saisi, on ne réécrase pas (sauf si l'admin
     * efface manuellement via l'interface).
     */
    public function captureSelfTechName(?string $name, ?string $ip = null): bool
    {
        $name = trim((string) $name);
        if ($name === '') return false;
        if ($this->tech_name_self && $this->tech_name_self === $name) return false;
        // Si déjà saisi avec un autre nom, on conserve le 1er (audit
        // historique). Le changement éventuel apparaît dans
        // PoseTaskAction.actor.
        if ($this->tech_name_self) return false;

        $this->forceFill([
            'tech_name_self'    => $name,
            'tech_name_self_at' => now(),
            'tech_name_self_ip' => $ip,
        ])->save();
        return true;
    }

    /**
     * M2 Performance Technicien — auto-sync pose_tasks.team_name VARCHAR
     * depuis la nouvelle relation users.pose_team_id.
     *
     * Observer câblé au saving : si assigned_user_id pointe sur un user
     * qui a une équipe (pose_team_id), on met à jour team_name avec le
     * nom de l'équipe AU MOMENT de l'assignation. Cohérent avec la
     * volonté de préserver l'historique : si le tech change d'équipe
     * plus tard, ses ANCIENNES poses gardent leur ancien team_name.
     *
     * Garde-fou A : le sous-titre explicatif sur les vues prévient
     * l'utilisateur de ce comportement.
     */
    protected static function booted(): void
    {
        static::saving(function (PoseTask $task) {
            if (!$task->isDirty('assigned_user_id') || empty($task->assigned_user_id)) {
                return;
            }
            $user = User::with('poseTeam:id,name')->find($task->assigned_user_id);
            if ($user?->poseTeam) {
                $task->team_name = $user->poseTeam->name;
            }
        });
    }

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

    public function actions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoseTaskAction::class)->orderByDesc('created_at');
    }

    /**
     * Dernier signalement de problème terrain (panneau cassé / accès bloqué /
     * mauvaise adresse / autre) ENCORE OUVERT — pour afficher au tech qu'il
     * a déjà signalé et au MP/admin pour suivi.
     *
     * Filtre `resolved_at IS NULL` : dès que l'admin traite le signal (mise
     * en maintenance ou "marquer traité"), il disparaît du badge tech →
     * le tech peut re-signaler si un nouveau problème apparaît, sans voir
     * l'ancien rappel obsolète. Sinon il pense que l'admin n'a rien fait.
     */
    public function lastProblemReport(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PoseTaskAction::class)
            ->where('action', 'problem_reported')
            ->whereNull('resolved_at')
            ->latestOfMany('created_at');
    }

    /**
     * Dernière pige REJETÉE par le MP — pour afficher au tech le motif du
     * refus directement sur la carte (sans nécessiter qu'il ouvre la pose).
     */
    public function latestRejectedPige(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Pige::class)
            ->where('status', 'rejete')
            ->latestOfMany('created_at');
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

    /**
     * Tâche "vraiment fermée" : annulée. Une tâche "réalisée" reste
     * ouverte côté terrain pour permettre au technicien d'uploader la
     * pige photo APRÈS avoir marqué la pose à 100% (workflow réel :
     * pose effective d'abord, photo de preuve ensuite).
     */
    public function isLocked(): bool
    {
        return $this->status === PoseTaskStatus::CANCELLED->value;
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

    /** URL publique de mise à jour pour le technicien.
     *  Spec Évolution 3 : format unifié /pige/{token}. */
    public function publicUrl(): ?string
    {
        $token = $this->public_token;
        return $token ? route('pige.public.show', $token) : null;
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

        // Premier passage > 0 → marque started_at + transition en_cours
        if ($percent > 0 && !$this->started_at) {
            $this->started_at = now();
            if (in_array($this->status, [
                PoseTaskStatus::PLANNED->value,
                PoseTaskStatus::EN_ROUTE->value,
            ])) {
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
