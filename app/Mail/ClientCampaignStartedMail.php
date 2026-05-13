<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé au CLIENT après qu'il a accepté une proposition.
 *
 * Contenu :
 *   - Confirmation de l'acceptation
 *   - Récap campagne (dates, nb panneaux, montant)
 *   - CTA "Suivre ma campagne" → espace client (si compte) OU invitation
 *     à demander un compte (si pas de password set).
 *
 * Reply-To = commercial qui suit le dossier (resolveCommercialContact).
 *
 * ⚠️ Pas de ShouldQueue : envoi synchrone fiable même sans worker.
 */
class ClientCampaignStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly ?Campaign   $campaign = null,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->reservation->reference;

        // Reply-To = commercial suivant le dossier
        $replyTo = [];
        $contact = $this->reservation->resolveCommercialContact();
        if ($contact?->email) {
            $replyTo[] = new Address($contact->email, $contact->name ?? '');
        }

        return new Envelope(
            subject:  "🎉 Votre campagne {$ref} est confirmée — Suivez-la en temps réel",
            replyTo:  $replyTo,
            tags:     ['client', 'campaign-started'],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'campaign_id'    => (string) ($this->campaign?->id ?? ''),
                'client_id'      => (string) ($this->reservation->client_id ?? ''),
            ],
        );
    }

    public function content(): Content
    {
        $this->reservation->loadMissing(['client', 'panels', 'externalPanels']);
        $client      = $this->reservation->client;
        $hasAccount  = $client && !empty($client->password);
        $contact     = $this->reservation->resolveCommercialContact();

        $totalPanels = $this->reservation->panels->count()
                     + $this->reservation->externalPanels->count();

        // Lien : si compte existant → login, sinon page de contact pour
        // demander la création (le commercial créera l'accès).
        $loginUrl   = route('client.login');
        $contactUrl = route('client.login') . '?contact=1';

        return new Content(
            view: 'emails.client-campaign-started',
            text: 'emails.plain.client-campaign-started',
            with: [
                'reservation'  => $this->reservation,
                'campaign'     => $this->campaign,
                'client'       => $client,
                'contact'      => $contact,
                'hasAccount'   => $hasAccount,
                'totalPanels'  => $totalPanels,
                'loginUrl'     => $loginUrl,
                'contactUrl'   => $contactUrl,
            ],
        );
    }
}
