<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification email envoyée au commercial quand le MP lui soumet une
 * proposition pour envoi au client. Doublé d'une alerte interne ciblée
 * (cf. PropositionController::submitProposition).
 *
 * Reply-To = MP qui a soumis (le commercial peut lui répondre direct
 * pour demander une modif avant envoi).
 *
 * ⚠️ Volontairement PAS de ShouldQueue : envoi immédiat synchrone
 *    requis (sinon avec QUEUE_CONNECTION=database sans worker, mail
 *    bloqué indéfiniment dans la table jobs).
 */
class PropositionAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly User $commercial,
        public readonly User $submittedBy,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        if ($this->submittedBy->email) {
            $replyTo[] = new Address($this->submittedBy->email, $this->submittedBy->name ?? '');
        }

        return new Envelope(
            subject:  "Proposition à envoyer — {$this->reservation->reference} ({$this->reservation->client?->name})",
            replyTo:  $replyTo,
            tags:     ['proposition', 'assigned'],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'commercial_id'  => (string) $this->commercial->id,
            ],
        );
    }

    public function content(): Content
    {
        $this->reservation->loadMissing(['client', 'panels', 'externalPanels']);

        return new Content(
            view: 'emails.proposition-assigned',
            text: 'emails.plain.proposition-assigned',
            with: [
                'reservation' => $this->reservation,
                'client'      => $this->reservation->client,
                'commercial'  => $this->commercial,
                'submittedBy' => $this->submittedBy,
                'showUrl'     => route('admin.reservations.show', $this->reservation),
                'panelCount'  => $this->reservation->panels->count() + $this->reservation->externalPanels->count(),
            ],
        );
    }
}
