<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Lot 12.3 — Notification au commercial qu'on lui a assigné une réservation.
 *
 * Destinataire : le commercial désigné via reservations.commercial_user_id.
 * Expéditeur : noreply@ (config par défaut).
 * Reply-To : l'auteur de l'assignation (admin/mp) pour que le commercial
 *            puisse poser une question directement.
 */
class ReservationAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly User        $assignedBy,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        if ($this->assignedBy->email) {
            $replyTo[] = new Address($this->assignedBy->email, $this->assignedBy->name ?? '');
        }

        return new Envelope(
            subject:  "Réservation assignée — {$this->reservation->reference}",
            replyTo:  $replyTo,
            tags:     ['reservation', 'assigned'],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'assigned_to'    => (string) $this->reservation->commercial_user_id,
            ],
        );
    }

    public function content(): Content
    {
        $this->reservation->loadMissing(['client', 'panels', 'externalPanels']);

        $totalPanels = $this->reservation->panels->count() + $this->reservation->externalPanels->count();

        return new Content(
            view: 'emails.reservation-assigned',
            with: [
                'reservation' => $this->reservation,
                'client'      => $this->reservation->client,
                'assignedBy'  => $this->assignedBy,
                'totalPanels' => $totalPanels,
                'showLink'    => route('admin.reservations.show', $this->reservation),
            ],
        );
    }
}
