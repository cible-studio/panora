<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail INTERNE du matin sur les renouvellements (2026-10-22) :
 *   · commercial : clients sans réponse à appeler (dès J-25), demandes de
 *     rappel, réponses reçues ;
 *   · MP : renouvellements à confirmer.
 * Cf. RenouvellementService::digest et la commande renouvellements:digest.
 */
class RenouvellementDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{email: string, nom: string, role: string, appeler: mixed, rappels: mixed, reponses: mixed, a_confirmer: mixed} $contenu */
    public function __construct(
        public readonly array $contenu,
        public readonly ?string $noteTest = null,
    ) {}

    public function envelope(): Envelope
    {
        $c = $this->contenu;
        $sujet = $c['role'] === 'mediaplanner'
            ? "Renouvellements à confirmer : {$c['a_confirmer']->count()}"
            : 'Renouvellements du jour : '
                . collect([
                    $c['appeler']->count() ? $c['appeler']->count() . ' à appeler' : null,
                    $c['rappels']->count() ? $c['rappels']->count() . ' rappel(s) demandé(s)' : null,
                    $c['reponses']->count() ? $c['reponses']->count() . ' réponse(s)' : null,
                ])->filter()->implode(' · ');

        return new Envelope(
            subject: ($this->noteTest ? '[TEST] ' : '') . $sujet,
            tags: ['renouvellement-equipe'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.renouvellement-digest',
            with: $this->contenu + [
                'noteTest' => $this->noteTest,
                'lien'     => route('admin.renouvellements.index'),
            ],
        );
    }
}
