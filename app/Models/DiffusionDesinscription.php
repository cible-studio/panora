<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adresse exclue de la diffusion des disponibilités.
 *
 * motif : desinscription | rebond | spam | manuel
 * Alimentée par le webhook Brevo (cf. BrevoWebhookController). Les clients
 * étant inscrits d'office, on ne stocke que ces exceptions.
 */
class DiffusionDesinscription extends Model
{
    protected $table = 'diffusion_desinscriptions';

    protected $fillable = ['email', 'motif', 'source', 'survenu_at'];

    protected $casts = [
        'survenu_at' => 'datetime',
    ];

    public function libelleMotif(): string
    {
        return match ($this->motif) {
            'desinscription' => 'Désinscrit',
            'rebond'         => 'Adresse invalide',
            'spam'           => 'Signalé comme spam',
            'manuel'         => 'Exclu manuellement',
            default          => $this->motif,
        };
    }
}
