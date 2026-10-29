<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Services\Diffusion\ModelesMail;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Mail au client avant l'échéance de sa campagne (2026-10-22) :
 *   · « fin »     : mail J-7 simple des campagnes de moins de 12 mois ;
 *   · « j60 »…« j7 » : séquence de renouvellement des campagnes annuelles ;
 *   · « accuse »  : accusé de réception d'une réponse.
 *
 * Texte : ModelesMail (modifiable dans Panora). Format léger à la charte :
 * aucune image, boutons sobres, signature et liseré en CSS.
 * Envoyé par Brevo uniquement (RelanceFinCampagneService::envoyerParBrevo),
 * suivi dans Brevo → Transactionnel, tag = tagBrevo().
 */
class MailClientCampagne extends Mailable
{
    use Queueable, SerializesModels;

    /** Calculs mis en mémoire le temps d'un envoi (texte, champs, encadré). */
    private array $memo = [];

    /**
     * @param string[]              $copie
     * @param array<string, string> $liens   choix (renouveler, ajuster…) → lien du bouton
     * @param ?array{objet: string, corps: string} $modele  texte imposé (aperçu de l'éditeur)
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $cle,
        public readonly int $daysRemaining,
        public readonly ?string $contact = null,
        public readonly ?string $lienDesinscription = null,
        public readonly ?string $repondreA = null,
        public readonly array $copie = [],
        public readonly ?string $noteTest = null,
        public readonly array $liens = [],
        public readonly ?string $reponse = null,
        public readonly ?string $message = null,
        public readonly ?array $modele = null,
    ) {}

    public function texteModele(): array
    {
        return $this->memo['modele'] ??= $this->modele ?? app(ModelesMail::class)->modele($this->cle);
    }

    public function sujet(): string
    {
        return ($this->noteTest ? '[TEST] ' : '') . ModelesMail::remplir($this->texteModele()['objet'], $this->valeurs());
    }

    /** Tag Brevo : un par étape, pour filtrer le journal et les statistiques. */
    public function tagBrevo(): string
    {
        return match ($this->cle) {
            'fin'    => 'fin-campagne',
            'accuse' => 'renouvellement-accuse',
            default  => 'renouvellement-' . $this->cle,
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  $this->sujet(),
            cc:       array_map(fn($e) => new Address($e), $this->copie),
            replyTo:  $this->repondreA ? [new Address($this->repondreA)] : [],
            tags:     [$this->tagBrevo()],
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
            view: 'emails.client-campagne',
            text: 'emails.plain.client-campagne',
            with: $this->donnees(),
        );
    }

    /** Variables des deux vues (HTML et texte). */
    public function donnees(): array
    {
        $corps  = $this->texteModele()['corps'];
        $html   = ModelesMail::html($corps, $this->valeurs(), $this->blocs($corps, true));
        $texte  = ModelesMail::texte($corps, $this->valeurs(), $this->blocs($corps, false));

        return [
            'campaign'           => $this->campaign,
            'paragraphes'        => $html,
            'texte'              => $texte,
            'preheader'          => $this->preheader(),
            'lienDesinscription' => $this->lienDesinscription,
            'noteTest'           => $this->noteTest,
            'charte'             => config('charte'),
        ];
    }

    /** [Champ] → valeur. */
    public function valeurs(): array
    {
        if (isset($this->memo['valeurs'])) {
            return $this->memo['valeurs'];
        }

        $c      = $this->campaign;
        $client = $c->client;
        $n      = $this->nombrePanneaux();

        return $this->memo['valeurs'] = [
            // Interlocuteur + client, toujours (2026-10-29) : « Awa Koné (ORANGE CI) ».
            '[Nom du client]'      => \App\Support\Salutation::nom($client, $this->contact),
            '[Société]'            => (string) ($client?->name ?? ''),
            '[Nom de la campagne]' => (string) $c->name,
            "[Date d'échéance]"    => self::dateEnLettres($c->end_date),
            '[Jours restants]'     => self::echeance($this->daysRemaining),
            '[Date limite]'        => self::dateEnLettres($c->end_date?->copy()->subDays(30)),
            '[Dispositif]'         => $n > 0 ? $n . ' panneau' . ($n > 1 ? 'x' : '') : 'vos emplacements actuels',
            '[Nom du commercial]'  => $c->resolveCommercialContact()?->name ?: 'Notre équipe commerciale',
            '[Régie]'              => (string) config('charte.nom'),
            '[Votre réponse]'      => (string) ($this->reponse ?? ''),
            '[Votre message]'      => $this->message ? '« ' . $this->message . ' »' : '',
        ];
    }

