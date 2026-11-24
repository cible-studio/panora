<?php

namespace App\Services\Barometre;

use App\Mail\BarometreMail;
use App\Mail\MailClientCampagne;
use App\Models\BarometreEdition;
use App\Models\BarometreEnvoi;
use App\Models\BarometreReponse;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\DiffusionDesinscription;
use App\Models\PublicLink;
use App\Models\Quote;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Brevo\BrevoClient;
use App\Services\JournalMails;
use App\Services\PublicLinkService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Baromètre clients (2026-11-17) — source unique de l'envoi du
 * questionnaire : qui le reçoit, l'envoi et la relance par Brevo, le lien
 * personnel, les réponses signalées par Google.
 *
 * Décisions validées par écrit (16 et 17/11) :
 *   · un mail par entreprise, à l'interlocuteur de Client::destinataireBarometre() ;
 *   · exclus : ce que l'écran retire (régies, nouveaux clients…), les
 *     adresses écartées des envois commerciaux, les clients sans e-mail ;
 *   · le nombre affiché est celui envoyé : la demande est refusée s'il a
 *     changé entre l'ouverture de la page et le clic ;
 *   · lien personnel 256 bits qui expire seul à 23:59:59 le jour limite ;
 *   · relance par un bouton, seulement à ceux qui n'ont pas répondu ;
 *   · mode test (défaut) : rien ne part aux clients.
 *
 * L'envoi lui-même est fait par la tâche planifiée barometre:envoyer
 * (chaque minute, en arrière-plan) : un clic ne fait que le demander.
 */
class BarometreService
{
    public const TYPE_LIEN = 'barometre';

    /** Statut affiché → [classe du badge, libellé]. */
    public const STATUTS = [
        'repondu'    => ['badge-green',  'A répondu'],
        'ouvert'     => ['badge-teal',   'A ouvert le lien'],
        'invite'     => ['badge-blue',   'Invité'],
        'en_cours'   => ['badge-purple', 'Envoi en cours'],
        'echec'      => ['badge-red',    'Échec de l\'envoi'],
        'pret'       => ['badge-orange', 'À envoyer'],
        'exclu'      => ['badge-gray',   'Exclu'],
        'doublon'    => ['badge-gray',   'Adresse déjà utilisée'],
        'a_choisir'  => ['badge-orange', 'Interlocuteur à choisir'],
        'sans_email' => ['badge-red',    'Aucun e-mail'],
    ];

    /** Statuts qui recevront l'invitation au prochain « Envoyer à tous ». */
    public const A_ENVOYER = ['pret', 'echec'];

    /** Statuts « sans destinataire » : il manque un interlocuteur utilisable. */
    public const SANS_DESTINATAIRE = ['sans_email', 'a_choisir', 'doublon'];

    private ?BarometreEdition $edition = null;
    private ?Collection $liste = null;
    /** @var ?array<string, string> */
    private ?array $ecartees = null;

    public function __construct(private BrevoClient $brevo) {}

    // ══════════════════════════════════════════════════════════════
    // ÉDITION ET RÉGLAGES
    // ══════════════════════════════════════════════════════════════

    public function code(): string
    {
        return (string) config('barometre.edition');
    }

    public function edition(): BarometreEdition
    {
        return $this->edition ??= BarometreEdition::firstOrCreate(
            ['edition' => $this->code()],
            ['date_limite' => Carbon::parse((string) config('barometre.date_limite'))->toDateString()],
        );
    }

    public function enProduction(): bool
    {
        return config('barometre.mode') === 'production';
    }

    /** Réglage qui empêche tout envoi, même de test ; null si tout est prêt. */
    public function reglageManquant(): ?string
    {
        if (!$this->formulaire() || !$this->champStructure()) {
            return 'Le questionnaire n\'est pas relié : collez son lien prérempli dans ⚙ Réglages.';
        }
        if (!self::lienGoogleForms($this->formulaire())) {
            return 'Le lien du questionnaire doit être un lien Google Forms (https://docs.google.com/forms/…).';
        }
        if (!$this->brevo->estConfigure()) {
            return 'Brevo n\'est pas configuré sur le serveur (BREVO_API_KEY, BREVO_SENDER_EMAIL).';
        }

        return null;
    }

    // Réglages de l'édition (écran ⚙ Réglages), sinon ceux du .env.

    public function formulaire(): ?string
    {
        return $this->edition()->formulaire ?: (config('barometre.formulaire') ?: null);
    }

    public function champStructure(): ?string
    {
        return $this->edition()->champ_structure ?: (config('barometre.champ_structure') ?: null);
    }

    /** @return array{nom: ?string, fonction: ?string, societe: ?string} */
    public function signature(): array
    {
        $e = $this->edition();
        $c = (array) config('barometre.signature');

        return [
            'nom'      => $e->signature_nom ?: ($c['nom'] ?? null),
            'fonction' => $e->signature_fonction ?: ($c['fonction'] ?? null),
            'societe'  => $e->signature_societe ?: ($c['societe'] ?? null),
        ];
    }

    /** Adresse qui reçoit les réponses des clients ; null = l'expéditeur Brevo. */
    public function repondreA(): ?string
    {
        return $this->edition()->repondre_a ?: (config('barometre.repondre_a') ?: null);
    }

    /** Nom affiché de l'expéditeur : la personne qui signe, sinon l'équipe. */
    public function expediteur(): string
    {
        if ($nom = config('barometre.expediteur')) {
            return (string) $nom;
        }
        $s = $this->signature();

        return $s['nom']
            ? $s['nom'] . ' – ' . ($s['societe'] ?: config('charte.nom'))
            : 'L\'équipe commerciale ' . config('charte.nom');
    }

