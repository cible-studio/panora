<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rappel trimestriel : tester la restauration d'une sauvegarde (2026-10-07).
 * Envoyé par la commande sauvegardes:rappel-restauration.
 */
class RappelTestRestaurationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, array{disque:string, joignable:bool, nombre:int, derniere:?string, taille_mo:?float}> $copies */
    public function __construct(public readonly array $copies) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🗓️ Rappel trimestriel — tester la restauration des sauvegardes Panora',
            tags: ['sauvegardes'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rappel-test-restauration',
            with: [
                'copies'    => $this->copies,
                'trimestre' => 'T' . now()->quarter . ' ' . now()->year,
                'echeance'  => now()->addDays(14)->translatedFormat('j F Y'),
            ],
        );
    }
}
