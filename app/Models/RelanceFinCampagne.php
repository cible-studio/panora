<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal du mail « Votre campagne se termine » (J-7) — 2026-10-21.
 *
 * Une ligne par campagne, par date de fin, par mode (test / production) et
 * par étape (2026-10-22) : « fin » = mail J-7 simple des campagnes de moins
 * de 12 mois ; j60…j7 = séquence de renouvellement des campagnes annuelles ;
 * « accuse » = accusé de réception d'une réponse.
 * Cf. RelanceFinCampagneService et RenouvellementService.
 */
class RelanceFinCampagne extends Model
{
    protected $table = 'relances_fin_campagne';

    public const STATUT_ENVOYE = 'envoye';
    public const STATUT_ECHEC  = 'echec';
    public const STATUT_EXCLU  = 'exclu';

    public const MODE_TEST       = 'test';
    public const MODE_PRODUCTION = 'production';
    // Envoi de test déclenché à la main (éditeur, fiche dossier, liens « essai ») :
    // rangé à part pour ne pas compter comme l'envoi automatique du mode test.
    public const MODE_ESSAI      = 'essai';

    public const ETAPE_FIN    = 'fin';
    public const ETAPE_ACCUSE = 'accuse';

    public const ETAPES = [
        'fin'    => 'Fin de campagne (J-7)',
        'j60'    => 'Renouvellement J-60',
        'j45'    => 'Renouvellement J-45',
        'j30'    => 'Renouvellement J-30',
        'j15'    => 'Renouvellement J-15',
        'j7'     => 'Renouvellement J-7',
        'accuse' => 'Accusé de réception',
    ];

    protected $fillable = [
        'campaign_id', 'client_id', 'fin_campagne', 'mode', 'etape', 'statut', 'canal',
        'destinataire', 'destinataires', 'copie', 'repondre_a', 'motif', 'brevo_message_id',
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

    public function libelleEtape(): string
    {
        return self::ETAPES[$this->etape ?? self::ETAPE_FIN] ?? (string) $this->etape;
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
