<?php

namespace App\Mail;

use App\Models\DiffusionEnvoi;
use App\Services\Diffusion\DiffusionCalendrier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation interne après une diffusion des disponibilités — ou alerte
 * si elle a échoué (2026-09-29).
 *
 * Destinataires : commercial@ + tous les Media Planners actifs (cf.
 * DiffusionDisponibilitesService::destinatairesInternes). Ne passe PAS par
 * la campagne Brevo : leurs clics fausseraient les statistiques clients.
 */
class DiffusionDisponibilitesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DiffusionEnvoi $envoi,
        public readonly ?string $lienApercu = null,
    ) {}

    public function envelope(): Envelope
    {
        $periode = DiffusionCalendrier::libellePeriode($this->envoi->periode_debut, $this->envoi->periode_fin);
        $test = $this->envoi->mode === DiffusionEnvoi::MODE_TEST || config('diffusion.mode') !== 'production';

        $sujet = $this->envoi->statut === DiffusionEnvoi::STATUT_ENVOYE
            ? "✅ Disponibilités envoyées aux clients — {$periode}"
            : "⚠️ Échec de l'envoi des disponibilités — {$periode}";

        return new Envelope(
            subject:  ($test ? '[TEST] ' : '') . $sujet,
            tags:     ['diffusion-dispos'],
            metadata: ['diffusion_envoi_id' => (string) $this->envoi->id],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.diffusion-dispos',
            with: [
                'envoi'      => $this->envoi,
                'succes'     => $this->envoi->statut === DiffusionEnvoi::STATUT_ENVOYE,
                'periode'    => DiffusionCalendrier::libellePeriode($this->envoi->periode_debut, $this->envoi->periode_fin),
                'lienPdf'    => $this->envoi->lienPdf(),
                'lienApercu' => $this->lienApercu,
                'lienEcran'  => route('admin.diffusion-dispos.index'),
                'test'       => $this->envoi->mode === DiffusionEnvoi::MODE_TEST || config('diffusion.mode') !== 'production',
            ],
        );
    }
}
