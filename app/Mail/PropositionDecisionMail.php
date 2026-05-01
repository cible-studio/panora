<?php
namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé au commercial / admin qui a créé une proposition lorsque
 * le client la valide ou la refuse depuis l'espace public.
 *
 * Destinataire : $reservation->user (le créateur de la proposition).
 * Si pas d'utilisateur lié, fallback sur tous les admins (à gérer côté caller).
 */
class PropositionDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const DECISION_ACCEPTED = 'accepted';
    public const DECISION_REFUSED  = 'refused';

    public function __construct(
        public readonly Reservation $reservation,
        public readonly string      $decision,    // 'accepted' | 'refused'
        public readonly ?string     $reason = null, // motif de refus éventuel
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->reservation->client?->name ?? 'le client';
        $ref        = $this->reservation->reference;

        // Subjects sobres et descriptifs (anti-spam)
        $subject = $this->decision === self::DECISION_ACCEPTED
            ? "Proposition {$ref} acceptée par {$clientName}"
            : "Proposition {$ref} refusée par {$clientName}";

        return new Envelope(
            subject:  $subject,
            tags:     ['proposition', 'decision', $this->decision],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'client_id'      => (string) ($this->reservation->client_id ?? ''),
                'decision'       => $this->decision,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proposition-decision',
            text: 'emails.plain.proposition-decision',  // Version texte (anti-spam)
            with: [
                'reservation' => $this->reservation,
                'client'      => $this->reservation->client,
                'decision'    => $this->decision,
                'reason'      => $this->reason,
                'isAccepted'  => $this->decision === self::DECISION_ACCEPTED,
                'showLink'    => route('admin.reservations.show', $this->reservation),
            ],
        );
    }
}
