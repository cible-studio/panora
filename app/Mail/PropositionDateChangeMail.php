<?php
namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé sur les évènements liés à la demande de décalage de
 * dates initiée par le client depuis sa page de proposition :
 *
 *   - requested : nouvelle demande arrive → mail admin/commercial
 *                 (récap des 2 périodes + note + CTA fiche réservation).
 *   - accepted  : l'admin accepte → mail client (nouvelle période valide
 *                 + CTA proposition réactivée).
 *   - refused   : l'admin refuse → mail client (raison optionnelle +
 *                 CTA proposition sur dates initiales).
 *
 * Un seul template polymorphique = uniformité visuelle garantie.
 */
class PropositionDateChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CONTEXT_REQUESTED = 'requested';
    public const CONTEXT_ACCEPTED  = 'accepted';
    public const CONTEXT_REFUSED   = 'refused';

    public function __construct(
        public readonly Reservation $reservation,
        public readonly string      $context,
        public readonly ?string     $reason = null,
        // Pour le contexte 'requested' on a besoin des dates demandées
        // AVANT que l'admin ne les valide (donc encore présentes dans
        // requested_*). Pour 'accepted' / 'refused' elles ont déjà été
        // appliquées ou effacées → on les passe en paramètre.
        public readonly ?string     $oldPeriod = null,
        public readonly ?string     $newPeriod = null,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->reservation->reference;
        $subject = match ($this->context) {
            self::CONTEXT_REQUESTED => "🗓 Demande de décalage — proposition {$ref}",
            self::CONTEXT_ACCEPTED  => "✅ Décalage accepté — proposition {$ref}",
            self::CONTEXT_REFUSED   => "🗓 Demande de décalage — proposition {$ref}",
            default                 => "Proposition {$ref} — mise à jour",
        };

        return new Envelope(
            subject:  $subject,
            tags:     ['proposition', 'date-change', $this->context],
            metadata: ['reservation_id' => (string) $this->reservation->id],
        );
    }

    public function content(): Content
    {
        $propositionUrl = $this->reservation->proposition_slug
            ? route('proposition.show', [$this->reservation->reference, $this->reservation->proposition_slug])
            : null;

        $reservationUrl = route('admin.reservations.show', $this->reservation);

        return new Content(
            view: 'emails.proposition-date-change',
            with: [
                'reservation'    => $this->reservation,
                'client'         => $this->reservation->client,
                'context'        => $this->context,
                'reason'         => $this->reason,
                'oldPeriod'      => $this->oldPeriod,
                'newPeriod'      => $this->newPeriod,
                'propositionUrl' => $propositionUrl,
                'reservationUrl' => $reservationUrl,
            ],
        );
    }
}
