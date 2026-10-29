<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un mail parti de Panora, ou un envoi échoué (2026-10-29). Écrit par
 * App\Services\JournalMails ; lu par l'écran « Journal des mails ».
 */
class JournalMail extends Model
{
    protected $table = 'journal_mails';

    protected $fillable = [
        'envoye_le', 'statut', 'canal', 'type', 'sujet', 'destinataires', 'copie',
        'dossier_type', 'dossier_id', 'erreur', 'user_id',
    ];

    protected $casts = [
        'envoye_le'  => 'datetime',
        'dossier_id' => 'integer',
        'user_id'    => 'integer',
    ];

    public const STATUT_ENVOYE = 'envoye';
    public const STATUT_ECHEC  = 'echec';

    /** Dossier → [libellé, route de la fiche]. */
    public const DOSSIERS = [
        'campagne'    => ['Campagne', 'admin.campaigns.show'],
        'facture'     => ['Facture', 'admin.invoices.show'],
        'reservation' => ['Réservation', 'admin.reservations.show'],
        'devis'       => ['Devis', 'admin.quotes.show'],
        'client'      => ['Client', 'admin.clients.show'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** « CampaignStartedMail » → « Campaign started » ; étape Brevo telle quelle. */
    public function libelleType(): string
    {
        $court = class_basename($this->type);
        $court = preg_replace('/(Mail|Notification)$/', '', $court);

        return ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $court)))) ?: $this->type;
    }

    public function lienDossier(): ?string
    {
        $route = self::DOSSIERS[$this->dossier_type][1] ?? null;
        if (!$route || !$this->dossier_id || !\Illuminate\Support\Facades\Route::has($route)) {
            return null;
        }

        return route($route, $this->dossier_id);
    }
}
