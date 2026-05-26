<?php

namespace App\Mail;

use App\Models\ClientMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification envoyée à la mailbox de l'équipe quand un client
 * envoie un message via le formulaire « Contacter la régie » de
 * l'espace client.
 *
 * Reply-To est positionné sur l'email du client pour qu'un
 * « Répondre » direct depuis Outlook/Gmail aille au bon endroit
 * (bien que le canal préféré reste l'interface admin
 * /admin/messages qui trace la réponse en BD).
 */
/**
 * NOTE : pas de ShouldQueue — aucun worker queue:work en prod, l'envoi
 * synchrone évite l'accumulation de jobs orphelins dans la table `jobs`.
 */
class ClientContactReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ClientMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  "[Espace Client] {$this->message->subject}",
            replyTo:  [$this->message->from_email => $this->message->from_name],
            tags:     ['client-contact', 'received'],
            metadata: ['client_message_id' => (string) $this->message->id],
        );
    }

    public function content(): Content
    {
        // ⚠ La clé `message` est RÉSERVÉE par Laravel (injecte l'objet
        // Illuminate\Mail\Message dans la vue). On expose donc notre
        // ClientMessage sous le nom `cm` pour éviter le shadow conflict.
        return new Content(
            view: 'emails.client-contact-received',
            text: 'emails.plain.client-contact-received',
            with: [
                'cm'      => $this->message,
                'showUrl' => route('admin.messages.show', $this->message),
            ],
        );
    }
}
