<?php

namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignEndingSoonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "📅 {$this->campaign->name} se termine dans {$this->daysRemaining} jours",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-ending-soon',
            with: [
                'campaign'      => $this->campaign,
                'client'        => $this->campaign->client,
                'daysRemaining' => $this->daysRemaining,
            ],
        );
    }
}