    /**
     * ⚙ Réglages. Le lien du questionnaire est le LIEN PRÉREMPLI de Google
     * (⋮ → « Obtenir le lien prérempli », un nom tapé dans « Votre
     * structure ») : Panora en tire l'adresse et le champ du nom.
     *
     * @param array{lien?: ?string, signature_nom?: ?string, signature_fonction?: ?string,
     *              signature_societe?: ?string, repondre_a?: ?string} $r
     */
    public function enregistrerReglages(array $r): void
    {
        $valeurs = [];
        if (array_key_exists('lien', $r)) {
            $lien = trim((string) $r['lien']);
            [$valeurs['formulaire'], $valeurs['champ_structure']] = $lien === '' ? [null, null] : self::lirePrerempli($lien);
        }
        foreach (['signature_nom', 'signature_fonction', 'signature_societe', 'repondre_a'] as $champ) {
            if (array_key_exists($champ, $r)) {
                $valeurs[$champ] = trim((string) $r[$champ]) !== '' ? trim((string) $r[$champ]) : null;
            }
        }
        if (isset($valeurs['repondre_a']) && !filter_var($valeurs['repondre_a'], FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('L\'adresse de réponse n\'est pas une adresse e-mail valide.');
        }

        $this->edition()->update($valeurs);
        $this->edition = null;
    }

    /**
     * Lien prérempli → [adresse sans le nom, champ du nom].
     * « …/viewform?usp=pp_url&entry.288269504=ADDOHA » → [« …/viewform?usp=pp_url », « entry.288269504 »].
     *
     * @return array{0: string, 1: string}
     */
    public static function lirePrerempli(string $lien): array
    {
        if (!self::lienGoogleForms($lien)) {
            throw new \DomainException('Collez un lien Google Forms (il commence par https://docs.google.com/forms/).');
        }
        $morceaux = parse_url($lien);
        // Pas de parse_str : il change « entry.123 » en « entry_123 ».
        $champs = [];
        $garde  = [];
        foreach (array_filter(explode('&', $morceaux['query'] ?? '')) as $paire) {
            $cle = urldecode(strtok($paire, '='));
            preg_match('/^entry\.\d+$/', $cle) ? $champs[] = $cle : $garde[] = $paire;
        }
        if (count($champs) !== 1) {
            throw new \DomainException($champs === []
                ? 'Ce lien n\'est pas prérempli : dans Google Forms, ⋮ → « Obtenir le lien prérempli », tapez un nom dans « Votre structure », puis « Obtenir le lien ».'
                : 'Ce lien préremplit plusieurs questions : ne remplissez que « Votre structure ».');
        }

        $base = 'https://' . $morceaux['host'] . $morceaux['path'] . ($garde ? '?' . implode('&', $garde) : '');

        return [$base, $champs[0]];
    }

    /** Seule destination autorisée pour la redirection du lien personnel. */
    public static function lienGoogleForms(?string $lien): bool
    {
        $m = parse_url((string) $lien);

        return ($m['scheme'] ?? null) === 'https'
            && ($m['host'] ?? null) === 'docs.google.com'
            && str_starts_with($m['path'] ?? '', '/forms/');
    }

    /**
     * Nouvelle date limite : tous les liens déjà envoyés expirent désormais
     * ce jour-là, à 23:59:59 (un client relancé garde le même lien).
     */
    public function changerDateLimite(Carbon $date): void
    {
        $edition = $this->edition();
        $edition->update(['date_limite' => $date->toDateString()]);
        $fin = $edition->fresh()->finDuDelai();

        $liens = BarometreEnvoi::where('edition', $this->code())->whereNotNull('public_link_id')->pluck('public_link_id');
        PublicLink::whereIn('id', $liens)->whereNull('revoked_at')->update(['expires_at' => $fin]);

        $this->edition = null;
    }

    // ══════════════════════════════════════════════════════════════
    // LA LISTE
    // ══════════════════════════════════════════════════════════════

    /**
     * Chaque client avec son destinataire et son statut, dans l'ordre
     * alphabétique.
     *
     * @return Collection<int, array{client: Client, envoi: ?BarometreEnvoi, reponse: ?BarometreReponse,
     *         destinataire: array{email: ?string, contact: ?string, contact_id: ?int, source: string},
     *         statut: string, detail: ?string}>
     */
    public function liste(): Collection
    {
        if ($this->liste !== null) {
            return $this->liste;
        }

        $code     = $this->code();
        $clients  = Client::clients()->with('contacts')->orderBy('name')->orderBy('id')->get();
        $envois   = BarometreEnvoi::where('edition', $code)->get()->keyBy('client_id');
        $reponses = BarometreReponse::where('edition', $code)->where('test', false)->whereNotNull('client_id')
            ->orderBy('repondu_le')->orderBy('id')->get()->groupBy('client_id');
        $dossiers = $this->interlocuteursDossiers($clients->pluck('id')->all());
        $ecartees = $this->adressesEcartees();
        $noms     = $clients->pluck('name', 'id');

        // Une adresse ne reçoit qu'une invitation : d'abord celles déjà
        // parties, puis dans l'ordre de la liste.
        $prises = [];
        foreach ($envois as $e) {
            if ($e->estInvite() && !$e->estExclu() && $e->email) {
                $prises[$e->email] ??= (int) $e->client_id;
            }
        }

        return $this->liste = $clients->map(function (Client $client) use ($envois, $reponses, $dossiers, $ecartees, $noms, &$prises) {
            $envoi   = $envois->get($client->id);
            $reponse = $reponses->get($client->id)?->first();
            $dest    = $envoi && $envoi->estInvite()
                ? ['email' => $envoi->email, 'contact' => $envoi->contact_nom, 'contact_id' => $envoi->client_contact_id, 'source' => (string) $envoi->source]
                : $client->destinataireBarometre($dossiers[$client->id] ?? null, $ecartees);

            [$statut, $detail] = $this->statut($client, $envoi, $reponse, $dest, $prises, $noms);

            return ['client' => $client, 'envoi' => $envoi, 'reponse' => $reponse, 'destinataire' => $dest, 'statut' => $statut, 'detail' => $detail];
        })->values();
    }

    /** @return array{0: string, 1: ?string} */
    private function statut(Client $client, ?BarometreEnvoi $envoi, ?BarometreReponse $reponse, array $dest, array &$prises, Collection $noms): array
    {
        if ($envoi?->estExclu()) {
            return ['exclu', $envoi->exclu_motif];
        }
        if ($reponse) {
            return ['repondu', null];
        }
        if ($envoi?->invite_statut === BarometreEnvoi::ENVOYE) {
            return [$envoi->ouvert_le ? 'ouvert' : 'invite', $envoi->erreur];
        }
        if ($envoi?->invite_statut === BarometreEnvoi::DEMANDE) {
            return ['en_cours', null];
        }
        if ($dest['source'] === 'aucun') {
            $ecartees = $this->adressesEcarteesDe($client);

            return ['sans_email', $ecartees
                ? 'Adresse écartée des envois : ' . collect($ecartees)->map(fn ($m, $e) => "{$e} ({$m})")->implode(', ')
                    . '. Corriger l\'adresse sur la fiche, ou donner un autre interlocuteur.'
                : 'Ajoutez l\'e-mail d\'un interlocuteur sur la fiche du client.'];
        }
        if ($dest['source'] === 'a_choisir') {
            return ['a_choisir', 'Plusieurs interlocuteurs, aucun indice : cochez le principal sur la fiche du client.'];
        }
        $autre = $prises[$dest['email']] ?? null;
        if ($autre !== null && $autre !== (int) $client->id) {
            return ['doublon', 'Même adresse que ' . ($noms[$autre] ?? 'un autre client') . ' : un seul mail.'];
        }
        $prises[$dest['email']] = (int) $client->id;

        return $envoi?->invite_statut === BarometreEnvoi::ECHEC ? ['echec', $envoi->erreur] : ['pret', null];
    }

    /**
     * Interlocuteur du dernier dossier de chaque client : sa dernière
     * campagne, sinon sa dernière réservation, sinon son dernier devis
     * (mesure du 16/11). Trois requêtes pour toute la liste.
     *
     * @return array<int, int>  client_id → client_contact_id
     */
    private function interlocuteursDossiers(array $ids): array
    {
        $trouves = [];
        // Du moins prioritaire au plus prioritaire, du plus ancien au plus
        // récent : la dernière valeur écrite l'emporte.
        foreach ([[Quote::class, 'created_at'], [Reservation::class, 'created_at'], [Campaign::class, 'start_date']] as [$modele, $date]) {
            foreach (array_chunk($ids, 500) as $lot) {
                $modele::query()->whereIn('client_id', $lot)->whereNotNull('client_contact_id')
                    ->orderBy($date)->orderBy('id')->get(['id', 'client_id', 'client_contact_id'])
                    ->each(function ($d) use (&$trouves, $modele) {
                        $trouves[$modele][(int) $d->client_id] = (int) $d->client_contact_id;
                    });
            }
        }

        $resultat = [];
        foreach ($ids as $id) {
            $resultat[$id] = $trouves[Campaign::class][$id] ?? $trouves[Reservation::class][$id] ?? $trouves[Quote::class][$id] ?? null;
        }

        return array_filter($resultat);
    }

    /** @return array<string, true> adresses écartées des envois commerciaux */
    /**
     * @return array<string, string> adresse écartée des envois commerciaux → motif
     *         (« Adresse invalide », « Désinscrit », « Signalé comme spam », « Exclu manuellement »)
     */
    private function adressesEcartees(): array
    {
        if ($this->ecartees !== null) {
            return $this->ecartees;
        }

        return $this->ecartees = DiffusionDesinscription::query()->get(['email', 'motif'])
            ->mapWithKeys(fn ($d) => [mb_strtolower(trim((string) $d->email)) => $d->libelleMotif()])->all();
    }

    /**
     * Adresses de ce client que la règle a mises de côté parce qu'elles sont
     * écartées des envois commerciaux (18/11 : 12ÈME LIEU s'affichait
     * « Aucun e-mail » alors que son interlocuteur en a une).
     *
     * @return array<string, string> adresse → motif
     */
    private function adressesEcarteesDe(Client $client): array
    {
        $ecartees = $this->adressesEcartees();
        $trouvees = [];
        foreach ([...$client->contacts->pluck('email')->all(), $client->email] as $email) {
            $email = mb_strtolower(trim((string) $email));
            if ($email !== '' && isset($ecartees[$email])) {
                $trouvees[$email] = $ecartees[$email];
            }
        }

        return $trouvees;
    }

    /** Chiffres du haut de l'écran. */
    public function compteurs(): array
    {
        $l = $this->liste();
        $par = $l->countBy('statut');
        $envois = $l->pluck('envoi')->filter();

        return [
            'a_envoyer'   => $l->whereIn('statut', self::A_ENVOYER)->count(),
            'en_cours'    => (int) ($par['en_cours'] ?? 0) + $envois->where('relance_statut', BarometreEnvoi::DEMANDE)->count(),
            'invites'     => $envois->where('invite_statut', BarometreEnvoi::ENVOYE)->count(),
            'ouverts'     => $envois->filter(fn ($e) => $e->ouvert_le)->count(),
            'repondus'    => (int) ($par['repondu'] ?? 0),
            'relances'    => $envois->where('relance_statut', BarometreEnvoi::ENVOYE)->count(),
            'a_relancer'  => $this->aRelancer()->count(),
            'exclus'      => (int) ($par['exclu'] ?? 0),
            'sans_email'  => (int) ($par['sans_email'] ?? 0),
            'a_choisir'   => (int) ($par['a_choisir'] ?? 0),
            'doublons'    => (int) ($par['doublon'] ?? 0),
            'echecs'      => (int) ($par['echec'] ?? 0),
            'clients'     => $l->count(),
        ];
    }

    /**
     * Fichier « contacts à compléter » (18/11) : les clients sans
     * destinataire, triés par commercial, avec ce que Panora connaît déjà.
     * Les commerciaux remplissent les colonnes jaunes, puis la fiche client.
     *
     * @return list<array{commercial: string, client: string, probleme: string, connus: string, fiche: string}>
     */
    public function aCompleter(): array
    {
        $problemes = [
            'sans_email' => 'Aucun e-mail : indiquer l\'interlocuteur à qui envoyer le questionnaire',
            'a_choisir'  => 'Plusieurs interlocuteurs, aucun indice : indiquer celui à qui envoyer',
        ];

        return $this->liste()
            ->filter(fn ($l) => in_array($l['statut'], self::SANS_DESTINATAIRE, true))
            ->map(function ($l) use ($problemes) {
                $client   = $l['client'];
                $ecartees = $this->adressesEcarteesDe($client);
                $marque   = function (?string $email) use ($ecartees) {
                    $cle = mb_strtolower(trim((string) $email));

                    return $email && isset($ecartees[$cle]) ? "{$email} (écartée : {$ecartees[$cle]})" : $email;
                };
                $connus = $client->contacts->map(fn ($ct) => implode(' · ', array_filter([$ct->name, $marque($ct->email), $ct->phone])))
                    ->filter()->values()->all();
                if ($client->email) {
                    $connus[] = 'Fiche : ' . $marque($client->email);
                }
                if ($l['statut'] === 'sans_email' && $ecartees) {
                    $problemes['sans_email'] = 'Adresse écartée des envois ('
                        . collect($ecartees)->unique()->implode(', ')
                        . ') : vérifier et corriger l\'adresse, ou donner un autre interlocuteur';
                }

                return [
                    'commercial' => $client->user?->name ?: 'Sans commercial attitré',
                    'client'     => (string) $client->name,
                    'probleme'   => $problemes[$l['statut']]
                        ?? ($l['detail'] . ' Donner un autre interlocuteur si l\'entreprise doit répondre séparément.'),
                    'connus'     => $connus ? implode("\n", $connus) : 'Aucun interlocuteur',
                    'fiche'      => route('admin.clients.show', $client),
                ];
            })
            ->sortBy(fn ($r) => mb_strtolower($r['commercial'] . '|' . $r['client']))
            ->values()
            ->all();
    }

    /** Invités qui n'ont pas répondu et pas encore été relancés. */
    public function aRelancer(): Collection
    {
        return $this->liste()->filter(fn ($l) => in_array($l['statut'], ['invite', 'ouvert'], true)
            && in_array($l['envoi']->relance_statut, [null, BarometreEnvoi::ECHEC], true));
    }

    // ══════════════════════════════════════════════════════════════
    // DEMANDES D'ENVOI (boutons de l'écran)
    // ══════════════════════════════════════════════════════════════

    /**
     * « Envoyer à tous » : chaque client « à envoyer » passe en demande, avec
     * son adresse figée. Refusé si le nombre n'est plus celui affiché.
     *
     * @return int nombre de mails demandés
     */
    public function demanderInvitations(int $attendu, ?User $par = null): int
    {
        $this->verifierEnvoiOuvert();

        return DB::transaction(function () use ($attendu, $par) {
            $prets = $this->liste()->whereIn('statut', self::A_ENVOYER);
            $this->verifierNombre($prets->count(), $attendu);

            foreach ($prets as $l) {
                BarometreEnvoi::updateOrCreate(
                    ['edition' => $this->code(), 'client_id' => $l['client']->id],
                    [
                        'email'             => $l['destinataire']['email'],
                        'contact_nom'       => $l['destinataire']['contact'],
                        'client_contact_id' => $l['destinataire']['contact_id'],
                        'source'            => $l['destinataire']['source'],
                        'invite_statut'     => BarometreEnvoi::DEMANDE,
                        'invite_par'        => $par?->id,
                        'erreur'            => null,
                    ]
                );
            }
            $this->liste = null;

            return $prets->count();
        });
    }

    /** « Relancer » : les invités sans réponse, une seule fois. */
    public function demanderRelances(int $attendu, ?User $par = null): int
    {
        $this->verifierEnvoiOuvert();

        return DB::transaction(function () use ($attendu, $par) {
            $cibles = $this->aRelancer();
            $this->verifierNombre($cibles->count(), $attendu);

            BarometreEnvoi::whereIn('id', $cibles->pluck('envoi.id'))->update([
                'relance_statut' => BarometreEnvoi::DEMANDE,
                'relance_par'    => $par?->id,
                'erreur'         => null,
            ]);
            $this->liste = null;

            return $cibles->count();
        });
    }

    /**
     * Aperçu du renvoi (2026-11-24) : qui a reçu l'invitation, à qui la fiche
     * l'enverrait maintenant, et si le renvoi est possible. Source unique de
     * la fenêtre « Renvoyer » ET du renvoi lui-même : les deux ne peuvent pas
     * se contredire.
     *
     * cas : nouveau | meme_personne (même nom, autre adresse) | identique
     *       (la fiche n'a pas changé) | aucune | a_choisir | doublon |
     *       pas_invite | ferme
     *
     * @return array{possible: bool, cas: string, motif: ?string,
     *               ancien: array{nom: ?string, email: ?string},
     *               nouveau: ?array{nom: ?string, email: ?string},
     *               destinataire: ?array}
     */
    public function apercuRenvoi(Client $client): array
    {
        $ligne  = $this->liste()->first(fn ($l) => (int) $l['client']->id === (int) $client->id);
        $envoi  = $ligne['envoi'] ?? null;
        $ancien = ['nom' => $envoi?->contact_nom, 'email' => $envoi?->email];
        $refus  = fn (string $cas, string $motif, ?array $nouveau = null) => [
            'possible' => false, 'cas' => $cas, 'motif' => $motif, 'ancien' => $ancien, 'nouveau' => $nouveau, 'destinataire' => null,
        ];

        try {
            $this->verifierEnvoiOuvert();
        } catch (\DomainException $e) {
            return $refus('ferme', $e->getMessage());
        }
        if (!$ligne || !in_array($ligne['statut'], ['invite', 'ouvert'], true)) {
            return $refus('pas_invite', "{$client->name} : seule une invitation déjà partie, sans réponse, peut être renvoyée.");
        }

        $fiche = $ligne['client'];
        $dest  = $fiche->destinataireBarometre(
            $this->interlocuteursDossiers([(int) $fiche->id])[(int) $fiche->id] ?? null,
            $this->adressesEcartees()
        );
        $nouveau = $dest['email'] ? ['nom' => $dest['contact'], 'email' => $dest['email']] : null;

        if (!$dest['email']) {
            return $dest['source'] === 'a_choisir'
                ? $refus('a_choisir', "{$client->name} : plusieurs interlocuteurs sur la fiche, aucun n'est principal. Cochez le principal, puis renvoyez.")
                : $refus('aucune', "{$client->name} : la fiche ne donne aucune adresse utilisable. Ajoutez le nouvel interlocuteur, puis renvoyez.");
        }
        if (mb_strtolower($dest['email']) === mb_strtolower((string) $envoi->email)) {
            return $refus('identique', "{$client->name} : la fiche donne toujours {$envoi->email}. Corrigez d'abord le contact principal sur la fiche du client, puis renvoyez.", $nouveau);
        }
        $autre = BarometreEnvoi::where('edition', $this->code())->where('client_id', '!=', $client->id)
            ->whereNull('exclu_motif')->whereNotNull('invite_statut')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($dest['email'])])->first();
        if ($autre) {
            return $refus('doublon', "{$dest['email']} reçoit déjà le baromètre pour " . ($autre->client?->name ?? 'un autre client') . ' : un seul mail par adresse.', $nouveau);
        }

