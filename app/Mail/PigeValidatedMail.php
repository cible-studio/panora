<?php

namespace App\Mail;

use App\Models\Pige;
use App\Models\PublicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifie le client qu'une pige photo a été validée et est consultable.
 * Si le client a un compte → lien espace client.
 * Sinon → lien public sécurisé via PublicLink.
 */
class PigeValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Pige $pige,
        public readonly PublicLink $link,
    ) {}

    public function envelope(): Envelope
    {
        $campaignName = $this->pige->campaign?->name ?? 'votre campagne';
        return new Envelope(
            subject: "✅ Pige photo disponible — {$campaignName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pige-validated',
            with: [
                'pige'    => $this->pige,
                'link'    => $this->link,
                'url'     => $this->link->publicUrl(),
                'client'  => $this->pige->campaign?->client,
                'panel'   => $this->pige->panel,
                'campaign'=> $this->pige->campaign,
            ],
        );
    }
}
