<?php

namespace App\Services\Diffusion;

use App\Enums\CampaignStatus;
use App\Mail\CampaignEndingSoonMail;
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
 * Règles validées par le métier le 2026-10-21 :
 *   · 7 jours avant la fin d'une campagne active, UN seul mail par
 *     campagne (et par date de fin : une prolongation en redonne un) ;
 *   · destinataire : l'interlocuteur principal du client (même règle que
 *     la diffusion des disponibilités, Client::destinataireCommercial) ;
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

    /** @return string[] */
    public function copie(): array
    {
        return DiffusionDisponibilitesService::adresses(
            config('diffusion.fin_campagne.copie') ?: config('brevo.sender.email')
        );
    }

    // ══════════════════════════════════════════════════════════════
    // SÉLECTION ET ANALYSE
    // ══════════════════════════════════════════════════════════════

    /**
     * Campagnes à traiter aujourd'hui : les actives qui se terminent dans
     * N jours, plus celles dont le mail n'a pas pu partir les jours
     * précédents (retentées jusqu'à la veille de la fin).
     *
     * @return Collection<int, Campaign>
     */
    public function campagnesDuJour(?int $jours = null): Collection
    {
        $jours ??= $this->jours();

        $aRetenter = RelanceFinCampagne::where('mode', $this->mode())
            ->where('statut', RelanceFinCampagne::STATUT_ECHEC)
            ->whereDate('fin_campagne', '>', today()->toDateString())
            ->pluck('campaign_id');

        return Campaign::query()
            ->where('status', CampaignStatus::ACTIF->value)
            ->whereDate('end_date', '>', today()->toDateString())
            ->where(fn($q) => $q
                ->whereDate('end_date', today()->addDays($jours)->toDateString())
                ->orWhereIn('id', $aRetenter))
            ->with(['client.contacts', 'commercial', 'user'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Ce qui partirait pour cette campagne, sans rien envoyer.
     *
     * @return array{destinataire: string, contact: ?string, copie: string[], repondre_a: ?string, statut: ?string, motif: ?string}
     *         statut/motif renseignés si le mail ne part pas
     */
    public function analyser(Campaign $campagne): array
    {
        $client = $campagne->client;
        $dest   = $client?->destinataireCommercial() ?? ['email' => '', 'contact' => null];

        $autres = $client
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

        $exclusion = DiffusionDesinscription::where('email', $dest['email'])->first();

        [$statut, $motif] = self::exclusion(
            $dest['email'],
            $campagne->end_date?->toDateString() ?? '',
            $autres,
            $exclusion ? $exclusion->libelleMotif() : null,
            today()->toDateString(),
        ) ?? [null, null];

        $copie      = $this->copie();
        $commercial = mb_strtolower(trim((string) $campagne->resolveCommercialContact()?->email));

        return [
            'destinataire' => $dest['email'],
            'contact'      => $dest['contact'] ?: $client?->name,
            'copie'        => $copie,
            'repondre_a'   => filter_var($commercial, FILTER_VALIDATE_EMAIL) ? $commercial : ($copie[0] ?? null),
            'statut'       => $statut,
            'motif'        => $motif,
        ];
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
                $date = CampaignEndingSoonMail::dateEnLettres(\Carbon\Carbon::parse($a['fin']));

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
     * Examine une campagne, envoie le mail si rien ne s'y oppose, et
     * l'inscrit au journal. Ne renvoie jamais un mail déjà parti, sauf
     * envoi de test demandé par l'admin ($forcerTest).
     */
    public function traiter(Campaign $campagne, bool $forcerTest = false): RelanceFinCampagne
    {
        $mode  = $forcerTest ? RelanceFinCampagne::MODE_TEST : $this->mode();
        $fin   = $campagne->end_date->toDateString();
        $ligne = RelanceFinCampagne::where('campaign_id', $campagne->id)
            ->whereDate('fin_campagne', $fin)
            ->where('mode', $mode)
            ->first()
            ?? new RelanceFinCampagne(['campaign_id' => $campagne->id, 'fin_campagne' => $fin, 'mode' => $mode]);

        if (!$forcerTest && $ligne->statut === RelanceFinCampagne::STATUT_ENVOYE) {
            return $ligne;
        }

        // Prévenu par l'ancienne version de la commande (drapeau en cache,
        // avant le 2026-10-21) : on le note, sans renvoyer.
        if (!$forcerTest && $mode === RelanceFinCampagne::MODE_PRODUCTION
            && Cache::has("ending_notified.{$campagne->id}.{$this->jours()}")) {
            $ligne->fill([
                'client_id' => $campagne->client_id,
                'statut'    => RelanceFinCampagne::STATUT_ENVOYE,
                'motif'     => 'Déjà prévenu par l\'ancienne version du mail.',
                'envoye_at' => $ligne->envoye_at ?? now(),
            ])->save();

            return $ligne;
        }

        $a = $this->analyser($campagne);
        $ligne->fill([
            'client_id'    => $campagne->client_id,
            'destinataire' => $a['destinataire'] ?: null,
            'copie'        => implode(', ', $a['copie']) ?: null,
            'repondre_a'   => $a['repondre_a'],
        ]);

        if ($a['statut'] !== null) {
            $ligne->fill(['statut' => $a['statut'], 'motif' => $a['motif'], 'canal' => null])->save();

            return $ligne;
        }

        $jours = (int) today()->diffInDays($campagne->end_date->copy()->startOfDay(), false);

        if ($mode === RelanceFinCampagne::MODE_TEST) {
            $destinataires = $this->adressesTest();
            $copie = [];
            $note  = "En production, il partirait à {$a['destinataire']}"
                . ($a['copie'] ? ', copie ' . implode(', ', $a['copie']) : '')
                . ($a['repondre_a'] ? ", et les réponses iraient à {$a['repondre_a']}" : '') . '.';
        } else {
            $destinataires = [$a['destinataire']];
            $copie = $a['copie'];
            $note  = null;
        }

        if ($destinataires === []) {
            $ligne->fill([
                'statut' => RelanceFinCampagne::STATUT_ECHEC,
                'motif'  => 'Aucune adresse de test : remplissez la liste Brevo « Tests internes » (BREVO_LIST_TESTS) ou DIFFUSION_CONFIRMATION_EMAIL.',
            ])->save();

            return $ligne;
        }

        $ligne->tentatives = (int) $ligne->tentatives + 1;

        try {
            $canaux = [];
            $ids    = [];
            foreach ($destinataires as $email) {
                [$canaux[], $ids[]] = $this->envoyer($campagne, $jours, $email, $a['contact'], $copie, $a['repondre_a'], $note);
            }

            $ligne->fill([
                'statut'           => RelanceFinCampagne::STATUT_ENVOYE,
                'canal'            => implode(', ', array_unique($canaux)),
                'motif'            => $note ? 'Envoyé en test à ' . implode(', ', $destinataires) . '.' : null,
                'brevo_message_id' => mb_substr(implode(', ', array_filter($ids)), 0, 255) ?: null,
                'envoye_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            $ligne->fill([
                'statut' => RelanceFinCampagne::STATUT_ECHEC,
                'motif'  => mb_substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('fin_campagne.echec', [
                'campaign_id' => $campagne->id,
                'mode'        => $mode,
                'erreur'      => $e->getMessage(),
            ]);
        }

        $ligne->save();

        return $ligne;
    }

    /**
     * Le mail, tel que le recevrait le client (aperçu de l'écran admin).
     */
    public function mail(Campaign $campagne, ?string $noteTest = null): CampaignEndingSoonMail
    {
        $a = $this->analyser($campagne);

        return new CampaignEndingSoonMail(
            $campagne,
            (int) today()->diffInDays($campagne->end_date->copy()->startOfDay(), false),
            $a['contact'],
            filter_var($a['destinataire'], FILTER_VALIDATE_EMAIL) ? DiffusionDesinscription::lien($a['destinataire']) : null,
            $a['repondre_a'],
            $a['copie'],
            $noteTest,
        );
    }

    /**
     * Par Brevo uniquement. Si Brevo n'est pas configuré ou refuse, l'erreur
     * remonte : le mail reste « à retenter » et repart le lendemain.
     *
     * @param string[] $copie
     * @return array{0: string, 1: ?string}  canal, identifiant Brevo
     */
    private function envoyer(Campaign $campagne, int $jours, string $to, ?string $contact, array $copie, ?string $repondreA, ?string $note): array
    {
        $mail = new CampaignEndingSoonMail(
            $campagne, $jours, $contact, DiffusionDesinscription::lien($to), $repondreA, $copie, $note
        );

        return ['brevo', $this->brevo->envoyerEmail(self::payloadBrevo($mail, $to))];
    }

    /** Requête de l'API transactionnelle Brevo pour ce mail. */
    public static function payloadBrevo(CampaignEndingSoonMail $mail, string $to): array
    {
        $payload = [
            'sender'      => array_filter(['email' => config('brevo.sender.email'), 'name' => config('brevo.sender.name')]),
            'to'          => [array_filter(['email' => $to, 'name' => $mail->contact])],
            'subject'     => $mail->sujet(),
            'htmlContent' => $mail->render(),
            'textContent' => view('emails.plain.campaign-ending-soon', $mail->donnees())->render(),
            'tags'        => ['fin-campagne'],
        ];

        if ($mail->copie) {
            $payload['cc'] = array_map(fn($e) => ['email' => $e], array_values($mail->copie));
        }
        if ($mail->repondreA) {
            $payload['replyTo'] = ['email' => $mail->repondreA];
        }
        if ($entetes = CampaignEndingSoonMail::entetesDesinscription($mail->lienDesinscription)) {
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
     * Campagnes actives qui se terminent dans les $horizon prochains jours,
     * avec la date du mail, ce qui partirait, et ce qui est déjà parti.
     *
     * @return Collection<int, array{campagne: Campaign, date_mail: CarbonInterface, analyse: array, journal: ?RelanceFinCampagne}>
     */
    public function prochaines(int $horizon = 14): Collection
    {
        $campagnes = Campaign::query()
            ->where('status', CampaignStatus::ACTIF->value)
            ->whereDate('end_date', '>', today()->toDateString())
            ->whereDate('end_date', '<=', today()->addDays($horizon)->toDateString())
            ->with(['client.contacts', 'commercial', 'user'])
            ->orderBy('end_date')
            ->get();

        $journal = RelanceFinCampagne::whereIn('campaign_id', $campagnes->pluck('id'))
            ->where('mode', $this->mode())
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
