<?php

namespace App\Mail;

use App\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Baromètre clients (2026-11-17) : invitation et relance.
 *
 * Envoyé par Brevo uniquement (BarometreService), suivi dans Brevo →
 * Transactionnel, tag « barometre-<édition> » ou « …-relance ».
 * Texte : resources/views/emails/barometre.blade.php — BROUILLON tant que
 * la direction ne l'a pas validé.
 *
 * Pas de MailCommercial : c'est une enquête, pas un mail commercial (pas de
 * copie à l'équipe, cf. l'interface).
 */
class BarometreMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Client $client,
        public readonly ?string $contact,
        public readonly string $lien,
        public readonly CarbonInterface $echeance,
        public readonly bool $relance = false,
        public readonly ?string $lienDesinscription = null,
        public readonly ?string $noteTest = null,
        /** @var array{nom: ?string, fonction: ?string, societe: ?string} vide = config barometre.signature */
        public readonly array $signature = [],
    ) {}

    /** Objets de la direction (17/11). Relance : 1re des deux formules proposées. */
    public function sujet(): string
    {
        $regie = (string) config('charte.nom', 'CIBLE');
        $sujet = $this->relance
            ? "Rappel | Votre avis compte pour {$regie}"
            : "Votre avis compte pour {$regie} | " . config('barometre.duree') . ' pour nous aider à progresser';

        return ($this->noteTest ? '[TEST] ' : '') . $sujet;
    }

    /** Tag Brevo : un pour l'invitation, un pour la relance. */
    public function tagBrevo(): string
    {
        return 'barometre-' . config('barometre.edition') . ($this->relance ? '-relance' : '');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  $this->sujet(),
            tags:     [$this->tagBrevo()],
            metadata: ['client_id' => (string) $this->client->id],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: MailClientCampagne::entetesDesinscription($this->lienDesinscription));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.barometre',
            text: 'emails.plain.barometre',
            with: $this->donnees(),
        );
    }

    /** Variables des deux vues (HTML et texte). */
    public function donnees(): array
    {
        return [
            'client'             => $this->client,
            'contact'            => $this->contact,
            'lien'               => $this->lien,
            'echeance'           => $this->echeance,
            'relance'            => $this->relance,
            'lienDesinscription' => $this->lienDesinscription,
            'noteTest'           => $this->noteTest,
            'duree'              => (string) config('barometre.duree'),
            'signature'          => $this->signature ?: (array) config('barometre.signature'),
        ];
    }

    /**
     * HTML envoyé à Brevo, styles écrits en ligne. Les mails partis par le
     * serveur de Panora le sont par AppServiceProvider (MessageSending) ;
     * l'API Brevo ne passe pas par là. Même outil, même repli.
     */
    public function htmlEnLigne(): string
    {
        $html = $this->render();
        try {
            return (new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles())->convert($html);
        } catch (\Throwable $e) {
            Log::warning('mail.css_inline.failed', ['mail' => 'barometre', 'error' => $e->getMessage()]);

            return $html;
        }
    }

    public function texte(): string
    {
        return view('emails.plain.barometre', $this->donnees())->render();
    }
}
