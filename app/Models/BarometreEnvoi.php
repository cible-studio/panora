<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baromètre clients (2026-11-17) : un client dans une édition. La ligne
 * naît à l'exclusion ou à la demande d'envoi ; elle fige l'adresse et
 * l'interlocuteur retenus. Les réponses sont dans BarometreReponse.
 */
class BarometreEnvoi extends Model
{
    protected $table = 'barometre_envois';

    public const DEMANDE = 'demande';
    public const ENVOYE  = 'envoye';
    public const ECHEC   = 'echec';

    /** Comment l'interlocuteur a été choisi (Client::destinataireBarometre). */
    public const SOURCES = [
        'principal' => 'Interlocuteur principal',
        'dossier'   => 'Interlocuteur du dernier dossier',
        'echange'   => 'Dernier échange par mail',
        'seul'      => 'Seul interlocuteur avec une adresse',
        'fiche'     => 'Adresse de la fiche entreprise',
    ];

    protected $fillable = [
        'edition', 'client_id', 'email', 'contact_nom', 'client_contact_id', 'source',
        'public_link_id',
        'invite_statut', 'invite_le', 'invite_brevo_id', 'invite_par',
        'relance_statut', 'relance_le', 'relance_brevo_id', 'relance_par', 'erreur',
        'ouvert_le', 'ouvertures',
        'exclu_motif', 'exclu_le', 'exclu_par',
    ];

    protected $casts = [
        'client_id'         => 'integer',
        'client_contact_id' => 'integer',
        'public_link_id'    => 'integer',
        'invite_le'         => 'datetime',
        'relance_le'        => 'datetime',
        'ouvert_le'         => 'datetime',
        'exclu_le'          => 'datetime',
        'ouvertures'        => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function lien(): BelongsTo
    {
        return $this->belongsTo(PublicLink::class, 'public_link_id');
    }

    public function estExclu(): bool
    {
        return $this->exclu_motif !== null;
    }

    /** L'invitation est partie, ou part en ce moment (jamais deux fois). */
    public function estInvite(): bool
    {
        return in_array($this->invite_statut, [self::DEMANDE, self::ENVOYE], true);
    }
}
