<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceSchedule;
use App\Models\PublicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rappel courtois envoyé au client avant qu'une échéance de paiement
 * n'arrive à terme (cf. mission B — capture user).
 *
 * Typiquement déclenché à J-7 par le job finance:check-due-dates.
 * Idempotence : la commande positionne reminded_at sur l'échéance pour
 * éviter le double-envoi (cf. CheckDueDates::touchReminder).
 */
class InvoiceScheduleReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly InvoiceSchedule $schedule,
        public readonly PublicLink $link,
        public readonly int $daysUntilDue,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->invoice->reference ?? '#'.$this->invoice->id;
        $label = $this->schedule->label ?? 'Échéance';
        $subject = $this->daysUntilDue > 0
            ? "📅 Rappel — {$label} de la facture {$ref} dans {$this->daysUntilDue} jour" . ($this->daysUntilDue > 1 ? 's' : '')
            : "⚠️ Rappel — {$label} de la facture {$ref} arrive à échéance aujourd'hui";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedule-reminder',
            with: [
                'invoice'        => $this->invoice,
                'schedule'       => $this->schedule,
                'link'           => $this->link,
                'url'            => $this->link->publicUrl(),
                'client'         => $this->invoice->client,
                'daysUntilDue'   => $this->daysUntilDue,
            ],
        );
    }
}
