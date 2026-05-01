<?php
namespace App\Mail;

use App\Models\Campaign;
use App\Models\SatisfactionSurvey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé immédiatement (J+0) au client à la fin de sa campagne.
 * Annonce la fin et inclut le lien vers le questionnaire de satisfaction.
 */
class CampaignEndedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign          $campaign,
        public readonly SatisfactionSurvey $survey
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  "Fin de votre campagne {$this->campaign->name} — CIBLE CI",
            tags:     ['campaign', 'ended'],
            metadata: [
                'campaign_id' => (string) $this->campaign->id,
                'client_id'   => (string) $this->campaign->client_id,
                'survey_id'   => (string) $this->survey->id,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-ended',
            text: 'emails.plain.campaign-ended',
            with: [
                'campaign' => $this->campaign,
                'client'   => $this->campaign->client,
                'survey'   => $this->survey,
                'lien'     => $this->survey->publicUrl(),
            ],
        );
    }
}
