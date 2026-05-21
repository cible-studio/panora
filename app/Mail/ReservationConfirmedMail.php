<?php

namespace App\Mail;

use App\Models\PublicLink;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly PublicLink $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Votre réservation {$this->reservation->reference} est confirmée",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-confirmed',
            with: [
                'reservation' => $this->reservation,
                'link'        => $this->link,
                'url'         => $this->link->publicUrl(),
                'client'      => $this->reservation->client,
            ],
        );
    }
}
