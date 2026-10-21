<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal du mail « Votre campagne se termine » (J-7) — 2026-10-21.
 *
 * Une ligne par campagne, par date de fin et par mode (test / production).
 * Cf. RelanceFinCampagneService.
 */
class RelanceFinCampagne extends Model
{
    protected $table = 'relances_fin_campagne';

    public const STATUT_ENVOYE = 'envoye';
    public const STATUT_ECHEC  = 'echec';
    public const STATUT_EXCLU  = 'exclu';

    public const MODE_TEST       = 'test';
    public const MODE_PRODUCTION = 'production';

    protected $fillable = [
        'campaign_id', 'client_id', 'fin_campagne', 'mode', 'statut', 'canal',
        'destinataire', 'copie', 'repondre_a', 'motif', 'brevo_message_id',
        'tentatives', 'envoye_at',
    ];

    protected $casts = [
        'fin_campagne' => 'date',
        'envoye_at'    => 'datetime',
        'tentatives'   => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** @return array{0: string, 1: string, 2: string}  fond, texte, libellé */
    public function badge(): array
    {
        return match ($this->statut) {
            self::STATUT_ENVOYE => ['#dcfce7', '#166534', 'Envoyé'],
            self::STATUT_ECHEC  => ['#fee2e2', '#991b1b', 'À retenter'],
            self::STATUT_EXCLU  => ['var(--surface2)', 'var(--text2)', 'Non envoyé'],
            default             => ['var(--surface2)', 'var(--text2)', $this->statut],
        };
    }
}
