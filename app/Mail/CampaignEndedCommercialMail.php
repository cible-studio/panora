<?php

namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail J0 (informatif) au commercial assigné à la campagne, envoyé dès
 * que le status passe à `termine`.
 *
 * Objectif : informer le commercial que la campagne est terminée pour
 * qu'il prépare son mail de suivi client (satisfaction, upsell, suite
 * commerciale). Le commercial rédige lui-même — pas de template auto.
 *
 * Envoi : dispatch via SendCampaignEndedCommercialMail depuis
 * CampaignObserver::updated quand status devient 'termine'.
 */
class CampaignEndedCommercialMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->campaign->client?->name ?? '';
        $subject = "✅ Campagne terminée — « {$this->campaign->name} »"
                 . ($clientName ? " · {$clientName}" : '');

        return new Envelope(
            subject:  $subject,
            tags:     ['campaign', 'ended-commercial'],
            metadata: [
                'campaign_id' => (string) $this->campaign->id,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-ended-commercial',
            with: [
                'campaign' => $this->campaign,
                'client'   => $this->campaign->client,
            ],
        );
    }
}
