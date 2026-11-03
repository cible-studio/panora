<?php

namespace App\Services\Diffusion;

use App\Mail\DiffusionDisponibilitesMail;
use App\Models\Client;
use App\Models\Commune;
use App\Models\DiffusionDesinscription;
use App\Models\DiffusionEnvoi;
use App\Models\Panel;
use App\Models\User;
use App\Services\Brevo\BrevoClient;
use App\Services\Brevo\BrevoException;
use App\Services\DisponibilitesPdfBuilder;
use App\Services\NotificationMailer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Diffusion des disponibilités aux clients — SOURCE UNIQUE.
 *
 * Spécification validée le 2026-09-29 (docs/DIFFUSION_DISPONIBILITES.md) :
 *
 *   • Deux catalogues par mois, envoyés AVANT la période qu'ils
 *     annoncent : celui du 1er entre le 20 et le 25 du mois précédent,
 *     celui du 15 entre le 1er et le 5 du mois. Premier jour ouvrable suivant
 *     (cf. DiffusionCalendrier).
 *   • Parc CIBLE uniquement : panneaux libres sur toute la période, plus
 *     ceux qui se libèrent en cours de période (avec leur date). Exclus :
 *     maintenance, options, occupés jusqu'à la fin de la période.
 *   • Pas de prix. Un seul PDF « images » par envoi, en lien dans le mail.
 *   • Tous les clients d'office, désinscription possible (gérée par Brevo).
 *   • Confirmation interne à commercial@ + tous les Media Planners
 *     après chaque envoi, et alerte en cas d'échec.
 *
 * Garde-fou : en mode 'test' (défaut, cf. config/diffusion.php), TOUS
 * les envois partent vers la liste Brevo « Tests internes ».
 */
class DiffusionDisponibilitesService
{
    private ?DiffusionCalendrier $calendrier = null;

    public function __construct(
        private JoursFeriesService $joursFeries,
        private DisponibilitesPdfBuilder $pdfBuilder,
        private BrevoClient $brevo,
        private NotificationMailer $mailer,
    ) {}

    public function calendrier(): DiffusionCalendrier
    {
        return $this->calendrier ??= $this->joursFeries->calendrier(now());
    }

    public function estModeTest(): bool
    {
        return config('diffusion.mode') !== 'production';
    }

    /**
     * Cet envoi est-il un test ? Oui s'il vient du bouton « Envoyer un
     * test », OU si le serveur est en mode test. Source unique (2026-11-02) :
     * la liste visée, la synchronisation, l'objet et les destinataires
     * internes en dépendent.
     *
     * Incident du 2026-11-02 : la liste ne regardait que le mode du
     * serveur. Un « Envoyer un test » avec DIFFUSION_MODE=production a
     * recopié les 830 clients dans la liste clients et leur a envoyé le
     * catalogue (300 partis avant que Brevo ne suspende).
     */
    public function estEnvoiTest(DiffusionEnvoi $envoi): bool
    {
        return $envoi->mode === DiffusionEnvoi::MODE_TEST || $this->estModeTest();
    }

    // ══════════════════════════════════════════════════════════════
    // CE QU'ON ENVOIE
    // ══════════════════════════════════════════════════════════════

    /**
     * Panneaux à présenter aux clients pour une période, prêts pour le PDF.
     *
     * @return Collection<int, array>
     */
    public function panneauxDiffusables(CarbonInterface $debut, CarbonInterface $fin): Collection
    {
        // Parc CIBLE uniquement (pas de panneaux de régies partenaires),
        // trié par commune puis référence : le PDF regroupe par commune.
        // Exclus (2026-10-22) : les panneaux dont le renouvellement a été
        // confirmé par le MP — ils restent au client actuel.
        $ids = Panel::query()
            ->whereNull('panels.deleted_at')
            ->whereNotIn('panels.id', RenouvellementService::panneauxRetenus())
            ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
            ->orderBy('communes.name')
            ->orderBy('panels.reference')
            ->pluck('panels.id')
            ->all();

        if (!$ids) {
            return collect();
        }

        $lignes = $this->pdfBuilder->lignes(
            $ids,
            [],
            $debut->toDateString(),
            $fin->toDateString(),
            photosCompactes: true
        );

        return self::filtrerDiffusables($lignes, $fin->toDateString());
    }

    /**
     * Règle de sélection validée : libres sur toute la période, ou qui se
     * libèrent AVANT la fin de la période. Fonction pure, testée seule.
     *
     * release_date = DERNIER jour d'occupation → libre le lendemain. Un
     * panneau occupé jusqu'au 29 d'un mois de 30 jours se libère le 30 :
     * il est présenté.
     */
    public static function filtrerDiffusables(Collection $lignes, string $finPeriode): Collection
    {
        return $lignes->filter(function ($l) use ($finPeriode) {
            $statut = $l['display_status'] ?? null;

            if (in_array($statut, ['libre', 'disponible'], true)) {
                return true;
            }

            if (in_array($statut, ['occupe', 'confirme'], true)) {
                $liberation = $l['release_date'] ?? null;

                return $liberation
                    && Carbon::parse($liberation)->toDateString() < $finPeriode;
            }

            // maintenance, option_periode, statut inconnu : exclus.
            return false;
        })->values();
    }

    /**
     * Répartition du catalogue en deux zones — SOURCE UNIQUE de la règle
     * « Abidjan ou intérieur du pays » pour la diffusion (2026-10-29).
     *
     * La règle elle-même n'est pas écrite ici : elle vient de
     * Commune::nameIsAbidjan(), la liste blanche officielle du District
     * Autonome d'Abidjan déjà utilisée par les rapports, les taxes et les
     * KPI. Les chiffres du mail concordent donc avec le filtre
     * « Abidjan / Intérieur » des rapports.
     *
     * Fonction pure, testée seule. Les lignes portent le NOM de la commune
     * (cf. PdfExportService::enrichPanel), pas son identifiant.
     *
     * @return array{abidjan: Collection<int, array>, interieur: Collection<int, array>}
     */
    public static function repartirParZone(Collection $lignes): array
    {
        [$abidjan, $interieur] = $lignes->partition(
            fn ($l) => Commune::nameIsAbidjan($l['commune'] ?? null)
        );

        return [
            DiffusionEnvoi::ZONE_ABIDJAN   => $abidjan->values(),
            DiffusionEnvoi::ZONE_INTERIEUR => $interieur->values(),
        ];
    }

