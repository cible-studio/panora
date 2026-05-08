<?php
namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé au commercial / admin qui a créé une proposition lorsque
 * le client la valide ou la refuse depuis l'espace public.
 *
 * Destinataire : $reservation->user (le créateur de la proposition).
 * Si pas d'utilisateur lié, fallback sur tous les admins (à gérer côté caller).
 */
class PropositionDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const DECISION_ACCEPTED = 'accepted';
    public const DECISION_REFUSED  = 'refused';

    public function __construct(
        public readonly Reservation $reservation,
        public readonly string      $decision,        // 'accepted' | 'refused'
        public readonly ?string     $reason = null,   // motif de refus éventuel
        public readonly ?string     $campaignName = null, // nom de la campagne créée à la confirmation
    ) {}

    public function envelope(): Envelope
    {
        $clientName = $this->reservation->client?->name ?? 'le client';
        $ref        = $this->reservation->reference;

        // Subjects sobres et descriptifs (anti-spam)
        $subject = $this->decision === self::DECISION_ACCEPTED
            ? "Proposition {$ref} acceptée par {$clientName}"
            : "Proposition {$ref} refusée par {$clientName}";

        return new Envelope(
            subject:  $subject,
            tags:     ['proposition', 'decision', $this->decision],
            metadata: [
                'reservation_id' => (string) $this->reservation->id,
                'client_id'      => (string) ($this->reservation->client_id ?? ''),
                'decision'       => $this->decision,
            ],
        );
    }

    public function content(): Content
    {
        // Eager-load tout ce que la vue va consommer (évite N+1 + assure
        // la dispo de campaign et de son lien direct).
        $this->reservation->loadMissing([
            'panels', 'externalPanels',
            'client', 'user',
            'campaign:id,reservation_id,name,start_date,end_date,status',
        ]);

        $isAccepted = $this->decision === self::DECISION_ACCEPTED;
        $campaign   = $this->reservation->campaign;

        return new Content(
            view: 'emails.proposition-decision',
            text: 'emails.plain.proposition-decision',  // Version texte (anti-spam)
            with: [
                'reservation'  => $this->reservation,
                'client'       => $this->reservation->client,
                'decision'     => $this->decision,
                'reason'       => $this->reason,
                'isAccepted'   => $isAccepted,
                'showLink'     => route('admin.reservations.show', $this->reservation),
                // campaignName : priorité au constructeur (rétro-compat avec
                // les appelants qui le fournissent explicitement), sinon on
                // déduit depuis la campagne liée à la réservation.
                'campaign'     => $campaign,
                'campaignName' => $this->campaignName ?? $campaign?->name,
                'campaignLink' => $campaign ? route('admin.campaigns.show', $campaign) : null,
            ],
        );
    }
}
