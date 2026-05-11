<?php
namespace App\Mail;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé sur les évènements clés d'une maintenance :
 *
 *   - assigned : la maintenance vient d'être attribuée à un technicien.
 *                Destinataire : le technicien. Doit contenir lien direct
 *                vers la fiche + numéro WhatsApp du signaleur.
 *   - updated  : informations modifiées (priorité, description, type).
 *                Destinataire : le technicien encore assigné.
 *   - resolved : la maintenance vient d'être marquée résolue.
 *                Destinataire : le signaleur + technicien (pour confirmation).
 */
class MaintenanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Maintenance $maintenance,
        public readonly string      $context, // 'assigned' | 'updated' | 'resolved'
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->maintenance->panel?->reference ?? '?';
        $subject = match ($this->context) {
            'assigned' => "Maintenance assignée — panneau {$ref}",
            'updated'  => "Maintenance mise à jour — panneau {$ref}",
            'resolved' => "Maintenance résolue — panneau {$ref}",
            default    => "Notification maintenance — panneau {$ref}",
        };

        return new Envelope(
            subject:  $subject,
            tags:     ['maintenance', $this->context],
            metadata: ['maintenance_id' => (string) $this->maintenance->id],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.maintenance',
            with: [
                'maintenance' => $this->maintenance,
                'context'     => $this->context,
                'panel'       => $this->maintenance->panel,
                'tech'        => $this->maintenance->technicien,
                'signaler'    => $this->maintenance->signaledBy,
                'showUrl'     => route('admin.maintenances.show', $this->maintenance),
            ],
        );
    }
}
