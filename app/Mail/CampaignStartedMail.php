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
 * Mail envoyé au client à l'activation (PLANIFIE → ACTIF) OU à la
 * création directe d'une campagne. Le template adapte le contenu
 * selon que la campagne démarre maintenant ou est planifiée (futur).
 *
 * Reply-To : commercial qui suit le dossier (via résa source si présente,
 * sinon créateur campagne). Évite d'exposer le MP au client.
 *
 * ⚠️ Pas de ShouldQueue : envoi synchrone fiable même sans worker
 *    queue:work (QUEUE_CONNECTION=database sinon mails bloqués).
 */
class CampaignStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
    ) {}

    public function envelope(): Envelope
    {
        // Reply-To = commercial assigné via la résa source (priorité),
        // sinon créateur de la campagne. Cohérent avec les autres mails
        // client (proposition, espace client).
        $replyTo = [];
        $contact = $this->campaign->reservation?->resolveCommercialContact()
                ?? $this->campaign->user;
        if ($contact?->email) {
            $replyTo[] = new Address($contact->email, $contact->name ?? '');
        }

        // Subject adapté : "démarre" vs "planifiée pour le X"
        $isFuture = $this->campaign->start_date
            && $this->campaign->start_date->isFuture();
        $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
        $subject  = $isFuture
            ? "Votre campagne {$this->campaign->name} est planifiée — PANORA · {$operator}"
            : "Démarrage de votre campagne {$this->campaign->name} — PANORA · {$operator}";

        return new Envelope(
            subject:  $subject,
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

        $isFuture = $this->campaign->start_date
            && $this->campaign->start_date->isFuture();

        return new Content(
            view: 'emails.campaign-started',
            with: [
                'campaign'    => $this->campaign,
                'client'      => $this->campaign->client,
                'totalPanels' => $totalPanels,
                'isFuture'    => $isFuture,
                'contact'     => $this->campaign->reservation?->resolveCommercialContact()
                                ?? $this->campaign->user,
            ],
        );
    }
}
