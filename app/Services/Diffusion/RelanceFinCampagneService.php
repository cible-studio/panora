<?php

namespace App\Services\Diffusion;

use App\Enums\CampaignStatus;
use App\Mail\CampaignEndingSoonMail;
use App\Mail\MailClientCampagne;
use App\Models\Campaign;
use App\Models\DiffusionDesinscription;
use App\Models\RelanceFinCampagne;
use App\Services\Brevo\BrevoClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mail au client « Votre campagne se termine le … » (J-7) — 2026-10-21.
 *
 * SEUL endroit qui décide à qui, quand et si ce mail part. Utilisé par la
 * commande campaigns:notify-ending (chaque jour à 10h) et par l'écran
 * admin « Diffusion dispos » (aperçu, envoi de test).
 *
 * Depuis le 2026-10-22, ce service porte aussi le socle commun à tous les
 * mails clients avant échéance (destinataire, mode test, envoi par Brevo,
 * journal — cf. envoyerEtJournaliser), réutilisé par la séquence de
 * renouvellement des campagnes annuelles (RenouvellementService). Le mail
 * J-7 simple ne concerne plus que les campagnes de MOINS de 12 mois.
 *
 * Règles validées par le métier le 2026-10-21 :
 *   · 7 jours avant la fin d'une campagne active, UN seul mail par
 *     campagne (et par date de fin : une prolongation en redonne un) ;
 *   · destinataires : l'interlocuteur de la campagne, sinon chaque contact
 *     coché « reçoit les mails », sinon l'adresse de la fiche
 *     (Client::destinatairesSuivi, 2026-10-29) ;
 *   · réponse au commercial de la campagne, copie commercial@ ;
 *   · pas de mail si le client a une autre campagne planifiée, ou active
 *     qui se termine après celle-ci (deux campagnes qui finissent le même
 *     jour reçoivent chacune le leur) ;
 *   · pas de mail aux adresses désinscrites (ou mortes, ou spam) ;
 *   · mode test d'abord (config diffusion.fin_campagne.mode) ;
 *   · envoi par Brevo UNIQUEMENT (2026-10-22) : chaque mail se suit dans
 *     Brevo → Transactionnel → Journal, tag « fin-campagne ». Pas de
 *     secours par le serveur de mail de Panora, invisible dans Brevo.
 *
 * Robustesse : un mail qui n'a pas pu partir (Brevo indisponible ou qui
 * refuse, client sans adresse) est retenté chaque jour, tant que la campagne
 * n'est pas terminée. Une exclusion (désinscrit, autre campagne) est
 * définitive.
 */
class RelanceFinCampagneService
{
    /** @var string[]|null  adresses de test, lues une fois par exécution */
    private ?array $adressesTest = null;

    public function __construct(
        private BrevoClient $brevo,
    ) {}

    public function estModeTest(): bool
    {
        return config('diffusion.fin_campagne.mode') !== 'production';
    }

    public function mode(): string
    {
        return $this->estModeTest() ? RelanceFinCampagne::MODE_TEST : RelanceFinCampagne::MODE_PRODUCTION;
    }

    public function jours(): int
    {
        return max(1, (int) config('diffusion.fin_campagne.jours', 7));
    }

    /**
     * Copie des mails de fin de campagne et de renouvellement.
     *
     * Deux sources réunies ici (2026-10-30) :
     *   · RELANCE_FIN_COPIE, propre à ces envois (à défaut l'expéditeur) ;
     *   · la copie commerciale de config('mails.copie_commerciale'),
     *     commune à tous les mails commerciaux et de facturation.
     *
     * Ces mails partent par l'API Brevo, pas par le mailer de Laravel :
     * l'écouteur de MessageSending qui pose la copie commerciale ailleurs
     * ne les voit pas. Sans cette réunion, ils seraient les seuls mails
     * commerciaux sans copie.
     *
     * @return string[]
     */
    public function copie(): array
    {
        return array_values(array_unique(array_merge(
            DiffusionDisponibilitesService::adresses(
                config('diffusion.fin_campagne.copie') ?: config('brevo.sender.email')
            ),
            DiffusionDisponibilitesService::adresses(config('mails.copie_commerciale')),
        )));
    }

