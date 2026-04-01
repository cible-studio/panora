<?php
// app/Models/Reservation.php — VERSION COMPLÈTE COHÉRENTE

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'client_id', 'user_id',
        'start_date', 'end_date',
        'status', 'type',
        'total_amount', 'notes', 'confirmed_at',
        'is_technical',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'confirmed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'status'       => ReservationStatus::class,
        'is_technical' => 'boolean',
    ];

    // ── Matrice des transitions autorisées ─────────────────
    // Centralisée ici — seule source de vérité
    public const ALLOWED_TRANSITIONS = [
        'en_attente' => ['confirme', 'refuse', 'annule'],
        'confirme'   => ['annule'],
        'refuse'     => [],   // terminal
        'annule'     => [],   // terminal
        'termine'    => [],   // terminal
    ];

    // ── Validation au niveau Model ─────────────────────────
    protected static function booted(): void
    {
        $validateDates = function (Reservation $r) {
            // end_date doit être STRICTEMENT après start_date
            if ($r->end_date && $r->start_date) {
                if ($r->end_date->lte($r->start_date)) {
                    throw new InvalidArgumentException(
                        'La date de fin doit être strictement après la date de début.'
                    );
                }
                // Durée max 36 mois
                if ($r->start_date->diffInMonths($r->end_date) > 36) {
                    throw new InvalidArgumentException(
                        'La durée maximale d\'une réservation est de 36 mois.'
                    );
                }
            }
        };
    
        // Valider à la création
        static::creating($validateDates);
    
        // Valider à la modification uniquement si les dates changent
        static::updating(function (Reservation $r) use ($validateDates) {
            if ($r->isDirty('start_date') || $r->isDirty('end_date')) {
                $validateDates($r);
            }
        });
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

    // ── Helpers métier (source de vérité pour les vues et controller) ──

    /**
     * Modifiable : uniquement en_attente ET client non supprimé
     */
    public function isEditable(): bool
    {
        return $this->status->value === 'en_attente'
            && ! $this->client?->trashed();
    }

    /**
     * Annulable : en_attente ou confirme ET client non supprimé
     * NB : Un client supprimé = réservation en lecture seule totale
     */
    public function isCancellable(): bool
    {
        return in_array($this->status->value, ['en_attente', 'confirme'])
            && ! $this->client?->trashed();
    }

    /**
     * Supprimable : uniquement annulé ou refusé ET sans campagne active
     * Admin uniquement (vérifié dans Policy)
     */
    public function isDeletable(): bool
    {
        return in_array($this->status->value, ['annule', 'refuse'])
            && ! $this->hasActiveCampaign();
    }

    /**
     * Changement de statut possible : client non supprimé + transition valide
     * Le client supprimé → aucune action possible, même le statut
     */
    public function canChangeStatus(): bool
    {
        return ! $this->client?->trashed()
            && ! empty(self::ALLOWED_TRANSITIONS[$this->status->value] ?? []);
    }

    /**
     * Vérifie si une transition vers newStatus est autorisée
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array(
            $newStatus,
            self::ALLOWED_TRANSITIONS[$this->status->value] ?? []
        );
    }

    /**
     * Campagne active liée (non terminée / non annulée)
     */
    public function hasActiveCampaign(): bool
    {
        return $this->campaign()->whereNotIn('status', ['termine', 'annule'])->exists();
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

    
}