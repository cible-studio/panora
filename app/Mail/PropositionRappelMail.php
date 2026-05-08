<?php
namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropositionRappelMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly int         $jourRappel, // 2 ou 5
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->reservation->reference;

        return new Envelope(
            subject:  "Rappel — Votre proposition {$ref} expire bientôt",
            tags:     ['proposition', 'rappel'],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'client_id'      => (string) ($this->reservation->client_id ?? ''),
                'jour_rappel'    => (string) $this->jourRappel,
            ],
        );
    }

    public function content(): Content
    {
        $lien = $this->reservation->proposition_slug
            ? route('proposition.show', [
                $this->reservation->reference,
                $this->reservation->proposition_slug,
            ])
            : route('proposition.show.legacy', $this->reservation->proposition_token);

        return new Content(
            view: 'emails.proposition-rappel',
            text: 'emails.plain.proposition-rappel',
            with: [
                'reservation' => $this->reservation,
                'client'      => $this->reservation->client,
                'lien'        => $lien,
                'expiresAt'   => $this->reservation->proposition_expires_at,
                'jourRappel'  => $this->jourRappel,
            ],
        );
    }
}
