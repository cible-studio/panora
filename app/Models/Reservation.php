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
        'proposition_reminded_j2_at',
        'proposition_reminded_j5_at',
        // Motif annulation
        'cancel_type',            // client_demande|budget|concurrent|report|autre
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        // Motif refus client (proposition)
        'refus_reason_code',      // budget|zones|periode|concurrent|delais|autre
    ];

    /**
     * Codes prédéfinis pour le motif de refus d'une proposition.
     * Sert à alimenter la modale UI client + à valider l'input.
     */
    public const REFUS_REASONS = [
        'budget'     => '💰 Budget trop élevé',
        'zones'      => '📍 Zones non adaptées',
        'periode'    => '📅 Période ne convient pas',
        'concurrent' => '⚔️ Choix d\'un autre prestataire',
        'delais'     => '⏱ Délais trop courts',
        'autre'      => '📝 Autre raison',
    ];

    protected $casts = [
        'start_date'             => 'date',
        'end_date'               => 'date',
        'confirmed_at'           => 'datetime',
        'cancelled_at'           => 'datetime',
        'total_amount'           => 'decimal:2',
        'status'                 => ReservationStatus::class,
        'is_technical'           => 'boolean',
        'proposition_sent_at'         => 'datetime',
        'proposition_viewed_at'       => 'datetime',
        'proposition_expires_at'      => 'datetime',
        'proposition_reminded_j2_at'  => 'datetime',
        'proposition_reminded_j5_at'  => 'datetime',
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
        // wherePivot('source','interne') protège les lignes externes (lignes
        // avec external_panel_id IS NOT NULL et source='externe') contre les
        // sync()/detach() effectués par cette relation. Les externes passent
        // par la relation externalPanels() ci-dessous.
        return $this->belongsToMany(Panel::class, 'reservation_panels')
                    ->wherePivot('source', 'interne')
                    ->withPivot('unit_price', 'total_price', 'source')
                    ->withTimestamps();
    }

    /**
     * Panneaux de régies externes liés à la réservation.
     * Lecture seule via Eloquent — l'écriture passe par INSERT/DELETE direct
     * dans le contrôleur (cf. ReservationController) pour cohérence avec
     * le verrou pessimiste anti double-booking.
     */
    public function externalPanels()
    {
        return $this->belongsToMany(\App\Models\ExternalPanel::class, 'reservation_panels', 'reservation_id', 'external_panel_id')
                    ->wherePivot('source', 'externe')
                    ->withPivot('unit_price', 'total_price', 'source')
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

    /**
     * Tous les panneaux de la proposition (internes + externes), normalisés
     * en arrays uniformes. Utilisé par les vues admin/client/mail pour ne
     * pas avoir à dupliquer la logique de mapping deux fois.
     *
     * Le champ 'source' permet de distinguer 'interne' (Panel) d'un panneau
     * 'externe' (ExternalPanel) côté UI quand c'est nécessaire.
     */
    public function proposalPanels(?float $months = null): \Illuminate\Support\Collection
    {
        $months = $months ?? 1.0;

        $internal = $this->panels->map(function ($panel) use ($months) {
            $photo = $panel->photos->sortBy('ordre')->first();
            return [
                'id'           => $panel->id,
                'source'       => 'interne',
                'reference'    => $panel->reference,
                'name'         => $panel->name,
                'commune'      => $panel->commune?->name ?? '—',
                'zone'         => $panel->zone?->name    ?? '—',
                'format'       => $panel->format?->name  ?? '—',
                'dimensions'   => $panel->format?->dimensions_label,
                'surface'      => $panel->format?->surface_label,
                'category'     => $panel->category?->name ?? '—',
                'is_lit'       => (bool) $panel->is_lit,
                'monthly_rate' => (float) ($panel->pivot->unit_price  ?? $panel->monthly_rate ?? 0),
                'total'        => (float) ($panel->pivot->total_price ?? ($panel->monthly_rate ?? 0) * $months),
                'photo_url'    => $photo
                    ? asset('storage/' . ltrim($photo->path, '/'))
                    : null,
                'photos'       => $panel->photos->sortBy('ordre')->map(fn($p) => [
                    'url' => asset('storage/' . ltrim($p->path, '/'))
                ])->values()->toArray(),
                'orientation'  => $panel->orientation ?? null,
                'daily_traffic'=> $panel->daily_traffic ?? null,
            ];
        });

        $external = $this->externalPanels->map(function ($panel) use ($months) {
            return [
                'id'           => $panel->id,
                'source'       => 'externe',
                'reference'    => $panel->code_panneau,
                'name'         => $panel->designation,
                'commune'      => $panel->commune?->name ?? '—',
                'zone'         => $panel->zone?->name    ?? '—',
                'format'       => $panel->format?->name  ?? '—',
                'dimensions'   => $panel->format?->dimensions_label,
                'surface'      => $panel->format?->surface_label,
                'category'     => $panel->category?->name ?? '—',
                'is_lit'       => (bool) $panel->is_lit,
                'monthly_rate' => (float) ($panel->pivot->unit_price  ?? $panel->monthly_rate ?? 0),
                'total'        => (float) ($panel->pivot->total_price ?? ($panel->monthly_rate ?? 0) * $months),
                'photo_url'    => $panel->photo_url,
                'photos'       => $panel->photo_url
                    ? [['url' => $panel->photo_url]]
                    : [],
                'orientation'  => $panel->orientation ?? null,
                'daily_traffic'=> $panel->daily_traffic ?? null,
            ];
        });

        return $internal->concat($external);
    }

    /**
     * Compte total de panneaux (internes + externes) — pour les listings où
     * on ne veut pas charger les détails complets.
     */
    public function getProposalPanelCountAttribute(): int
    {
        return $this->panels->count() + $this->externalPanels->count();
    }

    /**
     * @deprecated Utiliser PanelFormat::dimensions_label désormais. Conservé
     *             le temps que les éventuels callers externes migrent.
     */
    private function formatPanelDims($format): ?string
    {
        return $format?->dimensions_label;
    }
}