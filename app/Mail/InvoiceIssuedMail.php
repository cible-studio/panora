<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\PublicLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly PublicLink $link,
        public readonly bool $isReminder = false,
        public readonly ?int $reminderNumber = null,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->invoice->reference ?? '#'.$this->invoice->id;
        $subject = $this->isReminder
            ? "🔔 Rappel " . ($this->reminderNumber ?? 1) . " — Facture {$ref} impayée"
            : "📄 Votre facture {$ref} — CIBLE CI";
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-issued',
            with: [
                'invoice' => $this->invoice,
                'link'    => $this->link,
                'url'     => $this->link->publicUrl(),
                'client'  => $this->invoice->client,
                'isReminder' => $this->isReminder,
                'reminderNumber' => $this->reminderNumber,
            ],
        );
    }
}
