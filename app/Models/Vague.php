<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vague d'une campagne (2026-11-04) : un message affiché pendant une
 * partie de l'année. La campagne reste le chapeau (contrat, facture) ;
 * les poses d'un changement de visuel portent leur vague, leurs piges
 * suivent. Règles : App\Services\Vagues\VagueService.
 */
class Vague extends Model
{
    protected $fillable = ['campaign_id', 'numero', 'nom', 'debut_le', 'created_by'];

    protected $casts = [
        'numero'   => 'integer',
        'debut_le' => 'date',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function poses(): HasMany
    {
        return $this->hasMany(PoseTask::class, 'vague_id');
    }

    /**
     * « Vague 2 · Phase 2 », ou « Vague 2 » quand le nom est déjà
     * « Vague 2 » (nom proposé par défaut) — pas de doublon.
     */
    public function libelle(): string
    {
        $prefixe = 'Vague ' . $this->numero;

        return mb_strtolower(trim($this->nom)) === mb_strtolower($prefixe)
            ? $prefixe
            : $prefixe . ' · ' . $this->nom;
    }

    /** Badge compact : « V2 · Phase 2 ». */
    public function badge(): string
    {
        return 'V' . $this->numero . (mb_strtolower(trim($this->nom)) === 'vague ' . $this->numero ? '' : ' · ' . $this->nom);
    }
}