        $memeNom = $dest['contact'] && $envoi->contact_nom
            && mb_strtolower(trim($dest['contact'])) === mb_strtolower(trim((string) $envoi->contact_nom));

        return [
            'possible'     => true,
            'cas'          => $memeNom ? 'meme_personne' : 'nouveau',
            'motif'        => null,
            'ancien'       => $ancien,
            'nouveau'      => $nouveau,
            'destinataire' => $dest,
        ];
    }

    /**
     * « Renvoyer au contact de la fiche » (2026-11-24). L'adresse d'un
     * client est figée à l'invitation : quand l'interlocuteur a quitté
     * l'entreprise (réponse automatique « ne fait plus partie… »), on corrige
     * la fiche du client, puis ce bouton renvoie l'invitation — même lien
     * personnel — au contact que la fiche donne maintenant. La relance
     * suivra la nouvelle adresse. Règles : apercuRenvoi().
     */
    public function renvoyerAuContactDeLaFiche(Client $client, ?User $par = null): BarometreEnvoi
    {
        return DB::transaction(function () use ($client, $par) {
            $apercu = $this->apercuRenvoi($client);
            if (!$apercu['possible']) {
                throw new \DomainException($apercu['motif']);
            }

            $dest = $apercu['destinataire'];
            /** @var BarometreEnvoi $envoi */
            $envoi = BarometreEnvoi::where('edition', $this->code())->where('client_id', $client->id)->firstOrFail();

            Log::info('barometre.renvoi_contact', [
                'client' => $client->id, 'ancienne' => $envoi->email, 'nouvelle' => $dest['email'], 'par' => $par?->id,
                'ouvertures_anciennes' => (int) $envoi->ouvertures,
            ]);

            $envoi->forceFill([
                'email'             => $dest['email'],
                'contact_nom'       => $dest['contact'],
                'client_contact_id' => $dest['contact_id'],
                'source'            => $dest['source'],
                'invite_statut'     => BarometreEnvoi::DEMANDE,
                'invite_par'        => $par?->id,
                // La relance repartira vers la nouvelle adresse.
                'relance_statut'    => null,
                'erreur'            => null,
                // Les ouvertures comptées jusqu'ici venaient de l'ancienne
                // boîte (2026-11-24 : « A ouvert le lien » s'affichait pour le
                // nouveau contact). On ne compte plus que les siennes.
                'ouvert_le'         => null,
                'ouvertures'        => 0,
            ])->save();
            $this->liste = null;

            return $envoi;
        });
    }

    private function verifierEnvoiOuvert(): void
    {
        if (!$this->enProduction()) {
            throw new \DomainException('Mode test : seul « Envoyer un test » fonctionne. Les clients ne recevront rien tant que BAROMETRE_MODE n\'est pas sur production.');
        }
        if ($m = $this->reglageManquant()) {
            throw new \DomainException($m);
        }
        if ($this->edition()->estClose()) {
            throw new \DomainException('La date limite est passée : le questionnaire est clos.');
        }
    }

    private function verifierNombre(int $trouve, int $attendu): void
    {
        if ($trouve === 0) {
            throw new \DomainException('Personne à qui envoyer.');
        }
        if ($trouve !== $attendu) {
            throw new \DomainException("La liste a changé depuis l'ouverture de la page : {$trouve} destinataires au lieu de {$attendu}. Rechargez la page et vérifiez avant d'envoyer.");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ENVOI (tâche planifiée barometre:envoyer)
    // ══════════════════════════════════════════════════════════════

    /**
     * Envoie les demandes en attente, par lots. Une demande refusée (mode
     * test, date passée, quota Brevo) passe en échec, avec la raison : elle
     * repart au prochain clic, jamais toute seule.
     *
     * @return array{invitations: int, relances: int, echecs: int}
     */
    public function traiterDemandes(): array
    {
        $bilan = ['invitations' => 0, 'relances' => 0, 'echecs' => 0];
        $demandes = BarometreEnvoi::where('edition', $this->code())->whereNull('exclu_motif')
            ->where(fn ($q) => $q->where('invite_statut', BarometreEnvoi::DEMANDE)->orWhere('relance_statut', BarometreEnvoi::DEMANDE))
            ->orderBy('id')->limit(max(1, (int) config('barometre.lot')))->get();

        if ($demandes->isEmpty()) {
            return $bilan;
        }

        $refus = !$this->enProduction()
            ? 'Envoi refusé : le baromètre est en mode test (BAROMETRE_MODE).'
            : ($this->reglageManquant()
                ?? ($this->edition()->estClose() ? 'Envoi refusé : la date limite est passée.' : null)
                ?? $this->quotaInsuffisant($demandes->count()));

        foreach ($demandes as $envoi) {
            $relance = $envoi->invite_statut !== BarometreEnvoi::DEMANDE;
            $ok = $refus === null && $this->envoyer($envoi, $relance);
            if ($refus !== null) {
                $this->marquerEchec($envoi, $relance, $refus);
            }
            $bilan[$ok ? ($relance ? 'relances' : 'invitations') : 'echecs']++;
        }

        return $bilan;
    }

    private function quotaInsuffisant(int $n): ?string
    {
        try {
            $restants = $this->brevo->envoisRestants();
        } catch (\Throwable $e) {
            Log::warning('barometre.quota_illisible', ['erreur' => $e->getMessage()]);

            return null;
        }

        return $restants !== null && $restants < $n
            ? "Envoi refusé : il reste {$restants} envois chez Brevo pour {$n} mails. Rien n'est parti."
            : null;
    }

    private function marquerEchec(BarometreEnvoi $envoi, bool $relance, string $raison): void
    {
        $envoi->forceFill([$relance ? 'relance_statut' : 'invite_statut' => BarometreEnvoi::ECHEC, 'erreur' => $raison])->save();
    }

    /** Un mail. Brevo sans réponse = compté comme envoyé, jamais renvoyé (règle du 2026-11-03). */
    private function envoyer(BarometreEnvoi $envoi, bool $relance): bool
    {
        [$statut, $le, $brevoId] = $relance
            ? ['relance_statut', 'relance_le', 'relance_brevo_id']
            : ['invite_statut', 'invite_le', 'invite_brevo_id'];

        $client = $envoi->client;
        if (!$client || !filter_var($envoi->email, FILTER_VALIDATE_EMAIL)) {
            $this->marquerEchec($envoi, $relance, 'Client ou adresse introuvable.');

            return false;
        }

        $mail = $this->mail($client, $envoi->contact_nom, $this->lienPersonnel($envoi)->publicUrl(), $relance, $envoi->email);

        try {
            $id = $this->brevo->envoyerEmail($this->payload($mail, $envoi->email));
        } catch (\Throwable $e) {
            if (BrevoClient::sansReponse($e)) {
                $reserve = 'Brevo n\'a pas répondu : le mail est peut-être parti, il ne sera pas renvoyé. (' . $e->getMessage() . ')';
                JournalMails::brevo($mail->tagBrevo(), $mail->sujet(), $envoi->email, [], null, null, $reserve, $client);
                $envoi->forceFill([$statut => BarometreEnvoi::ENVOYE, $le => now(), 'erreur' => $reserve])->save();

                return true;
            }
            JournalMails::brevo($mail->tagBrevo(), $mail->sujet(), $envoi->email, [], null, $e->getMessage(), null, $client);
            Log::warning('barometre.envoi_refuse', ['envoi' => $envoi->id, 'erreur' => $e->getMessage()]);
            $this->marquerEchec($envoi, $relance, mb_substr($e->getMessage(), 0, 2000));

            return false;
        }

        JournalMails::brevo($mail->tagBrevo(), $mail->sujet(), $envoi->email, [], null, null, null, $client);
        $envoi->forceFill([$statut => BarometreEnvoi::ENVOYE, $le => now(), $brevoId => $id, 'erreur' => null])->save();

        return true;
    }

    /**
     * « Envoyer un test » : le mail exact d'un client, à l'adresse de la
     * personne connectée, avec un lien de test (il ouvre le questionnaire
     * au nom « TEST — <client> » : la réponse ne compte pour personne).
     */
    public function envoyerTest(Client $client, string $adresse, bool $relance = false): void
    {
        if ($m = $this->reglageManquant()) {
            throw new \DomainException($m);
        }
        if ($this->edition()->estClose()) {
            throw new \DomainException('La date limite est passée : le lien de test serait déjà expiré.');
        }
        if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('Votre compte n\'a pas d\'adresse e-mail valide.');
        }

        $ligne = $this->liste()->first(fn ($l) => (int) $l['client']->id === (int) $client->id);
        $dest  = $ligne['destinataire'] ?? $client->destinataireBarometre();
        $note  = $dest['email']
            ? 'En production, il partirait à ' . $dest['email'] . ($dest['contact'] ? ' (' . $dest['contact'] . ')' : '') . '.'
            : 'En production, ce client ne le recevrait pas : ' . mb_strtolower(self::STATUTS[$ligne['statut'] ?? 'sans_email'][1]) . '.';

        $fin  = $this->edition()->finDuDelai();
        $lien = PublicLinkService::create($client, self::TYPE_LIEN, $fin->lt(now()->addDays(7)) ? $fin : now()->addDays(7), $client->id, null,
            ['edition' => $this->code(), 'test' => true]);

        $mail = $this->mail($client, $dest['contact'], $lien->publicUrl(), $relance, $adresse, $note);
        $this->brevo->envoyerEmail($this->payload($mail, $adresse));
        JournalMails::brevo($mail->tagBrevo() . '-test', $mail->sujet(), $adresse, [], null, null, null, $client);
    }

    /**
     * Le mail d'un client. Un « nom » d'interlocuteur qui n'en est pas un
     * (« contact », « audreyasseke », « Comptabilité RCG ») n'est pas salué :
     * « Bonjour MANOOVA, » plutôt que « Bonjour contact (MANOOVA), »
     * (décision du 17/11, Salutation::personneNommable).
     */
    public function mail(Client $client, ?string $contact, string $lien, bool $relance, ?string $email, ?string $noteTest = null): BarometreMail
    {
        return new BarometreMail(
            $client, \App\Support\Salutation::personneNommable($contact, $email, $client->name), $lien,
            $this->edition()->date_limite, $relance,
            $email ? DiffusionDesinscription::lien($email) : null, $noteTest, $this->signature(),
        );
    }

    /** Requête de l'API transactionnelle Brevo (même forme que le mail J-7). */
    public function payload(BarometreMail $mail, string $to): array
    {
        $payload = [
            'sender'      => array_filter([
                'email' => config('brevo.sender.email'),
                'name'  => $this->expediteur(),
            ]),
            'to'          => [array_filter(['email' => $to, 'name' => $mail->contact])],
            'subject'     => $mail->sujet(),
            'htmlContent' => $mail->htmlEnLigne(),
            'textContent' => $mail->texte(),
            'tags'        => [$mail->tagBrevo()],
        ];
        if ($repondreA = $this->repondreA()) {
            $payload['replyTo'] = ['email' => $repondreA];
        }
        if ($entetes = MailClientCampagne::entetesDesinscription($mail->lienDesinscription)) {
            $payload['headers'] = $entetes;
        }

        return $payload;
    }

    // ══════════════════════════════════════════════════════════════
    // LIEN PERSONNEL
    // ══════════════════════════════════════════════════════════════

    /** Le lien du client : créé au premier envoi, gardé pour la relance. */
    public function lienPersonnel(BarometreEnvoi $envoi): PublicLink
    {
        $fin  = $this->edition()->finDuDelai();
        $lien = $envoi->public_link_id ? PublicLink::find($envoi->public_link_id) : null;

        if ($lien && !$lien->isRevoked()) {
            if (!$lien->expires_at || !$lien->expires_at->equalTo($fin)) {
                $lien->forceFill(['expires_at' => $fin])->save();
            }

            return $lien;
        }

        $lien = PublicLinkService::create($envoi, self::TYPE_LIEN, $fin, (int) $envoi->client_id, null, ['edition' => $this->code()]);
        $envoi->forceFill(['public_link_id' => $lien->id])->save();

        return $lien;
    }

    /**
     * Clic sur un lien valide (PublicLinkController) : noté, puis adresse
     * du questionnaire au nom du client. null si le lien ne mène à rien.
     */
    public function ouvrir(PublicLink $lien): ?string
    {
        // Jamais de redirection ailleurs que sur Google Forms.
        if (!self::lienGoogleForms($this->formulaire()) || !$this->champStructure()) {
            return null;
        }
        $cible = $lien->target;

        if ($cible instanceof BarometreEnvoi && !($lien->metadata['test'] ?? false)) {
            BarometreEnvoi::whereKey($cible->id)->update([
                'ouvertures' => DB::raw('ouvertures + 1'),
                'ouvert_le'  => $cible->ouvert_le ?? now(),
            ]);
            $nom = $cible->client?->name;

            return $nom ? $this->lienFormulaire($nom) : null;
        }
        if ($cible instanceof Client && ($lien->metadata['test'] ?? false)) {
            return $this->lienFormulaire(config('barometre.prefixe_test') . $cible->name);
        }

        return null;
    }

    public function lienFormulaire(string $structure): string
    {
        $base = (string) $this->formulaire();

        return $base . (str_contains($base, '?') ? '&' : '?')
            . $this->champStructure() . '=' . rawurlencode($structure);
    }

    // ══════════════════════════════════════════════════════════════
    // EXCLUSIONS
    // ══════════════════════════════════════════════════════════════

    /** Retire un client de l'envoi. Une demande pas encore partie est annulée, un lien envoyé coupé. */
    public function exclure(Client $client, string $motif, ?User $par = null): BarometreEnvoi
    {
        $envoi = BarometreEnvoi::firstOrNew(['edition' => $this->code(), 'client_id' => $client->id]);
        $envoi->forceFill([
            'exclu_motif' => mb_substr(trim($motif) !== '' ? trim($motif) : 'Retiré à la main', 0, 255),
            'exclu_le'    => now(),
            'exclu_par'   => $par?->id,
        ]);
        foreach (['invite_statut', 'relance_statut'] as $col) {
            if ($envoi->{$col} === BarometreEnvoi::DEMANDE) {
                $envoi->{$col} = null;
            }
        }
        $envoi->save();

        if ($envoi->public_link_id && ($lien = PublicLink::find($envoi->public_link_id)) && !$lien->isRevoked()) {
            // Raison interne : la page du client n'affiche jamais ce texte (PublicLinkController).
            PublicLinkService::revoke($lien, 'Retiré du baromètre');
        }
        $this->liste = null;

        return $envoi;
    }

    public function reintegrer(Client $client): void
    {
        $envoi = BarometreEnvoi::where('edition', $this->code())->where('client_id', $client->id)->first();
        if (!$envoi) {
            return;
        }
        $envoi->forceFill(['exclu_motif' => null, 'exclu_le' => null, 'exclu_par' => null])->save();

        if ($envoi->public_link_id) {
            PublicLink::whereKey($envoi->public_link_id)->where('revoked_reason', 'Retiré du baromètre')
                ->update(['revoked_at' => null, 'revoked_by_user_id' => null, 'revoked_reason' => null]);
        }
        $this->liste = null;
    }

    /**
     * Exclusion par nom (commande barometre:exclure) : nom exact, sans tenir
     * compte des majuscules, accents et espaces.
     *
     * @return array{exclus: list<string>, introuvables: list<string>, ambigus: list<string>}
     */
    public function exclureParNoms(array $noms, string $motif, ?User $par = null): array
    {
        $tous = Client::clients()->get(['id', 'name']);
        $r = ['exclus' => [], 'introuvables' => [], 'ambigus' => []];

        foreach ($noms as $nom) {
            $trouves = $tous->filter(fn ($c) => self::normaliser($c->name) === self::normaliser($nom));
            if ($trouves->count() === 1) {
                $this->exclure($trouves->first(), $motif, $par);
                $r['exclus'][] = $trouves->first()->name;
            } else {
                $r[$trouves->isEmpty() ? 'introuvables' : 'ambigus'][] = $nom;
            }
        }

        return $r;
    }

    // ══════════════════════════════════════════════════════════════
    // RÉPONSES (script Google)
    // ══════════════════════════════════════════════════════════════

    /**
     * Une réponse signalée par Google. Rejouer la même référence ne crée
     * rien. Le client est reconnu à son nom ; sinon la réponse attend un
     * rattachement à la main. Les réponses « TEST — … » ne comptent pas.
     */
    public function enregistrerReponse(string $reference, string $structure, ?Carbon $quand = null): BarometreReponse
    {
        $structure = mb_substr(trim($structure), 0, 255);
        $existante = BarometreReponse::where('edition', $this->code())->where('reference', $reference)->first();
        if ($existante) {
            return $existante;
        }

        $test = str_starts_with(self::normaliser($structure) . ' ', self::normaliser((string) config('barometre.prefixe_test')) . ' ');

        try {
            $reponse = BarometreReponse::create([
                'edition'    => $this->code(),
                'reference'  => $reference,
                'structure'  => $structure !== '' ? $structure : '(vide)',
                'repondu_le' => $quand ?? now(),
                'client_id'  => $test ? null : $this->reconnaitre($structure),
                'test'       => $test,
            ]);
        } catch (QueryException $e) {
            // Deux envois simultanés de la même réponse : l'autre l'a créée.
            $reponse = BarometreReponse::where('edition', $this->code())->where('reference', $reference)->first() ?? throw $e;
        }
        $this->liste = null;

        return $reponse;
    }

    /** Client dont le nom correspond, s'il n'y en a qu'un. */
    public function reconnaitre(string $structure): ?int
    {
        $cle = self::normaliser($structure);
        if ($cle === '') {
            return null;
        }

        foreach ([Client::clients(), Client::query()] as $requete) {
            $ids = $requete->get(['id', 'name'])->filter(fn ($c) => self::normaliser($c->name) === $cle)->pluck('id');
            if ($ids->isNotEmpty()) {
                return $ids->count() === 1 ? (int) $ids->first() : null;
            }
        }

        return null;
    }

    public function rattacher(BarometreReponse $reponse, ?Client $client, ?User $par = null): void
    {
        $reponse->update(['client_id' => $client?->id, 'test' => false, 'rattache_par' => $par?->id]);
        $this->liste = null;
    }

    /** @return Collection<int, BarometreReponse> */
    public function reponsesNonReconnues(): Collection
    {
        return BarometreReponse::where('edition', $this->code())->where('test', false)->whereNull('client_id')
            ->orderByDesc('repondu_le')->get();
    }

    public function nombreReponses(): array
    {
        $q = BarometreReponse::where('edition', $this->code());

        return ['total' => (clone $q)->where('test', false)->count(), 'tests' => (clone $q)->where('test', true)->count()];
    }

    /** « Société Générale  C.I » → « SOCIETE GENERALE C I ». */
    public static function normaliser(string $nom): string
    {
        $nom = mb_strtoupper(Str::ascii($nom));

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $nom));
    }
}
