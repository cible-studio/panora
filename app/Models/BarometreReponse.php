<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une réponse au baromètre, signalée par le script Google (2026-11-17).
 * Panora ne reçoit QUE le nom de la structure et l'heure : les réponses
 * elles-mêmes restent dans la feuille Google.
 */
class BarometreReponse extends Model
{
    protected $table = 'barometre_reponses';

    protected $fillable = ['edition', 'reference', 'structure', 'repondu_le', 'client_id', 'test', 'rattache_par'];

    protected $casts = [
        'repondu_le' => 'datetime',
        'client_id'  => 'integer',
        'test'       => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }
}