    // ══════════════════════════════════════════════════════════════
    // SÉLECTION ET ANALYSE
    // ══════════════════════════════════════════════════════════════

    /**
     * Campagnes de MOINS de 12 mois à traiter aujourd'hui : les actives qui
     * se terminent dans N jours, plus celles dont le mail n'a pas pu partir
     * les jours précédents (retentées jusqu'à la veille de la fin). Les
     * campagnes annuelles ont la séquence de renouvellement à la place.
     *
     * @return Collection<int, Campaign>
     */
    public function campagnesDuJour(?int $jours = null): Collection
    {
        $jours ??= $this->jours();

        $aRetenter = RelanceFinCampagne::where('mode', $this->mode())
            ->where('etape', RelanceFinCampagne::ETAPE_FIN)
            ->where('statut', RelanceFinCampagne::STATUT_ECHEC)
            ->whereDate('fin_campagne', '>', today()->toDateString())
            ->pluck('campaign_id');

        return Campaign::query()
            ->where('status', CampaignStatus::ACTIF->value)
            ->whereDate('end_date', '>', today()->toDateString())
            ->where(fn($q) => $q
                ->whereDate('end_date', today()->addDays($jours)->toDateString())
                ->orWhereIn('id', $aRetenter))
            ->with(['client.contacts', 'interlocuteur', 'commercial', 'user'])
            ->orderBy('end_date')
            ->get()
            ->reject(fn(Campaign $c) => $c->estAnnuelle())
            ->values();
    }

    /**
     * À qui écrire pour cette campagne : son interlocuteur, sinon les
     * contacts cochés « reçoit les mails » (Client::destinatairesSuivi,
     * 2026-10-29), copie, adresse de réponse. `destinataire` les liste
     * tous, séparés par des virgules (affichage, journal) ; `contact` est
     * le nom du premier.
     *
     * @return array{destinataires: list<array{email: string, contact: ?string}>, destinataire: string, contact: ?string, copie: string[], repondre_a: ?string}
     */
    public function coordonnees(Campaign $campagne): array
    {
        $client     = $campagne->client;
        $liste      = $client?->destinatairesSuivi($campagne->interlocuteur) ?? [];
        $copie      = $this->copie();
        $commercial = mb_strtolower(trim((string) $campagne->resolveCommercialContact()?->email));

        return [
            'destinataires' => $liste,
            'destinataire'  => implode(', ', array_column($liste, 'email')),
            'contact'       => ($liste[0]['contact'] ?? null) ?: $client?->name,
            'copie'         => $copie,
            'repondre_a'    => filter_var($commercial, FILTER_VALIDATE_EMAIL) ? $commercial : ($copie[0] ?? null),
        ];
    }

    /**
     * Ce qui partirait pour cette campagne, sans rien envoyer.
     *
     * @param bool $regleAutresCampagnes  pas de mail si une autre campagne du
     *        client continue après ou est planifiée (mail J-7 simple)
     * @return array{destinataire: string, contact: ?string, copie: string[], repondre_a: ?string, statut: ?string, motif: ?string}
     *         statut/motif renseignés si le mail ne part pas
     */
    public function analyser(Campaign $campagne, bool $regleAutresCampagnes = true): array
    {
        $coord  = $this->coordonnees($campagne);
        $client = $campagne->client;

        $autres = $client && $regleAutresCampagnes
            ? Campaign::where('client_id', $client->id)
                ->where('id', '!=', $campagne->id)
                ->whereIn('status', [CampaignStatus::ACTIF->value, CampaignStatus::PLANIFIE->value])
                ->get(['id', 'name', 'status', 'end_date'])
                ->map(fn(Campaign $c) => [
                    'nom'    => $c->name,
                    'statut' => $c->status->value,
                    'fin'    => $c->end_date?->toDateString(),
                ])
            : collect();

        // Une adresse désinscrite est retirée ; le mail ne s'arrête que si
        // toutes les adresses le sont.
        $tous    = $coord['destinataires'];
        $exclues = DiffusionDesinscription::whereIn('email', array_column($tous, 'email'))->get()->keyBy('email');
        $gardes  = array_values(array_filter($tous, fn($d) => !$exclues->has($d['email'])));
        $coord['destinataires'] = $gardes;
        $coord['destinataire']  = implode(', ', array_column($gardes ?: $tous, 'email'));
        $coord['contact']       = ($gardes[0]['contact'] ?? null) ?: $coord['contact'];

        [$statut, $motif] = self::exclusion(
            $gardes[0]['email'] ?? ($tous[0]['email'] ?? ''),
            $campagne->end_date?->toDateString() ?? '',
            $autres,
            !$gardes && $tous ? $exclues->first()->libelleMotif() : null,
            today()->toDateString(),
        ) ?? [null, null];

        return $coord + ['statut' => $statut, 'motif' => $motif];
    }

