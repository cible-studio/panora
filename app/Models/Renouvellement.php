<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dossier de renouvellement d'une campagne annuelle (2026-10-22).
 *
 * Un dossier par campagne et par date de fin. Tant que le statut est
 * « en_attente », la séquence de mails continue (J-60 → J-7) ; toute
 * réponse du client ou décision de l'équipe l'arrête.
 * Cf. RenouvellementService.
 */
class Renouvellement extends Model
{
    protected $table = 'renouvellements';

    public const EN_ATTENTE = 'en_attente';
    public const RENOUVELER = 'renouveler';
    public const AJUSTER    = 'ajuster';
    public const REFUS      = 'refus';
    public const RAPPEL     = 'rappel';
    public const CONFIRME   = 'confirme';

    /** Statut → [libellé, fond, texte]. */
    public const STATUTS = [
        self::EN_ATTENTE => ['En attente de réponse', '#eef2ff', '#3730a3'],
        self::RENOUVELER => ['Veut renouveler', '#dcfce7', '#166534'],
        self::AJUSTER    => ['Veut ajuster', '#fef3c7', '#92400e'],
        self::REFUS      => ['Ne renouvelle pas', '#fee2e2', '#991b1b'],
        self::RAPPEL     => ['Demande un rappel', '#e0f2fe', '#075985'],
        self::CONFIRME   => ['Renouvellement confirmé', '#166534', '#ffffff'],
    ];

    protected $fillable = [
        'campaign_id', 'client_id', 'fin_campagne', 'statut', 'rappel_le', 'token',
        'reponse_choix', 'reponse_message', 'reponse_at', 'reponse_par',
        'confirme_par', 'confirme_at', 'historique',
    ];

    protected $casts = [
        'fin_campagne' => 'date',
        'rappel_le'    => 'date',
        'reponse_at'   => 'datetime',
        'confirme_at'  => 'datetime',
        'historique'   => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function confirmateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirme_par');
    }

    public function libelle(): string
    {
        return self::STATUTS[$this->statut][0] ?? $this->statut;
    }

    /** @return array{0: string, 1: string}  fond, texte */
    public function couleurs(): array
    {
        $s = self::STATUTS[$this->statut] ?? [null, 'var(--surface2)', 'var(--text2)'];

        return [$s[1], $s[2]];
    }

    public function noter(string $action, ?string $texte = null, ?string $auteur = null): void
    {
        $h = $this->historique ?? [];
        $h[] = array_filter([
            'date'   => now()->toDateTimeString(),
            'auteur' => $auteur,
            'action' => $action,
            'texte'  => $texte,
        ], fn($v) => $v !== null && $v !== '');
        $this->historique = $h;
    }
}
