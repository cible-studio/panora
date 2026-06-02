<?php
namespace App\Mail;

use App\Models\Campaign;
use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Notification client — un (ou plusieurs) panneau(x) de sa campagne entre
 * ou sort de maintenance.
 *
 *   - down : un nouveau panneau de la campagne est tombé en maintenance.
 *            Liste tous les panneaux actuellement indisponibles + leur
 *            date de retour prévue.
 *   - back : un panneau vient d'être remis en service. Si d'autres sont
 *            encore en maintenance, on le précise ; sinon on confirme que
 *            la campagne est de nouveau intégralement diffusée.
 *
 * Pas de lien vers un espace client (certains clients n'en ont pas) — le
 * mail est self-contained.
 */
class ClientPanelMaintenanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign     $campaign,
        public readonly Collection   $maintenancesActives,
        public readonly string       $context, // 'down' | 'back'
        public readonly ?Maintenance $maintenanceResolved = null,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->campaign->name;
        $subject = match ($this->context) {
            'down' => "Campagne {$name} — panneau(x) en maintenance",
            'back' => "Campagne {$name} — panneau de nouveau en ligne",
            default => "Campagne {$name} — point d'avancement",
        };

        return new Envelope(
            subject:  $subject,
            tags:     ['client', 'maintenance', $this->context],
            metadata: [
                'campaign_id'        => (string) $this->campaign->id,
                'maintenances_count' => (string) $this->maintenancesActives->count(),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-maintenance',
            with: [
                'campaign'             => $this->campaign,
                'maintenances'         => $this->maintenancesActives,
                'context'              => $this->context,
                'maintenanceResolved'  => $this->maintenanceResolved,
                'client'               => $this->campaign->client,
            ],
        );
    }
}
