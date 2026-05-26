<?php

namespace App\Mail;

use App\Models\ClientMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Réponse envoyée au client suite à un message « Contacter la régie ».
 * Déclenché depuis /admin/messages/{id} par l'opérateur qui clique
 * sur « Envoyer la réponse ». Le body de réponse est aussi stocké
 * en BD pour garder l'historique côté admin.
 */
class ClientContactReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ClientMessage $message,
        public readonly string        $replyBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  'Re: ' . $this->message->subject,
            tags:     ['client-contact', 'reply'],
            metadata: ['client_message_id' => (string) $this->message->id],
        );
    }

    public function content(): Content
    {
        // ⚠ La clé `message` est RÉSERVÉE par Laravel (injecte
        // Illuminate\Mail\Message). On utilise `cm` pour notre ClientMessage.
        return new Content(
            view: 'emails.client-contact-reply',
            text: 'emails.plain.client-contact-reply',
            with: [
                'cm'        => $this->message,
                'replyBody' => $this->replyBody,
                'operator'  => config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')),
            ],
        );
    }
}
