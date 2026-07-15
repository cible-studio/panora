<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé à studio@cible-ci.com après une demande de démo Panora
 * depuis la landing publique /decouvrir.
 */
class DemoRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Panora] Nouvelle demande de démo — ' . ($this->payload['regie'] ?? 'régie inconnue'),
            replyTo: [$this->payload['email'] ?? null],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.landing.demo-request',
            with: ['p' => $this->payload],
        );
    }
}
