<?php

namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail J-3 (préventif) au commercial assigné à la campagne.
 *
 * Contexte : le commercial doit être averti quelques jours avant la fin
 * pour préparer le suivi post-campagne (satisfaction client, upsell).
 * PAS de template de mail client auto — le commercial rédige lui-même
 * son message au client depuis sa propre boîte mail.
 *
 * Envoi : NotifyEndingCommercial command, schedule quotidien 09h.
 */
class CampaignEndingCommercialMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->campaign->client?->name ?? '';
        $subject = "⏰ J-{$this->daysRemaining} — Fin de campagne « {$this->campaign->name} »"
                 . ($clientName ? " · {$clientName}" : '');

        return new Envelope(
            subject:  $subject,
            tags:     ['campaign', 'ending-commercial'],
            metadata: [
                'campaign_id'    => (string) $this->campaign->id,
                'days_remaining' => (string) $this->daysRemaining,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-ending-commercial',
            with: [
                'campaign'      => $this->campaign,
                'client'        => $this->campaign->client,
                'daysRemaining' => $this->daysRemaining,
            ],
        );
    }
}
