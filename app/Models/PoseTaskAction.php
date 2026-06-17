<?php
namespace App\Models;

use App\Enums\DelayReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoseTaskAction extends Model
{
    public $timestamps = false;

    // ── Actions canoniques (valeurs de la colonne `action` VARCHAR(50)) ──
    public const ACTION_PROBLEM_REPORTED = 'problem_reported';
    public const ACTION_MOTIF_MODIFIED   = 'motif_modified';

    protected $fillable = [
        'pose_task_id',
        'action',
        'payload',
        'actor',
        'ip_address',
        'created_at',
        // ── Signalements terrain : résolution côté admin ──────────────
        'resolved_at',
        'resolved_by',
        'resolution_action',
        'maintenance_id',
    ];

    protected $casts = [
        'payload'     => 'array',
        'created_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(PoseTask::class, 'pose_task_id');
    }

    /** Admin qui a traité ce signalement. */
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Maintenance créée à partir de ce signalement (si action=maintenance). */
    public function maintenance()
    {
        return $this->belongsTo(Maintenance::class, 'maintenance_id');
    }

    public function isPending(): bool
    {
        return $this->action === self::ACTION_PROBLEM_REPORTED && $this->resolved_at === null;
    }

    /**
     * Considère un signalement comme RÉSOLU si l'une des conditions est vraie :
     *   - resolved_at est rempli (le MP a explicitement dismiss ou maintenance)
     *   - maintenance_id est rempli (Maintenance créée à partir du signal)
     * Précision B du brief — évite de compter "ouverts" des signaux déjà pris
     * en charge via une Maintenance sans que resolved_at ait été posé.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null || $this->maintenance_id !== null;
    }

    /** Inverse direct — pratique dans les Collections (->filter). */
    public function isOpen(): bool
    {
        return $this->action === self::ACTION_PROBLEM_REPORTED && !$this->isResolved();
    }

    /** Motif d'origine déclaré (= type dans le payload du problem_reported). */
    public function originMotif(): ?DelayReason
    {
        if ($this->action !== self::ACTION_PROBLEM_REPORTED) return null;
        return DelayReason::tryFrom((string) ($this->payload['type'] ?? ''));
    }

    /**
     * Amendements de motif postérieurs à ce problem_reported.
     * Triés par created_at croissant — le dernier = motif courant.
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'pose_task_id', 'pose_task_id')
            ->where('action', self::ACTION_MOTIF_MODIFIED)
            ->where('created_at', '>=', $this->created_at)
            ->whereJsonContains('payload->target_action_id', $this->id)
            ->orderBy('created_at');
    }

    /**
     * Motif effectif = dernier amendement si présent, sinon motif d'origine.
     * Utilisé partout pour les stats et l'affichage UI courant.
     */
    public function effectiveMotif(): ?DelayReason
    {
        $latestAmendment = $this->amendments()
            ->latest('created_at')
            ->first();
        if ($latestAmendment) {
            $to = DelayReason::tryFrom((string) ($latestAmendment->payload['to'] ?? ''));
            if ($to) return $to;
        }
        return $this->originMotif();
    }

    /**
     * Append an audit entry for a PoseTask action.
     *
     * @param  int         $taskId
     * @param  string      $action   status_changed | progress_updated | photo_uploaded | photo_replaced | photo_deleted | marked_done
     * @param  array       $payload  arbitrary context (old/new status, path, %, …)
     * @param  string|null $actor    tech name or null
     * @param  string|null $ip
     */
    public static function log(
        int $taskId,
        string $action,
        array $payload = [],
        ?string $actor = null,
        ?string $ip = null,
    ): void {
        static::create([
            'pose_task_id' => $taskId,
            'action'       => $action,
            'payload'      => $payload ?: null,
            'actor'        => $actor,
            'ip_address'   => $ip,
            'created_at'   => now(),
        ]);
    }
}
