<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Un versement de taxes (ODP/TM) à une mairie — Suivi des mairies, 2026-11-16.
 *
 * Règle validée par écrit : une ligne par versement, rattachée à l'exercice
 * qu'elle règle, montant global + objet indicatif. La ligne ne s'efface
 * jamais : une erreur se corrige par annuler() avec un motif. Les lignes
 * annulées restent visibles (barrées) et sortent de tous les totaux.
 */
class TaxeReglement extends Model implements Auditable
{
    use AuditableTrait;

    public const OBJET_GLOBAL   = 'global';
    public const OBJET_ARRIERES = 'arrieres';
    public const OBJET_ODP      = 'odp';
    public const OBJET_TM       = 'tm';

    public const OBJETS = [
        self::OBJET_GLOBAL   => 'Tout (global)',
        self::OBJET_ARRIERES => 'Arriérés',
        self::OBJET_ODP      => 'ODP',
        self::OBJET_TM       => 'TM',
    ];

    // Mêmes modes que l'ancien suivi (CommuneTaxPayment::MODES).
    public const MODES = CommuneTaxPayment::MODES;

    public const ORIGINE_SAISIE  = 'saisie';
    public const ORIGINE_PAIEMENT = 'commune_tax_payment';
    public const ORIGINE_TAXE    = 'taxe';

    protected $fillable = [
        'commune_id', 'exercice', 'montant', 'paid_at', 'objet', 'mode',
        'reference', 'quittance_path', 'quittance_nom', 'commentaire',
        'recorded_by', 'origine', 'origine_id',
    ];

    protected $casts = [
        'exercice'  => 'integer',
        'montant'   => 'integer',
        'paid_at'   => 'date',
        'annule_le' => 'datetime',
    ];

    protected $auditExclude = ['updated_at'];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function saisiPar()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function annulePar()
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    /** Les versements qui comptent : tous sauf les annulés. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annule_le');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    public function annuler(string $motif, ?int $parUserId): void
    {
        $this->forceFill([
            'annule_le'        => now(),
            'annule_par'       => $parUserId,
            'motif_annulation' => mb_substr(trim($motif), 0, 500),
        ])->save();
    }

    public function getObjetLabelAttribute(): string
    {
        return self::OBJETS[$this->objet] ?? ucfirst((string) $this->objet);
    }

    public function getModeLabelAttribute(): ?string
    {
        return $this->mode ? (self::MODES[$this->mode] ?? ucfirst($this->mode)) : null;
    }
}
