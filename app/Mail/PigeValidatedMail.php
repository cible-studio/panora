<?php

namespace App\Mail;

use App\Models\Panel;
use App\Models\Pige;
use App\Models\PublicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Notifie le client qu'une pige photo a été validée et est consultable.
 * Si le client a un compte → lien espace client.
 * Sinon → lien public sécurisé via PublicLink.
 *
 * Axe (2026-11-12, décision du 12/11) : UN e-mail pour tout l'axe, envoyé
 * au dernier pied validé, avec la photo de chaque pied (`$photosAxe`) et un
 * lien qui les montre toutes. Décidé par App\Services\Piges\AvisPigeValidee.
 */
class PigeValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Pige $pige,
        public readonly PublicLink $link,
        public readonly ?Collection $photosAxe = null,
        public readonly ?Panel $axe = null,
    ) {}

    public function envelope(): Envelope
    {
        $campaignName = $this->pige->campaign?->name ?? 'votre campagne';
        return new Envelope(
            subject: $this->axe
                ? "✅ Photos de l'axe {$this->axe->reference} disponibles — {$campaignName}"
                : "✅ Pige photo disponible — {$campaignName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pige-validated',
            with: [
                'pige'      => $this->pige,
                'link'      => $this->link,
                'url'       => $this->link->publicUrl(),
                'client'    => $this->pige->campaign?->client,
                'panel'     => $this->pige->panel,
                'campaign'  => $this->pige->campaign,
                'axe'       => $this->axe,
                'photosAxe' => $this->photosAxe ?? collect(),
            ],
        );
    }
}
