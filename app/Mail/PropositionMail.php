<?php
// ══════════════════════════════════════════════════════════════
// app/Mail/PropositionMail.php
// ══════════════════════════════════════════════════════════════

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de proposition commerciale envoyé au client.
 *
 * Implements ShouldQueue : si un worker queue tourne en prod, l'envoi devient
 * asynchrone (l'admin n'attend pas le SMTP). Si pas de worker, le driver
 * `sync` envoie immédiatement (config par défaut Laravel).
 */
class PropositionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->reservation->client?->name ?? 'client';
        // Subject sobre — pas d'emoji, pas de majuscules agressives, pas de "!"
        // (réduit le score spam Gmail / SpamAssassin)
        return new Envelope(
            subject:  "Proposition commerciale CIBLE CI - Réf. {$this->reservation->reference}",
            tags:     ['proposition', 'commercial'],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'client_id'      => (string) ($this->reservation->client_id ?? ''),
            ],
        );
    }

    public function content(): Content
    {
        // ── Génération SÛRE du lien public ────────────────────────────
        // La route 'proposition.show' attend {reference}/{slug}.
        // Si le slug n'a pas été généré, on tombe sur la route legacy {token}
        // qui redirige vers la nouvelle URL — on garde la rétrocompat.
        $lien = $this->reservation->proposition_slug
            ? route('proposition.show', [
                $this->reservation->reference,
                $this->reservation->proposition_slug,
            ])
            : route('proposition.show.legacy', $this->reservation->proposition_token);

        return new Content(
            view: 'admin.emails.proposition',
            text: 'emails.plain.proposition',     // Version texte (anti-spam)
            with: [
                'reservation' => $this->reservation,
                'client'      => $this->reservation->client,
                'panels'      => $this->reservation->panels,
                'lien'        => $lien,
                'expiresAt'   => $this->reservation->proposition_expires_at,
                'sentAt'      => $this->reservation->proposition_sent_at ?? now(),
            ],
        );
    }
}