    /**
     * Pourquoi le client ne reçoit PAS le mail — ou null s'il le reçoit.
     * Fonction pure (testée dans RelanceFinCampagneRegleTest).
     *
     * @param iterable<array{nom: string, statut: string, fin: ?string}> $autres  autres campagnes actives/planifiées du client
     * @param ?string $exclusion  libellé si l'adresse est exclue (désinscrit, rebond, spam)
     * @return array{0: string, 1: string}|null  [statut, motif]
     */
    public static function exclusion(string $email, string $fin, iterable $autres, ?string $exclusion, string $aujourdhui): ?array
    {
        foreach ($autres as $a) {
            if ($a['statut'] === CampaignStatus::PLANIFIE->value && ($a['fin'] === null || $a['fin'] >= $aujourdhui)) {
                return [RelanceFinCampagne::STATUT_EXCLU, "Le client a une autre campagne planifiée : « {$a['nom']} »."];
            }
            if ($a['statut'] === CampaignStatus::ACTIF->value && $a['fin'] !== null && $a['fin'] > $fin) {
                $date = MailClientCampagne::dateEnLettres(\Carbon\Carbon::parse($a['fin']));

                return [RelanceFinCampagne::STATUT_EXCLU, "Le client a une autre campagne qui continue : « {$a['nom']} », jusqu'au {$date}."];
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [RelanceFinCampagne::STATUT_ECHEC, 'Aucune adresse e-mail valide (interlocuteur ni fiche client). Ajoutez-la : le mail partira le lendemain.'];
        }

        if ($exclusion !== null) {
            return [RelanceFinCampagne::STATUT_EXCLU, "Adresse exclue des envois commerciaux ({$exclusion})."];
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════
    // ENVOI
    // ══════════════════════════════════════════════════════════════

    /**
     * Mail J-7 simple : examine une campagne, envoie le mail si rien ne s'y
     * oppose, et l'inscrit au journal. Ne renvoie jamais un mail déjà
     * parti, sauf envoi de test demandé par l'admin ($forcerTest).
     */
    public function traiter(Campaign $campagne, bool $forcerTest = false): RelanceFinCampagne
    {
        // Prévenu par l'ancienne version de la commande (drapeau en cache,
        // avant le 2026-10-21) : on le note, sans renvoyer.
        if (!$forcerTest && $this->mode() === RelanceFinCampagne::MODE_PRODUCTION
            && Cache::has("ending_notified.{$campagne->id}.{$this->jours()}")) {
            $ligne = $this->ligne($campagne, RelanceFinCampagne::ETAPE_FIN, RelanceFinCampagne::MODE_PRODUCTION);
            if ($ligne->statut !== RelanceFinCampagne::STATUT_ENVOYE) {
                $ligne->fill([
                    'client_id' => $campagne->client_id,
                    'statut'    => RelanceFinCampagne::STATUT_ENVOYE,
                    'motif'     => 'Déjà prévenu par l\'ancienne version du mail.',
                    'envoye_at' => $ligne->envoye_at ?? now(),
                ])->save();
            }

            return $ligne;
        }

        return $this->envoyerEtJournaliser(
            $campagne,
            RelanceFinCampagne::ETAPE_FIN,
            $forcerTest,
            fn(string $to, ?string $note, array $copie, array $a, int $jours) => new CampaignEndingSoonMail(
                $campagne, $jours, $a['contact'], DiffusionDesinscription::lien($to), $a['repondre_a'], $copie, $note
            ),
            regleAutresCampagnes: true,
        );
    }

    /**
     * Socle commun de tous les mails clients avant échéance (2026-10-22) :
     * destinataire, exclusions, mode test, envoi par Brevo, journal.
     *
     * @param callable(string $to, ?string $note, string[] $copie, array $coord, int $jours): MailClientCampagne $fabrique
     * @param bool $renvoyer  envoyer même si déjà parti (accusé de réception)
     */
    public function envoyerEtJournaliser(
        Campaign $campagne,
        string $etape,
        bool $forcerTest,
        callable $fabrique,
        bool $regleAutresCampagnes,
        bool $renvoyer = false,
    ): RelanceFinCampagne {
        $mode  = $forcerTest ? RelanceFinCampagne::MODE_ESSAI : $this->mode();
        $ligne = $this->ligne($campagne, $etape, $mode);

        if (!$forcerTest && !$renvoyer && $ligne->statut === RelanceFinCampagne::STATUT_ENVOYE) {
            return $ligne;
        }

        $a = $this->analyser($campagne, $regleAutresCampagnes);
        $ligne->fill([
            'client_id'     => $campagne->client_id,
            'destinataire'  => mb_substr($a['destinataire'], 0, 191) ?: null, // colonne VARCHAR(191)
            'destinataires' => $a['destinataire'] ?: null,                     // liste complète (2026-10-28)
            'copie'        => mb_substr(implode(', ', $a['copie']), 0, 191) ?: null,
            'repondre_a'   => $a['repondre_a'],
        ]);

        if ($a['statut'] !== null) {
            $ligne->fill(['statut' => $a['statut'], 'motif' => $a['motif'], 'canal' => null])->save();

            return $ligne;
        }

        // Un vrai client lit toujours les jours réels, même si l'envoi a été
        // forcé à la main (--campagne, --days, --etape) ; un mail de test, le
        // texte qu'il recevra à l'étape.
        $jours = $mode === RelanceFinCampagne::MODE_PRODUCTION
            ? $this->joursRestants($campagne)
            : $this->joursAffiches($campagne, $etape);

        if ($mode !== RelanceFinCampagne::MODE_PRODUCTION) {
            $destinataires = $this->adressesTest();
            $copie = [];
            $note  = self::noteTest($a);
        } else {
            // Un mail par interlocuteur coché (2026-10-28), chacun à son nom.
            $destinataires = array_column($a['destinataires'], 'email');
            $copie = $a['copie'];
            $note  = null;
        }
        $noms = array_column($a['destinataires'], 'contact', 'email');

        if ($destinataires === []) {
            $ligne->fill([
                'statut' => RelanceFinCampagne::STATUT_ECHEC,
                'motif'  => 'Aucune adresse de test : remplissez la liste Brevo « Tests internes » (BREVO_LIST_TESTS) ou DIFFUSION_CONFIRMATION_EMAIL.',
            ])->save();

            return $ligne;
        }

        $ligne->tentatives = (int) $ligne->tentatives + 1;

        // Chaque adresse est envoyée à part (2026-10-28) : un refus pour l'une
        // n'empêche pas les autres, et une adresse déjà servie ne reçoit pas
        // le mail une seconde fois quand l'échec des autres est retenté.
        $ids = [];
        $rates = [];
        foreach ($destinataires as $email) {
            try {
                // Chacun est salué par son nom ; la copie au commercial ne part
                // qu'une fois (avec le premier mail accepté, pas N exemplaires).
                $pour = empty($noms[$email]) ? $a : ['contact' => $noms[$email]] + $a;
                $ids[] = $this->envoyerParBrevo($fabrique($email, $note, $ids === [] ? $copie : [], $pour, $jours), $email);
            } catch (\Throwable $e) {
                $rates[$email] = $e->getMessage();

                Log::error('fin_campagne.echec', [
                    'campaign_id'  => $campagne->id,
                    'etape'        => $etape,
                    'mode'         => $mode,
                    'destinataire' => $email,
                    'erreur'       => $e->getMessage(),
                ]);
            }
        }

        if ($ids === []) {
            $ligne->fill([
                'statut' => RelanceFinCampagne::STATUT_ECHEC,
                'motif'  => mb_substr((string) reset($rates), 0, 1000),
            ]);
        } else {
            $servis = array_values(array_diff($destinataires, array_keys($rates)));
            $ligne->fill([
                'statut'           => RelanceFinCampagne::STATUT_ENVOYE,
                'canal'            => 'brevo',
                'motif'            => mb_substr(trim(
                    ($note ? 'Envoyé en test à ' . implode(', ', $servis) . '. ' : '')
                    . ($rates ? 'Non parvenu à ' . implode(', ', array_keys($rates)) . ' : ' . reset($rates) : '')
                ), 0, 1000) ?: null,
                'brevo_message_id' => mb_substr(implode(', ', array_filter($ids)), 0, 191) ?: null, // colonne VARCHAR(191)
                'envoye_at'        => now(),
            ]);
        }

        $ligne->save();

        return $ligne;
    }

    /** Ligne du journal pour (campagne, date de fin, mode, étape), existante ou nouvelle. */
    public function ligne(Campaign $campagne, string $etape, string $mode): RelanceFinCampagne
    {
        $fin = $campagne->end_date->toDateString();

        return RelanceFinCampagne::where('campaign_id', $campagne->id)
            ->whereDate('fin_campagne', $fin)
            ->where('mode', $mode)
            ->where('etape', $etape)
            ->first()
            ?? new RelanceFinCampagne([
                'campaign_id' => $campagne->id, 'fin_campagne' => $fin, 'mode' => $mode, 'etape' => $etape,
            ]);
    }

    public function joursRestants(Campaign $campagne): int
    {
        return (int) today()->diffInDays($campagne->end_date->copy()->startOfDay(), false);
    }

    /**
     * Jours annoncés dans l'aperçu et les mails de test d'une étape
     * (2026-10-26) : jamais plus que l'étape. Le mail est montré tel qu'il
     * partira (J-7 → « dans 7 jours »), et non avec les jours qui restent
     * aujourd'hui. Un vrai client, lui, lit toujours les jours réels (voir
     * envoyerEtJournaliser) : l'envoi automatique part à l'étape ou après
     * (rattrapage), un envoi forcé à la main dit la vérité.
     * Accusé de réception : jours réels, il part quand le client répond.
     */
    public function joursAffiches(Campaign $campagne, string $etape): int
    {
        $reels = $this->joursRestants($campagne);
        $duree = RenouvellementService::ETAPES[$etape] ?? ($etape === RelanceFinCampagne::ETAPE_FIN ? $this->jours() : null);

        return $duree === null ? $reels : min($reels, $duree);
    }

    /** Bandeau des mails de test : à qui le mail serait parti en production. */
    public static function noteTest(array $a): string
    {
        return "En production, il partirait à {$a['destinataire']}"
            . ($a['copie'] ? ', copie ' . implode(', ', $a['copie']) : '')
            . ($a['repondre_a'] ? ", et les réponses iraient à {$a['repondre_a']}" : '') . '.';
    }

    /**
     * Le mail J-7 simple, tel que le recevrait le client (aperçu de l'écran admin).
     */
    public function mail(Campaign $campagne, ?string $noteTest = null, ?array $modele = null): CampaignEndingSoonMail
    {
        $a = $this->coordonnees($campagne);

        return new CampaignEndingSoonMail(
            $campagne,
            $this->joursAffiches($campagne, RelanceFinCampagne::ETAPE_FIN),
            $a['contact'],
            isset($a['destinataires'][0]) ? DiffusionDesinscription::lien($a['destinataires'][0]['email']) : null,
            $a['repondre_a'],
            $a['copie'],
            $noteTest,
            $modele,
        );
    }

    /**
     * Par Brevo uniquement. Si Brevo n'est pas configuré ou refuse, l'erreur
     * remonte : le mail reste « à retenter » et repart le lendemain.
     *
     * @return ?string  identifiant du message chez Brevo
     */
    public function envoyerParBrevo(MailClientCampagne $mail, string $to): ?string
    {
        // Journal des mails (2026-10-29) : envoi et refus notés, adresse par adresse.
        try {
            $id = $this->brevo->envoyerEmail(self::payloadBrevo($mail, $to));
        } catch (\Throwable $e) {
            \App\Services\JournalMails::brevo($mail->tagBrevo(), $mail->sujet(), $to, $mail->copie, $mail->campaign, $e->getMessage());
            throw $e;
        }
        \App\Services\JournalMails::brevo($mail->tagBrevo(), $mail->sujet(), $to, $mail->copie, $mail->campaign);

        return $id;
    }

    /** Requête de l'API transactionnelle Brevo pour ce mail. */
    public static function payloadBrevo(MailClientCampagne $mail, string $to): array
    {
        $payload = [
            'sender'      => array_filter(['email' => config('brevo.sender.email'), 'name' => config('brevo.sender.name')]),
            'to'          => [array_filter(['email' => $to, 'name' => $mail->contact])],
            'subject'     => $mail->sujet(),
            'htmlContent' => $mail->render(),
            'textContent' => view('emails.plain.client-campagne', $mail->donnees())->render(),
            'tags'        => [$mail->tagBrevo()],
        ];

        if ($mail->copie) {
            $payload['cc'] = array_map(fn($e) => ['email' => $e], array_values($mail->copie));
        }
        if ($mail->repondreA) {
            $payload['replyTo'] = ['email' => $mail->repondreA];
        }
        if ($entetes = MailClientCampagne::entetesDesinscription($mail->lienDesinscription)) {
            $payload['headers'] = $entetes;
        }

        return $payload;
    }

    /**
     * Adresses de test : la liste Brevo « Tests internes » (comme la
     * diffusion des disponibilités), à défaut DIFFUSION_CONFIRMATION_EMAIL.
     *
     * @return string[]
     */
    public function adressesTest(): array
    {
        if ($this->adressesTest !== null) {
            return $this->adressesTest;
        }

        $liste = config('brevo.lists.tests');
        if ($liste && $this->brevo->estConfigure()) {
            try {
                $emails = $this->brevo->emailsDeLaListe((int) $liste);
                if ($emails) {
                    return $this->adressesTest = $emails;
                }
            } catch (\Throwable $e) {
                Log::warning('fin_campagne.liste_tests_illisible', ['erreur' => $e->getMessage()]);
            }
        }

        return $this->adressesTest = DiffusionDisponibilitesService::adresses(config('diffusion.confirmation_email'));
    }

    // ══════════════════════════════════════════════════════════════
    // ÉCRAN ADMIN
    // ══════════════════════════════════════════════════════════════

    /**
     * Campagnes actives de moins de 12 mois qui se terminent dans les
     * $horizon prochains jours, avec la date du mail, ce qui partirait, et
     * ce qui est déjà parti. Les annuelles sont sur l'écran Renouvellements.
     *
     * @return Collection<int, array{campagne: Campaign, date_mail: CarbonInterface, analyse: array, journal: ?RelanceFinCampagne}>
     */
    public function prochaines(int $horizon = 14, ?int $commercialId = null): Collection
    {
        $campagnes = Campaign::query()
            ->where('status', CampaignStatus::ACTIF->value)
            ->whereDate('end_date', '>', today()->toDateString())
            ->whereDate('end_date', '<=', today()->addDays($horizon)->toDateString())
            ->when($commercialId, fn($q) => $q->forCommercialUser($commercialId))
            ->with(['client.contacts', 'interlocuteur', 'commercial', 'user'])
            ->orderBy('end_date')
            ->get()
            ->reject(fn(Campaign $c) => $c->estAnnuelle())
            ->values();

        $journal = RelanceFinCampagne::whereIn('campaign_id', $campagnes->pluck('id'))
            ->where('mode', $this->mode())
            ->where('etape', RelanceFinCampagne::ETAPE_FIN)
            ->get()
            ->keyBy(fn(RelanceFinCampagne $l) => $l->campaign_id . '|' . $l->fin_campagne->toDateString());

        return $campagnes->map(fn(Campaign $c) => [
            'campagne'  => $c,
            'date_mail' => $c->end_date->copy()->subDays($this->jours()),
            'analyse'   => $this->analyser($c),
            'journal'   => $journal[$c->id . '|' . $c->end_date->toDateString()] ?? null,
        ]);
    }
}
