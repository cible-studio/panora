<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * AdminAlertMail — email générique pour alerter le staff (commercial, admin,
 * MP, comptable) sur un événement opérationnel. Construit via un payload
 * structuré pour rester flexible (pas de Mailable par type d'alerte).
 *
 * Usage :
 *   new AdminAlertMail(
 *       severity: 'warning',
 *       title: 'Nouvelle pige uploadée par Yannick',
 *       summary: 'Panneau ABJ-001 — campagne ORANGE Q3',
 *       lines: ['Date pose : 21/05/2026', 'GPS : 5.3°N / 4.1°W'],
 *       ctaLabel: 'Valider la pige →',
 *       ctaUrl: route('admin.piges.show', $pige),
 *       footer: 'Pige #42 — par Yannick TANO',
 *   );
 */
class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $severity,   // info | success | warning | danger
        public readonly string $title,
        public readonly string $summary,
        public readonly array  $lines = [],
        public readonly ?string $ctaLabel = null,
        public readonly ?string $ctaUrl   = null,
        public readonly ?string $footer   = null,
        public readonly ?string $emoji    = null,
    ) {}

    public function envelope(): Envelope
    {
        $emoji = $this->emoji ?? match($this->severity) {
            'danger'  => '🔴',
            'warning' => '🟠',
            'success' => '🟢',
            default   => '🔵',
        };
        return new Envelope(subject: "{$emoji} {$this->title}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-alert',
            with: [
                'severity' => $this->severity,
                'title'    => $this->title,
                'summary'  => $this->summary,
                'lines'    => $this->lines,
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl'   => $this->ctaUrl,
                'footer'   => $this->footer,
                'emoji'    => $this->emoji ?? match($this->severity) {
                    'danger'  => '🔴',
                    'warning' => '🟠',
                    'success' => '🟢',
                    default   => '🔵',
                },
                'color'    => match($this->severity) {
                    'danger'  => '#dc2626',
                    'warning' => '#d97706',
                    'success' => '#16a34a',
                    default   => '#3b82f6',
                },
            ],
        );
    }
}
