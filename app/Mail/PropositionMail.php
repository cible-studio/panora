<?php
// ══════════════════════════════════════════════════════════════
// app/Mail/PropositionMail.php
// ══════════════════════════════════════════════════════════════

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropositionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->reservation->client?->name ?? 'Client';
        $panelCount = $this->reservation->panels->count();
        $period     = $this->reservation->start_date->format('d/m/Y') .
                      ' → ' .
                      $this->reservation->end_date->format('d/m/Y');

        return new Envelope(
            subject: "📋 Proposition commerciale — {$panelCount} panneau(x) · {$period} — CIBLE CI",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'admin.emails.proposition',
            with: [
                'reservation' => $this->reservation,
                'client'      => $this->reservation->client,
                'panels'      => $this->reservation->panels,
                'lien'        => route('proposition.show', $this->reservation->proposition_token),
                'expiresAt'   => $this->reservation->proposition_expires_at,
                'sentAt'      => $this->reservation->proposition_sent_at ?? now(),
            ],
        );
    }
}