    /**
     * Destinataires : chaque interlocuteur au statut client des entreprises
     * clientes (2026-10-28 ; avant : une seule adresse par client), à
     * défaut l'adresse de la fiche. Aucun prospect. Exclus : adresses
     * invalides, désinscrits, adresses mortes, doublons.
     *
     * @return Collection<int, array{email: string, contact: ?string, societe: string, client_id: int}>
     */
    public function destinataires(): Collection
    {
        $exclues = $this->adressesExclues();

        // Chaque adresse au statut client, plus seulement une par entreprise ;
        // aucun prospect (2026-10-28, cf. Client::destinatairesDiffusion).
        return Client::query()
            ->clients()
            ->with(['contacts' => fn($q) => $q->select('id', 'client_id', 'name', 'email', 'is_primary', 'statut', 'dernier_echange')])
            ->orderBy('name')
            ->get(['id', 'name', 'contact_name', 'email', 'statut_commercial', 'email_dernier_echange'])
            ->flatMap(fn(Client $c) => array_map(fn($d) => $d + [
                'societe'   => $c->name,
                'client_id' => $c->id,
            ], $c->destinatairesDiffusion()))
            ->filter(fn($d) => filter_var($d['email'], FILTER_VALIDATE_EMAIL))
            ->reject(fn($d) => $exclues->has($d['email']))
            ->unique('email')
            ->values();
    }

    /**
     * Copie interne du catalogue (règle du 2026-11-03) : l'adresse de
     * MAIL_COPIE_COMMERCIALE reçoit chaque vrai catalogue, comme un client,
     * sans compter parmi eux. Brevo n'a pas de « copie » pour une campagne :
     * chaque adresse de la liste reçoit son propre mail. Une adresse
     * désinscrite n'est pas remise dans la liste. Les tests n'en ont pas :
     * la liste « Tests internes » se gère à la main dans Brevo.
     *
     * @return string[]
     */
    public function copiesCatalogue(): array
    {
        $exclues = $this->adressesExclues();

        return array_values(array_filter(
            self::adresses(config('mails.copie_commerciale')),
            fn($e) => !$exclues->has($e)
        ));
    }

    /** Adresses désinscrites ou écartées, en minuscules, en clés. */
    private function adressesExclues(): Collection
    {
        return DiffusionDesinscription::pluck('email')
            ->map(fn($e) => mb_strtolower(trim($e)))
            ->flip();
    }

    // ══════════════════════════════════════════════════════════════
    // PRÉPARATION ET ENVOI
    // ══════════════════════════════════════════════════════════════

    /**
     * Génère le PDF d'un créneau et ouvre la ligne de journal.
     *
     * Pour l'envoi automatique, réutilise le PDF préparé à 9h30 s'il
     * existe déjà : l'envoi de 10h part alors sans attendre la génération.
     */
    public function preparer(CarbonInterface $creneau, string $mode, ?User $par = null): DiffusionEnvoi
    {
        if ($mode === DiffusionEnvoi::MODE_AUTO) {
            $existant = DiffusionEnvoi::where('creneau', $creneau->toDateString())
                ->where('mode', DiffusionEnvoi::MODE_AUTO)
                ->where('statut', DiffusionEnvoi::STATUT_PREPARE)
                ->latest('id')
                ->first();

            if ($existant && $this->cataloguesComplets($existant)) {
                return $existant;
            }
        }

        [$debut, $fin] = $this->calendrier()->periode($creneau);

        $envoi = DiffusionEnvoi::create([
            'creneau'       => $creneau->toDateString(),
            'periode_debut' => $debut->toDateString(),
            'periode_fin'   => $fin->toDateString(),
            'mode'          => $mode,
            'declenche_par' => $par?->id,
            'statut'        => DiffusionEnvoi::STATUT_PREPARE,
        ]);

        return $this->genererPdf($envoi);
    }

