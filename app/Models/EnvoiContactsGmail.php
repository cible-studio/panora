<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un envoi du bouton « Envoyer à Panora » du Google Sheets (2026-10-28),
 * ou une vérification sans écriture (`simulation`). `detail` garde le
 * résultat de chaque ligne, tel que renvoyé au Sheets.
 */
class EnvoiContactsGmail extends Model
{
    protected $table = 'envois_contacts_gmail';

    protected $fillable = [
        'envoye_par', 'simulation', 'lignes', 'crees', 'completes',
        'a_jour', 'refuses', 'entreprises_creees', 'detail',
    ];

    protected $casts = [
        'simulation'         => 'boolean',
        'lignes'             => 'integer',
        'crees'              => 'integer',
        'completes'          => 'integer',
        'a_jour'             => 'integer',
        'refuses'            => 'integer',
        'entreprises_creees' => 'integer',
        'detail'             => 'array',
    ];

    /** Résultat d'une ligne → libellé affiché (Sheets et Panora). */
    public const RESULTATS = [
        'cree'     => 'Créé',
        'complete' => 'Complété',
        'a_jour'   => 'Déjà à jour',
        'refuse'   => 'Refusé',
    ];
}
