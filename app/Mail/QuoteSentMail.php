<?php

namespace App\Mail;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé au client à l'émission d'un devis.
 *
 * Contient :
 *   - Un message d'accroche personnalisé (nom commercial + client)
 *   - Le PDF du devis en pièce jointe
 *   - Un lien direct :
 *     • vers /client/devis/{id} si le client a un compte
 *     • vers /devis/{token} sinon (lien public)
 */
class QuoteSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function build()
    {
        $this->quote->loadMissing(['client', 'commercial', 'lines', 'services']);

        // Génération du PDF en pièce jointe (mêmes règles que le download)
        $pdf = Pdf::loadView('pdf.quote', ['quote' => $this->quote])->setPaper('A4', 'portrait');

        $filename = 'Devis-' . $this->quote->reference . '.pdf';
        $subject  = 'Devis CIBLE · ' . $this->quote->title . ' (' . $this->quote->reference . ')';

        // Lien : public si le client n'a pas de compte, authentifié sinon.
        $hasClientAccount = \App\Models\ClientUser::where('client_id', $this->quote->client_id)
            ->where('is_active', true)->exists();
        $consultUrl = $hasClientAccount
            ? url('/client/devis/' . $this->quote->id)   // demandera un login
            : $this->quote->publicUrl();

        return $this->subject($subject)
            ->view('emails.quotes.sent')
            ->with([
                'quote'      => $this->quote,
                'consultUrl' => $consultUrl,
            ])
            ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf'])
            ->replyTo($this->quote->commercial?->email ?: config('mail.from.address'));
    }
}