    /** [Récapitulatif] et [Boutons] : calculés seulement s'ils sont dans le texte. */
    private function blocs(string $corps, bool $html): array
    {
        $a = mb_strtolower($corps);
        $recap   = str_contains($a, '[récapitulatif]') ? $this->recapitulatif() : null;
        $boutons = str_contains($a, '[boutons]') ? $this->boutons() : [];

        if ($html) {
            return [
                '[Récapitulatif]' => $recap ? view('emails.partials.client-recapitulatif', $recap + ['charte' => config('charte')])->render() : '',
                '[Boutons]'       => $boutons ? view('emails.partials.client-boutons', ['boutons' => $boutons, 'charte' => config('charte')])->render() : '',
            ];
        }

        $txtRecap = $recap
            ? "Campagne : {$recap['campagne']}\nDispositif : {$recap['dispositif']}\nÉchéance : {$recap['echeance']}"
              . ($recap['panneaux'] ? "\nEmplacements : " . implode(', ', $recap['panneaux']) . ($recap['autres'] ? " et {$recap['autres']} autre(s)" : '') : '')
            : '';
        $txtBoutons = implode("\n", array_map(fn($b) => "{$b['libelle']} : {$b['lien']}", $boutons));

        return ['[Récapitulatif]' => $txtRecap, '[Boutons]' => $txtBoutons];
    }

    /** @return array{campagne: string, dispositif: string, echeance: string, panneaux: string[], autres: int} */
    private function recapitulatif(): array
    {
        if (isset($this->memo['recap'])) {
            return $this->memo['recap'];
        }

        $panneaux = [];
        $total    = 0;
        if ($this->campaign->exists) {
            try {
                $total = $this->campaign->panels()->count();
                $panneaux = $this->campaign->panels()->with('commune:id,name')->orderBy('panels.reference')->limit(6)
                    ->get(['panels.id', 'panels.reference', 'panels.commune_id'])
                    ->map(fn($p) => trim($p->reference . ($p->commune?->name ? ' · ' . $p->commune->name : '')))
                    ->all();
            } catch (\Throwable) {
                // aperçu sans base complète : encadré sans la liste
            }
        }

        return $this->memo['recap'] = [
            'campagne'   => (string) $this->campaign->name,
            'dispositif' => $this->valeurs()['[Dispositif]'],
            'echeance'   => self::dateEnLettres($this->campaign->end_date),
            'panneaux'   => $panneaux,
            'autres'     => max(0, $total - count($panneaux)),
        ];
    }

    /** @return list<array{choix: string, libelle: string, lien: string}> */
    private function boutons(): array
    {
        return array_map(fn($choix) => [
            'choix'   => $choix,
            'libelle' => ModelesMail::CHOIX[$choix],
            'lien'    => $this->liens[$choix] ?? '#',
        ], ModelesMail::BOUTONS[$this->cle] ?? []);
    }

    private function nombrePanneaux(): int
    {
        $n = (int) ($this->campaign->total_panels ?? 0);
        if ($n === 0 && $this->campaign->exists) {
            try {
                $n = $this->campaign->panels()->count();
            } catch (\Throwable) {
                $n = 0;
            }
        }

        return $n;
    }

    private function preheader(): string
    {
        $v = $this->valeurs();

        return match ($this->cle) {
            'fin'    => "« {$v['[Nom de la campagne]']} » se termine {$v['[Jours restants]']}. Prolonger sa visibilité ou préparer la suite : répondez simplement à ce mail.",
            'accuse' => 'Nous avons bien reçu votre réponse.',
            default  => "Échéance le {$v["[Date d'échéance]"]} : indiquez-nous vos intentions en un clic.",
        };
    }

    // ══════════════════════════════════════════════════════════════

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
