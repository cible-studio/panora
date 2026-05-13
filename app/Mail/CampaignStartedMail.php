<?php
namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé au client au démarrage effectif de sa campagne (transition
 * PLANIFIE → ACTIF). Annonce les panneaux, la période et le montant.
 *
 * Pas envoyé à la création de la fiche : on attend que la campagne ait
 * au moins 1 panneau et soit activée — pour éviter le mail vide « votre
 * campagne est créée » avec aucune information utile.
 */
class CampaignStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        $contact = $this->campaign->user;
        if ($contact?->email) {
            $replyTo[] = new Address($contact->email, $contact->name ?? '');
        }

        return new Envelope(
            subject:  "Démarrage de votre campagne {$this->campaign->name} — CIBLE CI",
            replyTo:  $replyTo,
            tags:     ['campaign', 'started'],
            metadata: [
                'campaign_id' => (string) $this->campaign->id,
                'client_id'   => (string) $this->campaign->client_id,
            ],
        );
    }

    public function content(): Content
    {
        $totalPanels = $this->campaign->panels()->count()
            + $this->campaign->externalPanels()->count();

        return new Content(
            view: 'emails.campaign-started',
            with: [
                'campaign'    => $this->campaign,
                'client'      => $this->campaign->client,
                'totalPanels' => $totalPanels,
            ],
        );
    }
}
