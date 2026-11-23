<?php

namespace App\Mail;

use App\Mail\Concerns\MailCommercial;
use App\Models\Quote;
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
class QuoteSentMail extends Mailable implements MailCommercial
{
    use Queueable, SerializesModels;

    /**
     * @param string|null $nomPieceJointe  nom du PDF choisi par le commercial
     *                                     dans la fenêtre d'envoi (2026-11-23)
     */
    public function __construct(public Quote $quote, public ?string $nomPieceJointe = null) {}

    public function build()
    {
        $this->quote->loadMissing(['client', 'commercial', 'lines', 'services']);

        // Génération du PDF en pièce jointe : le même rendu que le
        // téléchargement, par le service commun (2026-11-19).
        $pdf = app(\App\Services\QuotePdf::class)->pdf($this->quote);

        $filename = \App\Services\QuotePdf::nomPieceJointe($this->quote, $this->nomPieceJointe);
        $subject  = 'Devis CIBLE · ' . $this->quote->title . ' (' . $this->quote->reference . ')';

        // Lien du bouton (2026-11-23) : TOUJOURS le lien privé et sécurisé du
        // devis (jeton de 64 caractères), qui permet d'accepter, refuser ou
        // demander une modification sans se connecter. Avant, un client avec
        // un compte recevait l'adresse de l'espace client, qui demande une
        // connexion : l'interlocuteur qui reçoit le mail n'est pas forcément
        // celui qui a le compte. Le compte est mentionné à côté du bouton.
        // Espace client : règle unique Client::hasAccount() (2026-11-23) —
        // la même que la connexion. Avant : un membre d'équipe actif, donc
        // un client avec son propre accès mais sans équipe lisait « demandez
        // l'ouverture de votre espace ».
        $hasClientAccount = (bool) $this->quote->client?->hasAccount();
        $consultUrl = $this->quote->publicUrl();

        return $this->subject($subject)
            ->view('emails.quotes.sent')
            ->with([
                'quote'            => $this->quote,
                'consultUrl'       => $consultUrl,
                'hasClientAccount' => $hasClientAccount,
                'espaceClientUrl'  => url('/client/devis/' . $this->quote->id),
            ])
            ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf'])
            ->replyTo($this->quote->commercial?->email ?: config('mail.from.address'));
    }
}
