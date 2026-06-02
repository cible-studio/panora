<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoseTaskAction extends Model
{
    public $timestamps = false;

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
        return $this->action === 'problem_reported' && $this->resolved_at === null;
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
