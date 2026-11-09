<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Listes de campagnes sans interlocuteur, un fichier Excel par commercial
 * (2026-11-09). Envoyé à la personne qui lance la commande
 * campagnes:sans-interlocuteur, qui transfère chaque fichier.
 */
class InterlocuteursAFournirMail extends Mailable
{
    /** @param array<string, string> $fichiers  nom du fichier => contenu Excel */
    public function __construct(
        public array $fichiers,
        public int $nbCampagnes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Campagnes sans interlocuteur : {$this->nbCampagnes} à renseigner");
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Bonjour,</p>'
            . '<p>Voici les campagnes sans interlocuteur, avec un fichier par commercial : '
            . e((string) $this->nbCampagnes) . ' campagne(s) au total.</p>'
            . '<p>Chaque commercial remplit les colonnes jaunes : la personne qui suit la campagne chez le client '
            . 'et peut répondre sur son renouvellement, sa fonction, son mail direct et son téléphone. '
            . 'Les échéances les plus proches sont en haut.</p>');
    }

    public function attachments(): array
    {
        return collect($this->fichiers)
            ->map(fn (string $contenu, string $nom) => Attachment::fromData(fn () => $contenu, $nom)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->values()
            ->all();
    }
}
