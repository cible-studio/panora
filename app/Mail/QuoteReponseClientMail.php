<?php

namespace App\Mail;

use App\Mail\Concerns\MailCommercial;
use App\Models\Quote;
use App\Services\QuoteDecisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail INTERNE (2026-11-23) : le client a répondu à un devis — acceptation,
 * refus ou demande de modification, depuis son espace ou le lien privé du
 * mail. Destinataire : le commercial du devis (cf. QuoteDecisionService).
 *
 * Copie commerciale (MailCommercial) : même règle que la décision du
 * client sur une proposition (PropositionDecisionMail), qui la porte déjà.
 * Répondre au mail écrit au client.
 */
class QuoteReponseClientMail extends Mailable implements MailCommercial
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly string $reponse,
        public readonly ?string $messageClient,
        public readonly string $canal,
        public readonly ?string $suite = null,
    ) {}

    public function envelope(): Envelope
    {
        $client = $this->quote->client?->name ?: 'Le client';
        $quoi   = match ($this->reponse) {
            QuoteDecisionService::ACCEPTATION  => 'a accepté',
            QuoteDecisionService::REFUS        => 'a refusé',
            QuoteDecisionService::MODIFICATION => 'demande une modification',
            default                            => 'a répondu',
        };

        // Répondre au mail = écrire au client (première adresse du devis).
        $replyTo = collect($this->quote->adressesClient())
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->take(1)
            ->map(fn ($e) => new Address($e, $this->quote->client?->name ?? ''))
            ->all();

        return new Envelope(
            subject: "Devis {$this->quote->reference} : {$client} {$quoi}",
            replyTo: $replyTo,
            tags: ['devis', 'reponse-client', $this->reponse],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotes.reponse-client',
            with: [
                'quote'   => $this->quote,
                'client'  => $this->quote->client,
                'reponse' => $this->reponse,
                'libelle' => QuoteDecisionService::libelle($this->reponse),
                // Pas « canal » : les propriétés publiques du mail écrasent
                // les variables de même nom dans la vue.
                'canalLibelle' => QuoteDecisionService::libelleCanal($this->canal),
                // « message » est réservé par Laravel dans les vues de mail.
                'messageClient' => $this->messageClient,
                'suite'   => $this->suite,
                'lien'    => route('admin.quotes.show', $this->quote->id),
                'repondreAuClient' => $this->quote->adressesClient() !== [],
            ],
        );
    }
}
