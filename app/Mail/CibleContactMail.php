<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mail de demande de devis reçue via /cible/contact.
 * Envoyé à commercial@cible-ci.com (config mail.cible_devis_to).
 */
class CibleContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $subj = 'Demande devis — ' . ($this->data['entreprise'] ?? '?') . ' (' . ($this->data['nom'] ?? '?') . ')';
        return $this->subject($subj)
                    ->view('emails.cible.devis', ['d' => $this->data])
                    ->replyTo($this->data['email'] ?? config('mail.from.address'));
    }
}
