<?php
namespace App\Mail;

use App\Models\SatisfactionSurvey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé au client 3 jours après la fin de sa campagne pour solliciter son
 * avis. Lien public sans auth pointant vers /satisfaction/{token}.
 */
class SatisfactionSurveyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SatisfactionSurvey $survey
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->survey->client?->name ?? '';
        return new Envelope(
            subject:  "Votre avis sur la campagne {$this->survey->campaign?->name} - CIBLE CI",
            tags:     ['satisfaction', 'survey'],
            metadata: [
                'survey_id'   => (string) $this->survey->id,
                'campaign_id' => (string) $this->survey->campaign_id,
                'client_id'   => (string) $this->survey->client_id,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.satisfaction-survey',
            text: 'emails.plain.satisfaction-survey',
            with: [
                'survey'    => $this->survey,
                'client'    => $this->survey->client,
                'campaign'  => $this->survey->campaign,
                'lien'      => $this->survey->publicUrl(),
            ],
        );
    }
}
