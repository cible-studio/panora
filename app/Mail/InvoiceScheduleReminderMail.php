<?php

namespace App\Mail;

use App\Mail\Concerns\MailCommercial;
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
 * Déclenché à J-7 par invoices:alerts (2026-11-26, système unique).
 * Idempotence : une ligne invoice_alerts par étape et par canal.
 * Sans échéancier, $schedule est l'échéance par défaut (non enregistrée) :
 * « Solde », reste à payer, date d'émission + 30 jours.
 */
class InvoiceScheduleReminderMail extends Mailable implements MailCommercial
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
