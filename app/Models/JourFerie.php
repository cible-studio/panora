<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jour férié — exclu des envois automatiques de disponibilités.
 *
 * source : fixe | chretien | musulman | manuel
 * Les fêtes fixes et chrétiennes sont pré-remplies par
 * App\Services\Diffusion\JoursFeriesService ; les fêtes musulmanes sont
 * fixées chaque année par décret et saisies par l'admin.
 */
class JourFerie extends Model
{
    protected $table = 'jours_feries';

    protected $fillable = ['date', 'libelle', 'source'];

    protected $casts = [
        'date' => 'date',
    ];
}
