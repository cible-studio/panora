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
    /**
     * Délai de grâce (en jours) avant qu'une pose planifiée soit
     * considérée "en retard".
     *
     * Règle métier validée par la patronne le 2026-07-06 :
     * "Une pose planifiée aujourd'hui ne doit pas apparaître en retard
     * dans l'instant. Le tech a jusqu'à J+2 pour effectuer la pose."
     *
     * Impact : lateThreshold() = today - 2 jours. Une pose est en retard
     * uniquement si scheduled_at < aujourd'hui - 2j.
     *
     * Exemple avec today = 06/07 :
     *   scheduled_at = 06/07 → OK (pas en retard)
     *   scheduled_at = 05/07 → OK
     *   scheduled_at = 04/07 → OK (limite)
     *   scheduled_at = 03/07 → EN RETARD
     *
     * Cette constante est LA source de vérité — tous les endroits qui
     * calculent "en retard" doivent passer par lateThreshold() ou
     * scopeOverdue(). Cf. CLAUDE.md §1 Harmonisation.
     */
    public const LATE_GRACE_DAYS = 2;

    /**
     * Retourne la borne date à partir de laquelle une pose planifiée
     * est "en retard". Utilisable en SQL via ->format('Y-m-d H:i:s').
     */
    public static function lateThreshold(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::today()->subDays(self::LATE_GRACE_DAYS);
    }

    /**
     * Retourne la borne date au format SQL — utilisable dans les
     * whereRaw / selectRaw où now() ne peut pas être injecté.
     */
    public static function lateThresholdSql(): string
    {
        return self::lateThreshold()->format('Y-m-d H:i:s');
    }

    /**
     * Une pose est-elle actuellement en retard ?
     * (status planifiee + scheduled_at avant la borne de grâce)
     */
    public function isLate(): bool
    {
        if ($this->status !== \App\Enums\PoseTaskStatus::PLANNED->value) return false;
        if (!$this->scheduled_at) return false;
        return $this->scheduled_at->lt(self::lateThreshold());
    }

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
        'arrived_at',  // 2026-07-06 : posé au palier 50% "Arrivé sur place"
        'whatsapp_sent_at',
        'public_token',
        // Multi-poses (ajout 2026-08-04) — cf. migration
        // add_rechange_support_to_pose_tasks + enum PoseTaskKind.
        'pose_kind',
        'replaces_pose_task_id',
        'replaced_at',
    ];

    protected $casts = [
        'scheduled_at'        => 'datetime',
        'done_at'             => 'datetime',
        'started_at'          => 'datetime',
        'arrived_at'          => 'datetime',
        'whatsapp_sent_at'    => 'datetime',
        'tech_name_self_at'   => 'datetime',
        'replaced_at'         => 'datetime',
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
            // 2026-06-19 — Multi-équipe : on n'auto-déduit team_name QUE si
            // le tech appartient à une seule équipe. S'il est dans plusieurs,
            // l'admin doit choisir manuellement (sinon ambiguïté). Si team_name
            // est déjà renseigné par l'admin, on respecte son choix.
            if (!empty($task->team_name)) {
                return;
            }
            $user = User::with('poseTeams:id,name')->find($task->assigned_user_id);
            $teams = $user?->poseTeams ?? collect();
            if ($teams->count() === 1) {
                $task->team_name = $teams->first()->name;
            }
            // Sinon (0 ou ≥2 équipes) : team_name reste tel quel — l'admin
            // garde la responsabilité du choix.
        });

        // SM2c B3 — Notifie le tech au moment où une PoseTask lui est
        // assignée (création OU update qui pose assigned_user_id pour la
        // 1re fois). Utilise saved() pour avoir l'ID + relation panel.
        static::saved(function (PoseTask $task) {
            if (!$task->assigned_user_id) return;
            $wasFreshlyAssigned = $task->wasRecentlyCreated
                || ($task->wasChanged('assigned_user_id') && $task->getOriginal('assigned_user_id') === null);
            if (!$wasFreshlyAssigned) return;

            $panelRef = $task->panel?->reference
                     ?? \App\Models\Panel::query()->whereKey($task->panel_id)->value('reference')
                     ?? 'un panneau';
            $sched = $task->scheduled_at ? $task->scheduled_at->format('d/m à H\hi') : null;

            try {
                \App\Models\TechNotification::notify(
                    userId:  $task->assigned_user_id,
                    type:    'new_pose',
                    title:   '🆕 Nouvelle pose : ' . $panelRef,
                    detail:  $sched ? 'Prévue le ' . $sched : 'À programmer dans ton carnet.',
                    payload: ['task_id' => $task->id, 'panel_id' => $task->panel_id],
                );
            } catch (\Throwable $e) {
                // On loggue mais on n'empêche jamais la création de la
                // PoseTask (ex : si la migration tech_notifications n'a pas
                // été exécutée sur cet env, ne pas casser la création).
                \Illuminate\Support\Facades\Log::warning('TechNotification new_pose failed', [
                    'task_id' => $task->id, 'err' => $e->getMessage(),
                ]);
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
     * ══ Piges liées à cette tâche (mise à jour 2026-08-04)
     *
     * Multi-poses : depuis qu'on autorise plusieurs PoseTask par
     * (panel, campaign), on ne peut PLUS regrouper les piges via
     * (panel_id, campaign_id) sinon toutes les poses de la campagne
     * verraient TOUTES les piges du panneau.
     *
     * Stratégie : matcher via `pose_task_id` (FK directe ajoutée par
     * migration 2026-05-13) EN PRIORITÉ. Si la pige a été créée avant
     * l'existence de cette FK (legacy) ET qu'elle appartient bien à
     * cette paire (panel + campaign), elle est aussi retournée —
     * assure la rétrocompat totale des campagnes anciennes.
     *
     * Concrètement : piges.pose_task_id = X OU (pose_task_id IS NULL
     * AND panel_id + campaign_id matchent).
     */
    public function piges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Pige::class, 'panel_id', 'panel_id')
            ->where(function ($q) {
                $q->where('pose_task_id', $this->id)
                  ->orWhere(function ($qq) {
                      $qq->whereNull('pose_task_id')
                         ->where('panel_id', $this->panel_id)
                         ->when($this->campaign_id, fn($qqq) => $qqq->where('campaign_id', $this->campaign_id));
                  });
            });
    }

    // ══ Chaînage des poses (multi-poses, ajout 2026-08-04) ══════════

    /**
     * Kind sous forme d'enum typé (défaut INITIAL si NULL en BDD).
     */
    public function kind(): \App\Enums\PoseTaskKind
    {
        return \App\Enums\PoseTaskKind::tryFrom($this->pose_kind ?? 'initial')
            ?? \App\Enums\PoseTaskKind::INITIAL;
    }

    /**
     * Pose PRÉCÉDENTE que celle-ci remplace (NULL pour une pose initiale).
     */
    public function replaces(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PoseTask::class, 'replaces_pose_task_id');
    }

    /**
     * Pose SUIVANTE qui a remplacé celle-ci (NULL tant que pas remplacée).
     * Relation inverse de replaces() — dans les faits une pose ne devrait
     * être remplacée que par UNE nouvelle (chaîne linéaire), mais on
     * expose HasMany pour tolérer les cas edge (2 rechanges créés en
     * parallèle par erreur — les rapports pourront les détecter).
     */
    public function replacedBy(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoseTask::class, 'replaces_pose_task_id');
    }

    /**
     * True si cette pose a été remplacée par une nouvelle (rechange).
     * L'ancienne affiche a implicitement été retirée.
     */
    public function isReplaced(): bool
    {
        return $this->replaced_at !== null;
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
        // Cf. constante LATE_GRACE_DAYS + lateThreshold() en haut de classe.
        return $q->where('status', PoseTaskStatus::PLANNED->value)
                 ->where('scheduled_at', '<', self::lateThreshold());
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
