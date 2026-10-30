<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Devis (Quote) — proposition commerciale NON BLOQUANTE.
 *
 * Contrairement à Reservation type=option (bloque les panneaux), le devis
 * laisse les panneaux libres jusqu'à ce que le client accepte. Plusieurs
 * commerciaux peuvent proposer les mêmes panneaux à des prospects
 * différents sans conflit — le premier qui fait accepter son devis
 * gagne les panneaux (via QuoteConverter qui re-check dispo).
 *
 * Cycle de vie : cf. App\Enums\QuoteStatus.
 */
class Quote extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'reference', 'client_id', 'client_contact_id', 'campaign_id',
        'commercial_user_id', 'created_by',
        'title', 'status', 'version',
        'period_start', 'period_end',
        'valid_days', 'sent_at', 'expires_at',
        'decision_at', 'decision_reason',
        'notes_client', 'notes_internes',
        'tva', 'remise_pct',
        'amount', 'net_ht', 'tva_amount', 'tsp_amount',
        'tm_total', 'odp_total',
        'services_ht_total', 'services_ttc_total',
        'amount_ttc', 'total_a_payer',
        'public_token', 'converted_reservation_id',
    ];

    protected $casts = [
        'status'          => QuoteStatus::class,
        'period_start'    => 'date',
        'period_end'      => 'date',
        'sent_at'         => 'datetime',
        'expires_at'      => 'datetime',
        'decision_at'     => 'datetime',
        'tva'             => 'decimal:2',
        'remise_pct'      => 'decimal:2',
        // Tous les montants en entiers FCFA — pas de centimes.
        'amount'                => 'integer',
        'net_ht'                => 'integer',
        'tva_amount'            => 'integer',
        'tsp_amount'            => 'integer',
        'tm_total'              => 'integer',
        'odp_total'             => 'integer',
        'services_ht_total'     => 'integer',
        'services_ttc_total'    => 'integer',
        'amount_ttc'            => 'integer',
        'total_a_payer'         => 'integer',
        'version'               => 'integer',
        'valid_days'            => 'integer',
    ];

    // ─── Relations ─────────────────────────────────────────────────
    public function client(): BelongsTo             { return $this->belongsTo(Client::class); }

    /**
     * Adresses où envoyer un document de ce dossier : l'adresse de la fiche
     * client et l'interlocuteur du devis (Client::destinatairesDocuments,
     * 2026-10-29). Vide si ni l'un ni l'autre.
     *
     * @return list<string>
     */
    public function adressesClient(): array
    {
        return $this->client ? array_column($this->client->destinatairesDocuments($this->interlocuteur), 'email') : [];
    }
    /** Interlocuteur du client qui suit ce devis (2026-10-29). */
    public function interlocuteur(): BelongsTo      { return $this->belongsTo(ClientContact::class, 'client_contact_id'); }
    public function campaign(): BelongsTo           { return $this->belongsTo(Campaign::class); }
    public function commercial(): BelongsTo         { return $this->belongsTo(User::class, 'commercial_user_id'); }
    public function creator(): BelongsTo            { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany                { return $this->hasMany(QuoteLine::class); }
    public function services(): HasMany             { return $this->hasMany(QuoteService::class); }
    public function convertedReservation(): BelongsTo { return $this->belongsTo(Reservation::class, 'converted_reservation_id'); }

    // ─── Scopes ────────────────────────────────────────────────────
    public function scopeForCommercialUser($q, int $userId)
    {
        return $q->where('commercial_user_id', $userId);
    }

    public function scopeActionable($q)
    {
        return $q->whereIn('status', QuoteStatus::actionableStatuses());
    }

    public function scopeExpiringSoon($q, int $days = 7)
    {
        return $q->where('status', QuoteStatus::ENVOYE->value)
                 ->whereNotNull('expires_at')
                 ->where('expires_at', '<=', now()->addDays($days))
                 ->where('expires_at', '>', now());
    }

    public function scopeAlreadyExpired($q)
    {
        return $q->where('status', QuoteStatus::ENVOYE->value)
                 ->whereNotNull('expires_at')
                 ->where('expires_at', '<', now());
    }

    // ─── Helpers métier ────────────────────────────────────────────

    /** Peut-il être modifié ? (brouillon ou en négociation) */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            QuoteStatus::BROUILLON,
            QuoteStatus::EN_NEGOCIATION,
        ], true);
    }

    /** Peut-il être envoyé au client ? */
    public function isSendable(): bool
    {
        return in_array($this->status, [
            QuoteStatus::BROUILLON,
            QuoteStatus::EN_NEGOCIATION,
        ], true)
        && $this->lines->count() > 0;
    }

    /** Peut-il être converti en réservation ferme ? */
    public function isConvertible(): bool
    {
        return in_array($this->status, [
            QuoteStatus::ACCEPTE,
            QuoteStatus::ACCEPTE_AVEC_CONFLIT,
        ], true)
        && $this->converted_reservation_id === null;
    }

    public function belongsToCommercialUser(int $userId): bool
    {
        return (int) $this->commercial_user_id === $userId;
    }

    /** URL publique unique du devis (pour clients sans compte). */
    public function publicUrl(): string
    {
        return url('/devis/' . $this->public_token);
    }

    /** Jours restants avant expiration (négatif si dépassé). */
    public function daysUntilExpiry(): ?int
    {
        if (!$this->expires_at) return null;
        return (int) now()->diffInDays($this->expires_at, false);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Génère une référence unique format DEV-YYYY-NNN.
     * Compteur reset chaque année civile.
     */
    public static function generateReference(): string
    {
        $year = now()->year;
        $prefixe = "DEV-{$year}-";
        // Plus grand numéro de l'année, corbeille comprise : un numéro n'est
        // jamais réattribué (2026-10-29 ; avant : comptage des devis, qui
        // redonnait un numéro déjà pris après une suppression).
        $dernier = self::withTrashed()
            ->where('reference', 'like', $prefixe . '%')
            ->pluck('reference')
            ->map(fn ($r) => (int) substr($r, strlen($prefixe)))
            ->max() ?? 0;
        return sprintf('DEV-%s-%03d', $year, $dernier + 1);
    }

    /**
     * Accepté par le client, ou devenu réservation : un tel devis ne se
     * supprime jamais (règle de la direction, 2026-10-29). Un devis archivé
     * après acceptation se reconnaît à son historique de modifications.
     */
    public function aEteAccepte(): bool
    {
        if (in_array($this->status, [QuoteStatus::ACCEPTE, QuoteStatus::ACCEPTE_AVEC_CONFLIT], true)
            || $this->converted_reservation_id !== null
            || Reservation::withTrashed()->where('quote_id', $this->id)->exists()) { // même supprimée depuis
            return true;
        }
        try {
            return $this->audits()->where('new_values', 'like', '%"status":"accepte%')->exists();
        } catch (\Illuminate\Database\QueryException $e) {
            return false; // historique indisponible : les autres preuves suffisent
        }
    }

    /** Génère un token public 64 hex chars (256 bits). */
    public static function generatePublicToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
