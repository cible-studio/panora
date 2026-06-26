<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\PublicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifie le client que tous les panneaux de sa campagne ont été décapés
 * (campagne terminée + visuel retiré du terrain).
 */
class CampaignDecappedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly PublicLink $link,
        public readonly int $decappedCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Campagne {$this->campaign->name} — décapage terminé",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-decapped',
            with: [
                'campaign'      => $this->campaign,
                'link'          => $this->link,
                'url'           => $this->link->publicUrl(),
                'client'        => $this->campaign->client,
                'decappedCount' => $this->decappedCount,
            ],
        );
    }
}