    /**
     * Un envoi a-t-il TOUS ses catalogues, fichiers présents sur le disque ?
     *
     * Sert à décider si l'envoi de 10h peut réutiliser la préparation de 9h.
     * La somme des deux zones doit retomber sur le total : un envoi préparé
     * par une version antérieure au 2026-10-29 n'a que le catalogue complet,
     * ses compteurs de zone sont à 0, et il est donc régénéré.
     */
    private function cataloguesComplets(DiffusionEnvoi $envoi): bool
    {
        if (!$envoi->pdf_path) {
            return false;
        }

        if ($envoi->nb_panneaux_abidjan + $envoi->nb_panneaux_interieur !== $envoi->nb_panneaux) {
            return false;
        }

        $disque = Storage::disk('local');

        foreach ($envoi->cheminsCatalogues() as $chemin) {
            if (!$disque->exists($chemin)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Génère le PDF d'un envoi déjà enregistré (préparation automatique
     * ou demande manuelle).
     *
     * Durée mesurée sur tout le parc (364 panneaux) : ~6 à 7 minutes sur
     * un poste de dev. C'est pourquoi rien ne génère ce PDF pendant une
     * requête web : le bouton manuel enregistre une demande, et le
     * planificateur la traite (cf. traiterDemandes).
     */
    public function genererPdf(DiffusionEnvoi $envoi): DiffusionEnvoi
    {
        $debut = $envoi->periode_debut;
        $fin   = $envoi->periode_fin;
        $creneau = $envoi->creneau;
        $mode = $envoi->mode;

        try {
            // Tout le parc avec photos : gros volume. Même mémoire que les
            // exports PDF manuels (cf. Dockerfile, memory_limit=1024M).
            // Aucune limite de temps : en ligne de commande PHP n'en a pas,
            // et en imposer une couperait une génération légitime.
            @ini_set('memory_limit', '1024M');
            @set_time_limit(0);

            $envoi->update(['statut' => DiffusionEnvoi::STATUT_PREPARE, 'erreur' => null]);

            // Trois documents (2026-10-29) : le complet, Abidjan, l'intérieur.
            // Une zone vide n'en produit aucun — ses colonnes restent nulles,
            // donc le mail n'affichera pas son lien.
            $champs = [
                'complet'                      => ['nb_panneaux', 'pdf_path', 'pdf_token'],
                DiffusionEnvoi::ZONE_ABIDJAN   => ['nb_panneaux_abidjan', 'pdf_path_abidjan', 'pdf_token_abidjan'],
                DiffusionEnvoi::ZONE_INTERIEUR => ['nb_panneaux_interieur', 'pdf_path_interieur', 'pdf_token_interieur'],
            ];

            $catalogues = $this->renduCatalogues($debut, $fin);

            // Colonnes de zone remises à zéro d'abord : si une zone s'est
            // vidée depuis une préparation précédente, son ancien lien ne
            // doit pas survivre et renvoyer les clients vers un catalogue
            // périmé annoncé à « 0 emplacement ».
            $valeurs = [
                'nb_panneaux_abidjan'   => 0,
                'nb_panneaux_interieur' => 0,
                'pdf_path_abidjan'      => null,
                'pdf_token_abidjan'     => null,
                'pdf_path_interieur'    => null,
                'pdf_token_interieur'   => null,
            ];

            foreach ($champs as $cle => [$champNb, $champChemin, $champJeton]) {
                if (!isset($catalogues[$cle])) {
                    continue;
                }

                [$nb, $contenu] = $catalogues[$cle];
                $token = Str::random(48);
                $chemin = sprintf(
                    'diffusions/%s_%s_%s%s.pdf',
                    $creneau->format('Y-m-d'),
                    $mode,
                    $cle === 'complet' ? '' : $cle . '_',
                    $token
                );
                Storage::disk('local')->put($chemin, $contenu);

                $valeurs[$champNb]     = $nb;
                $valeurs[$champChemin] = $chemin;
                $valeurs[$champJeton]  = $token;
            }

            $envoi->update($valeurs);
        } catch (\Throwable $e) {
            $envoi->update(['statut' => DiffusionEnvoi::STATUT_ECHEC, 'erreur' => $this->messageErreur($e)]);
            throw $e;
        }

        return $envoi;
    }

    /**
     * Le catalogue complet d'une période, tel qu'il part aux clients.
     *
     * @return array{0: int, 1: string}  nombre de panneaux, contenu du PDF
     */
    public function renduCatalogue(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $panneaux = $this->panneauxDiffusables($debut, $fin);

        return [$panneaux->count(), $this->rendreCatalogue($panneaux, $debut, $fin)];
    }

    /**
     * Les catalogues d'une période : le complet, celui d'Abidjan et celui
     * de l'intérieur du pays (2026-10-29).
     *
     * UN SEUL passage sur les panneaux : c'est le traitement des photos
     * (cf. PdfExportService::enrichPanel) qui coûte les minutes, pas le
     * rendu. Les trois documents sont rendus à partir des mêmes lignes.
     *
     * Une zone sans aucun emplacement disponible ne produit PAS de PDF :
     * règle validée le 2026-10-29, le mail n'affiche alors pas son lien.
     *
     * @return array<string, array{0: int, 1: string}>  clés : complet,
     *         abidjan, interieur — les zones vides sont absentes
     */
    public function renduCatalogues(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $panneaux = $this->panneauxDiffusables($debut, $fin);

        $catalogues = ['complet' => [$panneaux->count(), $this->rendreCatalogue($panneaux, $debut, $fin)]];

        foreach (self::repartirParZone($panneaux) as $zone => $lignes) {
            if ($lignes->isEmpty()) {
                continue;
            }
            $catalogues[$zone] = [$lignes->count(), $this->rendreCatalogue($lignes, $debut, $fin)];
        }

        return $catalogues;
    }

    /**
     * Rend le PDF d'une sélection déjà calculée. Mêmes options pour le
     * catalogue complet et pour chaque zone : une seule écriture de la
     * règle d'affichage.
     */
    private function rendreCatalogue(Collection $lignes, CarbonInterface $debut, CarbonInterface $fin): string
    {
        return $this->pdfBuilder->rendre($lignes, $debut->toDateString(), $fin->toDateString(), [
            'hide_status'  => false,  // la disponibilité DOIT apparaître
            'show_pricing' => false,  // règle validée : pas de prix
        ])->output();
    }

    /**
     * Catalogues envoyés dont le PDF a disparu du disque (2026-10-28).
     *
     * En production, le conteneur est recréé à chaque mise en ligne : un
     * fichier hors volume persistant est perdu, et le lien reçu par les
     * clients répondait 404. On refait le PDF au même chemin, donc avec le
     * même lien, sans toucher au statut ni au jeton : un envoi déjà parti ne
     * doit jamais repartir. Une seule génération par période (~7 min),
     * partagée par les envois de cette période. Le catalogue refait montre
     * les disponibilités du moment.
     *
     * @return int  nombre de PDF refaits
     */
    public function reparerPdfsManquants(): int
    {
        $disque = Storage::disk('local');

        // Depuis le 2026-10-29 un envoi porte jusqu'à trois catalogues :
        // le complet, Abidjan, l'intérieur. On ne retient que les envois
        // dont au moins un fichier a disparu, et on ne refait que celui-là.
        $manquants = DiffusionEnvoi::whereIn('statut', DiffusionEnvoi::STATUTS_PARTIS)
            ->whereDate('periode_fin', '>=', today()->toDateString())
            ->get()
            ->map(fn (DiffusionEnvoi $e) => [$e, array_filter(
                $e->cheminsCatalogues(),
                fn (string $chemin) => !$disque->exists($chemin)
            )])
            ->filter(fn (array $paire) => $paire[1] !== []);

        $refaits = 0;
        $parPeriode = $manquants->groupBy(
            fn (array $paire) => $paire[0]->periode_debut->toDateString() . '_' . $paire[0]->periode_fin->toDateString()
        );

        foreach ($parPeriode as $groupe) {
            @ini_set('memory_limit', '1024M');
            @set_time_limit(0);

            $premier = $groupe->first()[0];
            $catalogues = $this->renduCatalogues($premier->periode_debut, $premier->periode_fin);

            foreach ($groupe as [$envoi, $aRefaire]) {
                foreach ($aRefaire as $cle => $chemin) {
                    // Zone devenue vide depuis l'envoi : impossible de
                    // refaire ce catalogue. Le lien reste en 404 plutôt que
                    // de servir au client un document sans aucune offre.
                    if (!isset($catalogues[$cle])) {
                        continue;
                    }
                    $disque->put($chemin, $catalogues[$cle][1]);
                    $refaits++;
                }
            }
        }

        return $refaits;
    }

    /**
     * Envoie un créneau préparé via Brevo, puis prévient l'équipe.
     *
     * Ne lève pas d'exception : un échec est consigné dans le journal et
     * signalé par mail, pour que l'admin utilise le bouton manuel.
     */
    public function envoyer(DiffusionEnvoi $envoi): DiffusionEnvoi
    {
        // Verrou : deux envois simultanés (double clic, planificateur lancé
        // deux fois) ne doivent jamais partir ensemble.
        $verrou = Cache::lock('diffusion-dispos-envoi', 900);
        if (!$verrou->get()) {
            $envoi->update([
                'statut' => DiffusionEnvoi::STATUT_ECHEC,
                'erreur' => 'Un autre envoi était déjà en cours — celui-ci n\'est pas parti.',
            ]);

            return $envoi;
        }

        $lienApercu = null;

        try {
            $envoi->update(['statut' => DiffusionEnvoi::STATUT_EN_COURS, 'erreur' => null]);

            $test = $this->estEnvoiTest($envoi);
            $listeId = $this->listeCible($test);
            $nbDestinataires = $this->synchroniserDestinataires($listeId, $test);

            if ($nbDestinataires === 0) {
                throw new BrevoException(
                    $test
                        ? 'La liste « Tests internes » est vide dans Brevo.'
                        : 'Aucun destinataire : aucun client n\'a d\'adresse valide non désinscrite.'
                );
            }

            // La copie interne part aussi : elle consomme le quota.
            $this->verifierQuota($nbDestinataires + ($test ? 0 : count($this->copiesCatalogue())));

            $contenu = $this->contenuCampagne($envoi, $listeId);
            try {
                $campagneId = $this->brevo->creerCampagne($contenu);
            } catch (\Throwable $e) {
                if (!BrevoClient::sansReponse($e)) {
                    throw $e;
                }
                // 2026-11-03 : Brevo a créé la campagne (deux fois) sans
                // répondre à temps. Rien n'est parti, mais un brouillon traîne.
                throw new BrevoException(
                    "Brevo n'a pas répondu à temps à la création de la campagne : rien n'est parti. "
                    . "Brevo a pu créer quand même le brouillon « {$contenu['name']} » : le supprimer dans Brevo, puis relancer."
                );
            }
            $lienApercu = BrevoClient::lienRapportCampagne($campagneId);

            // Ordre d'envoi sans réponse (2026-11-03) : la campagne a pu
            // partir. Surtout pas d'« Échec », qui inviterait à relancer et
            // ferait recevoir le catalogue deux fois : l'envoi est « à
            // vérifier », et dispos:verifier-envois lit chez Brevo ce qu'il
            // en est.
            $statut = DiffusionEnvoi::STATUT_ENVOYE;
            $doute  = null;
            try {
                $this->brevo->envoyerCampagne($campagneId);
            } catch (\Throwable $e) {
                if (!BrevoClient::sansReponse($e)) {
                    throw $e;
                }
                $statut = DiffusionEnvoi::STATUT_A_VERIFIER;
                $doute  = "Brevo n'a pas répondu à l'ordre d'envoi : la campagne est peut-être partie. "
                    . 'Ne pas relancer : Panora vérifie chez Brevo toutes les 15 minutes.';
            }

            $envoi->update([
                'statut'            => $statut,
                'nb_destinataires'  => $nbDestinataires,
                'brevo_campaign_id' => $campagneId,
                'envoye_at'         => now(),
                'erreur'            => $doute,
            ]);

            Log::log($doute ? 'warning' : 'info', $doute ? 'diffusion.a-verifier' : 'diffusion.envoyee', [
                'envoi_id' => $envoi->id,
                'mode'     => $envoi->mode,
                'creneau'  => $envoi->creneau->toDateString(),
                'nb'       => $nbDestinataires,
                'campagne' => $campagneId,
            ]);
        } catch (\Throwable $e) {
            $envoi->update([
                'statut' => DiffusionEnvoi::STATUT_ECHEC,
                'erreur' => $this->messageErreur($e),
            ]);

            Log::error('diffusion.echec', ['envoi_id' => $envoi->id, 'erreur' => $e->getMessage()]);
        } finally {
            $verrou->release();
        }

        $this->notifierEquipe($envoi->fresh(), $lienApercu);

        return $envoi->fresh();
    }

    /**
     * Appelé chaque jour par le planificateur. Ne fait rien si ce n'est
     * pas un jour d'envoi, si l'automatique est désactivé, ou si le
     * créneau est déjà parti en automatique (idempotence).
     */
    public function executerAutomatique(CarbonInterface $jour, bool $preparerSeulement = false): ?DiffusionEnvoi
    {
        if (!config('diffusion.auto')) {
            return null;
        }

        $creneau = $this->calendrier()->creneauDuJour($jour);
        if (!$creneau) {
            return null;
        }

        $dejaParti = DiffusionEnvoi::where('creneau', $creneau->toDateString())
            ->where('mode', DiffusionEnvoi::MODE_AUTO)
            ->whereIn('statut', [...DiffusionEnvoi::STATUTS_PARTIS, DiffusionEnvoi::STATUT_EN_COURS])
            ->exists();

        if ($dejaParti) {
            return null;
        }

        if (!$preparerSeulement) {
            $this->attendrePreparationEnCours($creneau);
        }

        try {
            $envoi = $this->preparer($creneau, DiffusionEnvoi::MODE_AUTO);
        } catch (\Throwable $e) {
            // La préparation a échoué : l'équipe doit le savoir AVANT 10h.
            $echec = DiffusionEnvoi::where('creneau', $creneau->toDateString())
                ->where('mode', DiffusionEnvoi::MODE_AUTO)
                ->latest('id')
                ->first();
            if ($echec) {
                $this->notifierEquipe($echec, null);
            }

            return $echec;
        }

        return $preparerSeulement ? $envoi : $this->envoyer($envoi);
    }

    /**
     * Envoi manuel (bouton admin) — enregistre une DEMANDE.
     *
     * Le PDF de tout le parc prend plusieurs minutes à générer : trop long
     * pour une requête web, qui serait coupée. Le planificateur traite la
     * demande dans la minute (dispos:diffuser --demandes) et l'équipe
     * reçoit la confirmation par mail.
     *
     * Couvre la période du créneau en cours : avant le 15, tout le mois ;
     * à partir du 15, du 15 à la fin du mois. N'annule pas l'automatique.
     */
    public function demanderEnvoiManuel(User $par, bool $test = false): DiffusionEnvoi
    {
        $creneau = $this->calendrier()->creneauCourant(now());
        [$debut, $fin] = $this->calendrier()->periode($creneau);

        return DiffusionEnvoi::create([
            'creneau'       => $creneau->toDateString(),
            'periode_debut' => $debut->toDateString(),
            'periode_fin'   => $fin->toDateString(),
            'mode'          => $test ? DiffusionEnvoi::MODE_TEST : DiffusionEnvoi::MODE_MANUEL,
            'declenche_par' => $par->id,
            'statut'        => DiffusionEnvoi::STATUT_DEMANDE,
        ]);
    }

    /**
     * Une demande manuelle attend-elle déjà d'être traitée ? Un envoi « à
     * vérifier » bloque aussi (2026-11-03), quel qu'en soit le type, tant
     * que dispos:verifier-envois le relit (24 h) : il est peut-être parti.
     */
    public function demandeEnAttente(): ?DiffusionEnvoi
    {
        return DiffusionEnvoi::whereIn('statut', [DiffusionEnvoi::STATUT_DEMANDE, DiffusionEnvoi::STATUT_EN_COURS])
            ->whereIn('mode', [DiffusionEnvoi::MODE_MANUEL, DiffusionEnvoi::MODE_TEST])
            ->where('created_at', '>', now()->subHour())
            ->latest('id')
            ->first()
            ?? DiffusionEnvoi::where('statut', DiffusionEnvoi::STATUT_A_VERIFIER)
                ->where('envoye_at', '>', now()->subDay())
                ->latest('id')
                ->first();
    }

    /**
     * Traite les demandes d'envoi manuel en attente, les plus anciennes
     * d'abord. Appelé chaque minute par le planificateur.
     *
     * @return int  nombre de demandes traitées
     */
    public function traiterDemandes(): int
    {
        $demandes = DiffusionEnvoi::where('statut', DiffusionEnvoi::STATUT_DEMANDE)
            ->orderBy('id')
            ->get();

        foreach ($demandes as $demande) {
            // Réservation atomique : si deux planificateurs tournaient en
            // même temps, un seul prend la demande.
            $pris = DiffusionEnvoi::where('id', $demande->id)
                ->where('statut', DiffusionEnvoi::STATUT_DEMANDE)
                ->update(['statut' => DiffusionEnvoi::STATUT_PREPARE]);

            if (!$pris) {
                continue;
            }

            try {
                $this->envoyer($this->genererPdf($demande->fresh()));
            } catch (\Throwable) {
                // genererPdf a déjà consigné l'échec dans le journal.
                $this->notifierEquipe($demande->fresh(), null);
            }
        }

        return $demandes->count();
    }

    /**
     * Si la préparation de 9h est encore en train de générer le PDF à
     * 10h, on l'attend (20 min maximum) plutôt que de tout regénérer en
     * parallèle.
     */
    private function attendrePreparationEnCours(CarbonInterface $creneau): void
    {
        $limite = now()->addMinutes(20);

        while (now()->lt($limite)) {
            $enCours = DiffusionEnvoi::where('creneau', $creneau->toDateString())
                ->where('mode', DiffusionEnvoi::MODE_AUTO)
                ->where('statut', DiffusionEnvoi::STATUT_PREPARE)
                ->whereNull('pdf_path')
                ->where('created_at', '>', now()->subHour())
                ->exists();

            if (!$enCours) {
                return;
            }

            sleep(15);
        }
    }

    /**
     * Ce que l'admin voit avant de confirmer un envoi manuel.
     */
    public function apercuEnvoiManuel(): array
    {
        $creneau = $this->calendrier()->creneauCourant(now());
        [$debut, $fin] = $this->calendrier()->periode($creneau);
        $dernier = $this->dernierEnvoiReussi();
        $delai = (int) config('diffusion.delai_alerte_heures', 24);

        return [
            'periode'          => DiffusionCalendrier::libellePeriode($debut, $fin),
            'nb_destinataires' => $this->estModeTest() ? null : $this->destinataires()->count(),
            'mode_test'        => $this->estModeTest(),
            'dernier_envoi'    => $dernier ? [
                'date'  => $dernier->envoye_at?->format('d/m/Y à H\hi'),
                'mode'  => $dernier->libelleMode(),
                'nb'    => $dernier->nb_destinataires,
                'periode' => DiffusionCalendrier::libellePeriode($dernier->periode_debut, $dernier->periode_fin),
            ] : null,
            'envoi_recent'     => $dernier && $dernier->envoye_at?->gt(now()->subHours($delai)),
            'delai_heures'     => $delai,
        ];
    }

    /**
     * Dernier envoi parti chez les clients. Un envoi suspendu par Brevo
     * compte aussi (2026-11-02) : une partie des clients a pu le recevoir.
     */
    public function dernierEnvoiReussi(): ?DiffusionEnvoi
    {
        return DiffusionEnvoi::whereIn('statut', DiffusionEnvoi::STATUTS_PARTIS)
            ->where('mode', '!=', DiffusionEnvoi::MODE_TEST)
            ->latest('envoye_at')
            ->first();
    }

    // ══════════════════════════════════════════════════════════════
    // ÉTAT RÉEL CHEZ BREVO (2026-11-02)
    // ══════════════════════════════════════════════════════════════

    /** Au-delà, Brevo suspend la campagne : il exige moins de 2 % d'adresses invalides. */
    private const SEUIL_INVALIDES = 0.02;

    /**
     * Relit chez Brevo les envois des dernières heures et repère ceux
     * qu'il a suspendus. Appelé toutes les 15 minutes (dispos:verifier-envois).
     *
     * Brevo accepte l'ordre d'envoi même quand il ne pourra pas le tenir,
     * puis suspend la campagne : aussitôt si le quota est épuisé, jusqu'à
     * 3 h plus tard s'il juge les rebonds trop nombreux. Constaté le 2/11 :
     * Panora affichait « Envoyé » pour deux campagnes suspendues.
     *
     * Une campagne terminée chez Brevo n'est plus relue. Si Brevo ne
     * répond pas, l'envoi sera relu au passage suivant.
     *
     * Les envois « à vérifier » (2026-11-03, ordre d'envoi resté sans
     * réponse) sont tranchés ici, cf. trancherEnvoiAVerifier().
     *
     * @return int  nombre d'envois trouvés suspendus
     */
    public function verifierEnvoisRecents(int $heures = 24): int
    {
        $envois = DiffusionEnvoi::whereIn('statut', [DiffusionEnvoi::STATUT_ENVOYE, DiffusionEnvoi::STATUT_A_VERIFIER])
            ->whereNotNull('brevo_campaign_id')
            ->where('envoye_at', '>=', now()->subHours($heures))
            ->orderBy('id')
            ->get();

        $suspendus = 0;

        foreach ($envois as $envoi) {
            $termine = "diffusion-envoi-{$envoi->id}-termine-chez-brevo";
            if (Cache::has($termine)) {
                continue;
            }

            try {
                $campagne = $this->brevo->campagne((int) $envoi->brevo_campaign_id);
            } catch (\Throwable $e) {
                Log::warning('diffusion.verification', ['envoi_id' => $envoi->id, 'erreur' => $e->getMessage()]);
                continue;
            }

            if ($envoi->statut === DiffusionEnvoi::STATUT_A_VERIFIER && $campagne['statut'] !== 'suspended') {
                $this->trancherEnvoiAVerifier($envoi, $campagne['statut']);
                continue;
            }

            if (in_array($campagne['statut'], ['sent', 'archive', 'cancelled'], true)) {
                Cache::put($termine, true, now()->addDays(3));
                continue;
            }

            if ($campagne['statut'] !== 'suspended') {
                continue;   // encore en cours chez Brevo : relue au passage suivant
            }

            $envoi->update([
                'statut' => DiffusionEnvoi::STATUT_SUSPENDU,
                'erreur' => $this->explicationSuspension($envoi, $campagne),
            ]);
            Log::warning('diffusion.suspendue', ['envoi_id' => $envoi->id, 'campagne' => $envoi->brevo_campaign_id]);

            $this->notifierEquipe($envoi->fresh(), BrevoClient::lienRapportCampagne((int) $envoi->brevo_campaign_id));
            $suspendus++;
        }

        return $suspendus;
    }

    /**
     * Ordre d'envoi resté sans réponse (2026-11-03) : l'état de la campagne
     * chez Brevo dit s'il est parti. L'équipe reçoit le mail du résultat.
     *   • en file, en cours, envoyée → « Envoyé » ;
     *   • encore brouillon 15 min après → « Échec », rien n'est parti ;
     *   • annulée dans Brevo → « Échec », à voir dans Brevo ;
     *   • autre chose → relue au passage suivant.
     * Une suspension suit le chemin commun de verifierEnvoisRecents().
     */
    private function trancherEnvoiAVerifier(DiffusionEnvoi $envoi, ?string $statutBrevo): void
    {
        $campagneId = (int) $envoi->brevo_campaign_id;

        $conclusion = match (true) {
            in_array($statutBrevo, ['queued', 'in_process', 'in_review', 'sent', 'archive'], true)
                => [DiffusionEnvoi::STATUT_ENVOYE, null],
            $statutBrevo === 'draft' && $envoi->envoye_at?->lte(now()->subMinutes(15))
                => [DiffusionEnvoi::STATUT_ECHEC, "Brevo n'a pas lancé l'envoi : rien n'est parti. "
                    . "Supprimer le brouillon n° {$campagneId} dans Brevo, puis relancer depuis Panora."],
            in_array($statutBrevo, ['cancelling', 'cancelled'], true)
                => [DiffusionEnvoi::STATUT_ECHEC, "La campagne n° {$campagneId} a été annulée dans Brevo. "
                    . 'Vérifier dans Brevo combien de mails sont partis avant de relancer.'],
            default => null,
        };

        if ($conclusion === null) {
            return;
        }

        [$statut, $erreur] = $conclusion;
        $envoi->update(['statut' => $statut, 'erreur' => $erreur]);
        Log::info('diffusion.a-verifier.tranche', ['envoi_id' => $envoi->id, 'brevo' => $statutBrevo, 'statut' => $statut]);

        $this->notifierEquipe($envoi->fresh(), BrevoClient::lienRapportCampagne($campagneId));
    }

    /**
     * Brevo ne dit pas pourquoi il suspend : on le déduit de ses chiffres.
     *   « Cause probable : quota d'envois épuisé et trop d'adresses
     *     invalides (Brevo exige moins de 2 %). Partis : 300 sur 830 ·
     *     adresses invalides : 46 (15,3 %) · envois restants sur le compte
     *     Brevo : 0. »
     *
     * @param array{statut: ?string, partis: ?int, invalides: ?int} $campagne
     */
    private function explicationSuspension(DiffusionEnvoi $envoi, array $campagne): string
    {
        $prevus    = (int) $envoi->nb_destinataires;
        $partis    = $campagne['partis'];
        $invalides = $campagne['invalides'];

        try {
            $restants = $this->brevo->envoisRestants();
        } catch (\Throwable) {
            $restants = null;
        }

        $causes = [];
        if ($restants !== null && $restants < max(1, $prevus - (int) $partis)) {
            $causes[] = 'quota d\'envois épuisé';
        }
        if ($partis && $invalides !== null && $invalides / $partis >= self::SEUIL_INVALIDES) {
            $causes[] = 'trop d\'adresses invalides (Brevo exige moins de 2 %)';
        }

        $faits = ['Partis : ' . ($partis ?? '?') . " sur {$prevus}"];
        if ($invalides !== null) {
            $taux = $partis ? ' (' . str_replace('.', ',', (string) round(100 * $invalides / $partis, 1)) . ' %)' : '';
            $faits[] = "adresses invalides : {$invalides}{$taux}";
        }
        if ($restants !== null) {
            $faits[] = "envois restants sur le compte Brevo : {$restants}";
        }

        return ($causes
                ? 'Cause probable : ' . implode(' et ', $causes) . '. '
                : 'Brevo ne donne pas la raison : voir la campagne dans Brevo. ')
            . implode(' · ', $faits) . '.';
    }

    /**
     * Règle validée le 2026-11-02 : si Brevo annonce moins d'envois
     * restants que de destinataires, rien ne part. Brevo accepterait
     * l'ordre puis suspendrait en cours de route (le 2/11 : 300 partis sur
     * 830, puis un test lancé avec 0 envoi restant). Si Brevo ne répond
     * pas, l'envoi n'est pas bloqué. DIFFUSION_CONTROLE_QUOTA=false coupe
     * ce contrôle.
     */
    private function verifierQuota(int $nbDestinataires): void
    {
        if (!config('diffusion.controle_quota', true)) {
            return;
        }

        try {
            $restants = $this->brevo->envoisRestants();
        } catch (\Throwable $e) {
            Log::warning('diffusion.quota-illisible', ['erreur' => $e->getMessage()]);

            return;
        }

        if ($restants !== null && $restants < $nbDestinataires) {
            throw new BrevoException(
                "Brevo n'a plus que {$restants} envoi(s) disponible(s), il en faut {$nbDestinataires} : rien n'est parti. "
                . 'Attendre le renouvellement du quota (offre gratuite : 300 par jour) ou passer à une offre payante, puis relancer.'
            );
        }
    }

    // ══════════════════════════════════════════════════════════════
    // BREVO
    // ══════════════════════════════════════════════════════════════

    private function listeCible(bool $test): int
    {
        $cle = $test ? 'tests' : 'clients';
        $id = config("brevo.lists.{$cle}");

        if (!$id) {
            throw new BrevoException(
                $test
                    ? 'BREVO_LIST_TESTS n\'est pas renseigné dans le .env (mode test).'
                    : 'BREVO_LIST_CLIENTS n\'est pas renseigné dans le .env.'
            );
        }

        // Garde-fou (2026-11-02) : un test ne doit jamais pouvoir viser la
        // liste des clients, même par erreur de réglage.
        if ($test && (int) $id === (int) config('brevo.lists.clients')) {
            throw new BrevoException(
                'BREVO_LIST_TESTS et BREVO_LIST_CLIENTS désignent la même liste Brevo : le test n\'est pas parti.'
            );
        }

        return (int) $id;
    }

    /**
     * Met la liste Brevo des clients en phase avec Panora et renvoie le
     * nombre de destinataires. Pour un test, la liste « Tests internes »
     * est gérée à la main dans Brevo : on se contente de la compter, on
     * n'y écrit jamais.
     */
    private function synchroniserDestinataires(int $listeId, bool $test): int
    {
        if ($test) {
            return count($this->brevo->emailsDeLaListe($listeId));
        }

        $destinataires = $this->destinataires();

        foreach ($destinataires as $d) {
            $this->brevo->enregistrerContact($d['email'], [
                'CONTACT'   => $d['contact'],
                'SOCIETE'   => $d['societe'],
                'PANORA_ID' => $d['client_id'],
            ], [$listeId]);
        }

        // Copie interne (2026-11-03) : reçoit le catalogue comme un client,
        // sans compter parmi eux. Pas de SOCIETE : le mail dirait « pour
        // CIBLE » ; la copie lit la phrase sans entreprise.
        $copies = array_values(array_diff($this->copiesCatalogue(), $destinataires->pluck('email')->all()));
        foreach ($copies as $email) {
            $this->brevo->enregistrerContact($email, [
                'CONTACT' => User::where('email', $email)->value('name'),
            ], [$listeId]);
        }

        // Retire de la liste ceux qui ne sont plus destinataires dans
        // Panora (client supprimé, adresse changée, exclu à la main).
        $voulus = $destinataires->pluck('email')->merge($copies)->flip();
        $enTrop = array_values(array_filter(
            $this->brevo->emailsDeLaListe($listeId),
            fn($e) => !$voulus->has($e)
        ));
        if ($enTrop) {
            $this->brevo->retirerDeLaListe($listeId, $enTrop);
        }

        return $destinataires->count();
    }

    private function contenuCampagne(DiffusionEnvoi $envoi, int $listeId): array
    {
        $periode = DiffusionCalendrier::libellePeriode($envoi->periode_debut, $envoi->periode_fin);

        // Règle validée le 2026-10-29 : le mail porte DEUX liens, un par
        // zone. LIEN_PDF (catalogue complet) reste fourni — filet de
        // sécurité pour tout modèle Brevo pas encore remplacé, sans quoi
        // son bouton partirait vers une adresse vide.
        $valeurs = [
            'PERIODE'     => $periode,
            'MOIS'        => DiffusionCalendrier::libelleMois($envoi->periode_debut),
            'NB_PANNEAUX' => $envoi->nb_panneaux,
            'LIEN_PDF'    => $envoi->lienPdf(),
        ];

        // Une zone sans aucun emplacement n'envoie NI lien NI nombre : le
        // modèle Brevo teste la présence du lien et masque tout son bloc.
        foreach ([
            DiffusionEnvoi::ZONE_ABIDJAN   => ['LIEN_ABIDJAN', 'NB_ABIDJAN'],
            DiffusionEnvoi::ZONE_INTERIEUR => ['LIEN_INTERIEUR', 'NB_INTERIEUR'],
        ] as $zone => [$cleLien, $cleNombre]) {
            if ($lien = $envoi->lienPdfZone($zone)) {
                $valeurs[$cleLien]   = $lien;
                $valeurs[$cleNombre] = $envoi->nbPanneauxZone($zone);
            }
        }

        // Signature (2026-11-03) : numéros et logo viennent de la charte,
        // donc du .env. Le modèle Brevo ne les écrit plus en dur : changer
        // un numéro ne demande plus de le recoller.
        if ($telephones = trim((string) config('charte.coordonnees.telephones'))) {
            // Espaces insécables DANS chaque numéro : un téléphone ne le
            // coupe jamais en fin de ligne, seuls les « · » séparent.
            $valeurs['TELEPHONES'] = preg_replace('/(?<=[\d+])\s+(?=\d)/u', "\u{00A0}", $telephones);
        }
        if ($logo = config('charte.logos.clair')) {
            $valeurs['LOGO'] = asset($logo);
        }

        $objet = str_replace('{periode}', $periode, (string) config('diffusion.objet'));
        if ($this->estEnvoiTest($envoi)) {
            $objet = '[TEST] ' . $objet;
        }

        $payload = [
            'name'       => sprintf('Disponibilités %s — %s #%d', $periode, $envoi->libelleMode(), $envoi->id),
            'subject'    => $objet,
            'sender'     => [
                'name'  => (string) config('brevo.sender.name'),
                'email' => (string) config('brevo.sender.email'),
            ],
            'replyTo'    => (string) config('brevo.sender.email'),
            'recipients' => ['listIds' => [$listeId]],
        ];

        if ($templateId = config('brevo.template_id')) {
            // Modèle conçu par la boss dans l'éditeur Brevo : il affiche
            // les valeurs via {{ params.PERIODE }}, {{ params.LIEN_PDF }}…
            $payload['templateId'] = (int) $templateId;
            $payload['params'] = $valeurs;
        } else {
            $payload['htmlContent'] = view('diffusion.campagne-defaut', [
                'valeurs' => $valeurs,
                'envoi'   => $envoi,
            ])->render();
        }

        // Page de désinscription aux couleurs de la régie, si configurée.
        if ($pageDesinscription = config('brevo.unsubscribe_page_id')) {
            $payload['unsubscriptionPageId'] = (string) $pageDesinscription;
        }

        return $payload;
    }

    // ══════════════════════════════════════════════════════════════
    // CONFIRMATION INTERNE
    // ══════════════════════════════════════════════════════════════

    /**
     * Confirmation après envoi, ou alerte si échec.
     *
     * Production : commercial@ + tous les Media Planners actifs.
     * Mode test : seulement la personne qui a déclenché le test (à défaut
     * l'adresse de confirmation) — pour ne pas inonder l'équipe pendant
     * les essais.
     */
    private function notifierEquipe(?DiffusionEnvoi $envoi, ?string $lienApercu): void
    {
        if (!$envoi) {
            return;
        }

        foreach ($this->destinatairesInternes($envoi) as $email) {
            $this->mailer->sendSilently(
                $email,
                new DiffusionDisponibilitesMail($envoi, $lienApercu),
                context: ['diffusion_envoi_id' => $envoi->id]
            );
        }
    }

    /** @return string[] */
    public function destinatairesInternes(DiffusionEnvoi $envoi): array
    {
        $confirmation = self::adresses(config('diffusion.confirmation_email'));

        if ($this->estEnvoiTest($envoi)) {
            $auteur = $envoi->auteur?->email;

            return $auteur ? [mb_strtolower($auteur)] : $confirmation;
        }

        return collect($confirmation)
            ->merge(User::where('role', 'mediaplanner')->where('is_active', true)->pluck('email'))
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn($e) => mb_strtolower($e))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Liste d'adresses écrite dans le .env : séparées par des virgules,
     * points-virgules ou espaces. Les adresses invalides sont ignorées.
     *   « commercial@cible-ci.com, studio@cible-ci.com » → 2 adresses
     *
     * @return string[]
     */
    public static function adresses(?string $valeur): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $valeur, -1, PREG_SPLIT_NO_EMPTY))
            // Guillemets tolérés : Coolify les garde tels quels si on écrit
            // la valeur entre guillemets (constaté en prod le 2026-10-01).
            ->map(fn($e) => mb_strtolower(trim($e, "\"'<>")))
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function messageErreur(\Throwable $e): string
    {
        return $e instanceof BrevoException
            ? $e->getMessage()
            : 'Erreur interne : ' . $e->getMessage();
    }
}
