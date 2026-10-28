<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal des envois de disponibilités aux clients.
 *
 * mode   : auto | manuel | test
 * statut : demande (envoi manuel en attente du planificateur) | prepare
 *          (PDF prêt, pas encore envoyé) | en_cours | envoye | echec
 */
class DiffusionEnvoi extends Model
{
    protected $table = 'diffusion_envois';

    public const MODE_AUTO   = 'auto';
    public const MODE_MANUEL = 'manuel';
    public const MODE_TEST   = 'test';

    public const STATUT_DEMANDE  = 'demande';
    public const STATUT_PREPARE  = 'prepare';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_ENVOYE   = 'envoye';
    public const STATUT_ECHEC    = 'echec';

    protected $fillable = [
        'creneau', 'periode_debut', 'periode_fin', 'mode', 'declenche_par',
        'statut', 'nb_destinataires', 'nb_panneaux', 'pdf_path', 'pdf_token',
        'brevo_campaign_id', 'erreur', 'envoye_at',
    ];

    protected $casts = [
        'creneau'          => 'date',
        'periode_debut'    => 'date',
        'periode_fin'      => 'date',
        'envoye_at'        => 'datetime',
        'nb_destinataires' => 'integer',
        'nb_panneaux'      => 'integer',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declenche_par');
    }

    public function lienPdf(): ?string
    {
        return $this->pdf_token ? route('diffusion.pdf', $this->pdf_token) : null;
    }

    /** Filtres du journal des envois (2026-10-28). */
    public const FILTRES = ['tous' => 'Tous', 'clients' => 'Envois aux clients', 'tests' => 'Tests', 'echecs' => 'Échecs'];

    public function scopeFiltre(Builder $q, string $filtre): Builder
    {
        return match ($filtre) {
            'clients' => $q->whereIn('mode', [self::MODE_AUTO, self::MODE_MANUEL]),
            'tests'   => $q->where('mode', self::MODE_TEST),
            'echecs'  => $q->where('statut', self::STATUT_ECHEC),
            default   => $q,
        };
    }

    /** « 2026-10 » → les envois créés ce mois-là ; null ou autre chose → tous. */
    public function scopeDuMois(Builder $q, ?string $mois): Builder
    {
        if ($mois === null || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
            return $q;
        }
        $debut = Carbon::createFromFormat('Y-m-d', $mois . '-01')->startOfDay();

        return $q->whereBetween('created_at', [$debut, $debut->copy()->endOfMonth()]);
    }

    public function libelleMode(): string
    {
        return match ($this->mode) {
            self::MODE_AUTO   => 'automatique',
            self::MODE_MANUEL => 'manuel',
            self::MODE_TEST   => 'test',
            default           => $this->mode,
        };
    }
}
