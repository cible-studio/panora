<?php
namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'client_id',
        'user_id',
        'start_date',
        'end_date',
        'status',
        'type',
        'proposition_slug',       // ← URL lisible proposition
        'total_amount',
        'notes',
        'confirmed_at',
        'is_technical',
        'proposition_token',
        'proposition_sent_at',
        'proposition_viewed_at',
        'proposition_expires_at',
        // Motif annulation
        'cancel_type',            // client_demande|budget|concurrent|report|autre
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'start_date'             => 'date',
        'end_date'               => 'date',
        'confirmed_at'           => 'datetime',
        'cancelled_at'           => 'datetime',
        'total_amount'           => 'decimal:2',
        'status'                 => ReservationStatus::class,
        'is_technical'           => 'boolean',
        'proposition_sent_at'    => 'datetime',
        'proposition_viewed_at'  => 'datetime',
        'proposition_expires_at' => 'datetime',
    ];

    // Matrice des transitions autorisées
    public const ALLOWED_TRANSITIONS = [
        'en_attente' => ['confirme', 'refuse', 'annule'],
        'confirme'   => ['annule'],
        'refuse'     => [],
        'annule'     => [],
        'termine'    => [],
    ];

    // ── Validation au niveau Model ─────────────────────────
    protected static function booted(): void
    {
        $validateDates = function (Reservation $r) {
            if ($r->end_date && $r->start_date) {
                if ($r->end_date->lte($r->start_date)) {
                    throw new InvalidArgumentException(
                        'La date de fin doit être strictement après la date de début.'
                    );
                }
                if ($r->start_date->diffInMonths($r->end_date) > 36) {
                    throw new InvalidArgumentException(
                        'La durée maximale d\'une réservation est de 36 mois.'
                    );
                }
            }
        };

        static::creating($validateDates);
        static::updating(function (Reservation $r) use ($validateDates) {
            if ($r->isDirty('start_date') || $r->isDirty('end_date')) {
                $validateDates($r);
            }
        });
    }

    // Mise à jour sans déclencher les observers (utilisé par CampaignService)
    public function updateWithoutObservers(array $attributes): bool
    {
        return DB::table($this->getTable())
            ->where('id', $this->id)
            ->update($attributes) === 1;
    }

    // ── Relations ──────────────────────────────────────────
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'reservation_panels')
                    ->withPivot('unit_price', 'total_price')
                    ->withTimestamps();
    }

    public function campaign()
    {
        return $this->hasOne(Campaign::class);
    }

    // ── Helpers métier ─────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status->value === 'en_attente'
            && !$this->client?->trashed();
    }

    public function isCancellable(): bool
    {
        return in_array($this->status->value, ['en_attente', 'confirme'])
            && !$this->client?->trashed();
    }

    public function isDeletable(): bool
    {
        return in_array($this->status->value, ['annule', 'refuse'])
            && !$this->hasActiveCampaign();
    }

    public function canChangeStatus(): bool
    {
        return !$this->client?->trashed()
            && !empty(self::ALLOWED_TRANSITIONS[$this->status->value] ?? []);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array(
            $newStatus,
            self::ALLOWED_TRANSITIONS[$this->status->value] ?? []
        );
    }

    public function hasActiveCampaign(): bool
    {
        return $this->campaign()
            ->whereNotIn('status', ['termine', 'annule'])
            ->exists();
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['en_attente', 'confirme']);
    }

    public function scopeArchived($query)
    {
        return $query->whereIn('status', ['annule', 'refuse']);
    }

    public function scopeOptions($query)
    {
        return $query->where('status', 'en_attente')->where('type', 'option');
    }

    // ── Proposition helpers ─────────────────────────────────

    public function propositionEnAttente(): bool
    {
        return $this->proposition_token !== null
            && $this->proposition_sent_at !== null
            && ($this->proposition_expires_at === null
                || $this->proposition_expires_at->isFuture())
            && $this->status->value === 'en_attente';
    }

    public function propositionVue(): bool
    {
        return $this->proposition_viewed_at !== null;
    }

    public function getLienPropositionAttribute(): ?string
    {
        if (!$this->proposition_token || !$this->proposition_slug) {
            return null;
        }
        return route('proposition.show', [
            $this->reference,
            $this->proposition_slug,
        ]);
    }
}