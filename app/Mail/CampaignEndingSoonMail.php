<?php

namespace App\Mail;

use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Mail au client : « Votre campagne se termine le … » (J-7).
 *
 * Refait le 2026-10-21 (règles validées, cf. RelanceFinCampagneService) :
 *   · format léger « écrit par un commercial » — aucune image, aucun
 *     bouton, réponse par retour de mail — pour arriver dans la boîte de
 *     réception plutôt que dans Promotions ;
 *   · réponse au commercial de la campagne, copie commercial@ ;
 *   · plus d'« offre de fidélité » (elle n'existe pas).
 *
 * Le même rendu sert aux deux canaux : Brevo (HTML rendu par Panora, cf.
 * RelanceFinCampagneService::envoyer) et SMTP en secours.
 */
class CampaignEndingSoonMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string[] $copie
     * @param ?string  $noteTest  en mode test : à qui le mail serait parti
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly int $daysRemaining,
        public readonly ?string $contact = null,
        public readonly ?string $lienDesinscription = null,
        public readonly ?string $repondreA = null,
        public readonly array $copie = [],
        public readonly ?string $noteTest = null,
    ) {}

    public function sujet(): string
    {
        return ($this->noteTest ? '[TEST] ' : '')
            . "Votre campagne « {$this->campaign->name} » se termine le "
            . self::dateEnLettres($this->campaign->end_date, false);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  $this->sujet(),
            cc:       array_map(fn($e) => new Address($e), $this->copie),
            replyTo:  $this->repondreA ? [new Address($this->repondreA)] : [],
            tags:     ['fin-campagne'],
            metadata: ['campaign_id' => (string) $this->campaign->id],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: self::entetesDesinscription($this->lienDesinscription));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-ending-soon',
            text: 'emails.plain.campaign-ending-soon',
            with: $this->donnees(),
        );
    }

    /** Variables des deux vues (HTML et texte). */
    public function donnees(): array
    {
        return [
            'campaign'           => $this->campaign,
            'client'             => $this->campaign->client,
            'daysRemaining'      => $this->daysRemaining,
            'salutation'         => $this->contact ?: ($this->campaign->client?->name ?: 'Madame, Monsieur'),
            'dateFin'            => self::dateEnLettres($this->campaign->end_date),
            'echeance'           => self::echeance($this->daysRemaining),
            'lienDesinscription' => $this->lienDesinscription,
            'noteTest'           => $this->noteTest,
            'charte'             => config('charte'),
        ];
    }

    /**
     * En-têtes de désinscription en un clic (RFC 8058), exigés par Gmail
     * et Yahoo pour les expéditeurs réguliers.
     *
     * @return array<string, string>
     */
    public static function entetesDesinscription(?string $lien): array
    {
        return $lien ? [
            'List-Unsubscribe'      => "<{$lien}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : [];
    }

    /** « 1er novembre 2026 », « 31 octobre » (sans l'année). */
    public static function dateEnLettres(?CarbonInterface $date, bool $annee = true): string
    {
        if (!$date) {
            return '—';
        }

        $jour = $date->day === 1 ? '1er' : (string) $date->day;

        return trim($jour . ' ' . $date->locale('fr')->translatedFormat($annee ? 'F Y' : 'F'));
    }

    /** « dans 7 jours », « demain », « aujourd'hui ». */
    public static function echeance(int $jours): string
    {
        return match (true) {
            $jours <= 0 => 'aujourd\'hui',
            $jours === 1 => 'demain',
            default => "dans {$jours} jours",
        };
    }
}
