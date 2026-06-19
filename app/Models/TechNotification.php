<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SM2c B3 — Notification destinée à un technicien (drawer notifications).
 *
 * Helper statique `notify()` pour émettre depuis les controllers/services
 * sans dépendre du DatabaseChannel Laravel natif.
 */
class TechNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'detail', 'payload', 'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Émet une notification pour un tech. Type canonique :
     *   photo_rejected | new_pose | photo_validated.
     */
    public static function notify(int $userId, string $type, string $title, ?string $detail = null, array $payload = []): self
    {
        return self::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'detail'  => $detail,
            'payload' => $payload ?: null,
        ]);
    }
}
