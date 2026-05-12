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
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(PoseTask::class, 'pose_task_id');
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
