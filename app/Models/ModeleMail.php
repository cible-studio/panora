<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Texte d'un mail client modifié dans Panora (2026-10-22).
 *
 * Sans ligne pour une clé, c'est le texte d'origine qui s'applique
 * (App\Services\Diffusion\ModelesMail::DEFAUTS).
 */
class ModeleMail extends Model
{
    protected $table = 'modeles_mail';

    protected $fillable = ['cle', 'objet', 'corps', 'modifie_par'];

    public function auteur(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
