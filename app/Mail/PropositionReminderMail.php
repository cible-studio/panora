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
 * Rappel de proposition envoyé au client à J+2 et J+5 si la proposition
 * n'a pas été traitée. Déclenché par la commande artisan
 * `propositions:send-reminders` (cron daily 09:00).
 *
 * `reminderStep` = 2 ou 5 — utilisé pour personnaliser le ton :
 *   - J+2 : informatif ("vous n'avez pas encore validé")
 *   - J+5 : pressant (J-2 avant expiration)
 */
class PropositionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly int         $reminderStep, // 2 ou 5
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->reservation->reference;

        return new Envelope(
            subject:  "Rappel — votre proposition {$ref} expire bientôt",
            tags:     ['proposition', 'reminder', "j{$this->reminderStep}"],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'client_id'      => (string) ($this->reservation->client_id ?? ''),
                'step'           => (string) $this->reminderStep,
            ],
        );
    }

    public function content(): Content
    {
        $this->reservation->loadMissing([
            'client', 'user',
            'panels', 'externalPanels',
        ]);

        // Lien public — rétrocompat token si pas de slug.
        $lien = $this->reservation->proposition_slug
            ? route('proposition.show', [
                $this->reservation->reference,
                $this->reservation->proposition_slug,
            ])
            : route('proposition.show.legacy', $this->reservation->proposition_token);

        $expiresAt   = $this->reservation->proposition_expires_at;
        $now         = now();
        $hoursLeft   = $expiresAt ? max(0, (int) $now->diffInHours($expiresAt, false)) : null;
        $daysLeft    = $hoursLeft !== null ? (int) ceil($hoursLeft / 24) : null;
        $isUrgent    = $this->reminderStep >= 5 || ($daysLeft !== null && $daysLeft <= 2);

        return new Content(
            view: 'emails.proposition-reminder',
            text: 'emails.plain.proposition-reminder',
            with: [
                'reservation'  => $this->reservation,
                'client'       => $this->reservation->client,
                'commercial'   => $this->reservation->user,
                'lien'         => $lien,
                'expiresAt'    => $expiresAt,
                'reminderStep' => $this->reminderStep,
                'daysLeft'     => $daysLeft,
                'isUrgent'     => $isUrgent,
            ],
        );
    }
}
