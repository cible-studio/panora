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
        $exclues = DiffusionDesinscription::pluck('email')
            ->map(fn($e) => mb_strtolower(trim($e)))
            ->flip();

        // Chaque adresse au statut client, plus seulement une par entreprise ;
        // aucun prospect (2026-10-28, cf. Client::destinatairesDiffusion).
        return Client::query()
            ->clients()
            ->with(['contacts' => fn($q) => $q->select('id', 'client_id', 'name', 'email', 'is_primary', 'statut')])
            ->orderBy('name')
            ->get(['id', 'name', 'contact_name', 'email', 'statut_commercial'])
            ->flatMap(fn(Client $c) => array_map(fn($d) => $d + [
                'societe'   => $c->name,
                'client_id' => $c->id,
            ], $c->destinatairesDiffusion()))
            ->filter(fn($d) => filter_var($d['email'], FILTER_VALIDATE_EMAIL))
            ->reject(fn($d) => $exclues->has($d['email']))
            ->unique('email')
            ->values();
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
        $manquants = DiffusionEnvoi::where('statut', DiffusionEnvoi::STATUT_ENVOYE)
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

            $listeId = $this->listeCible();
            $nbDestinataires = $this->synchroniserDestinataires($listeId);

            if ($nbDestinataires === 0) {
                throw new BrevoException(
                    $this->estModeTest()
                        ? 'La liste « Tests internes » est vide dans Brevo.'
                        : 'Aucun destinataire : aucun client n\'a d\'adresse valide non désinscrite.'
                );
            }

            $campagneId = $this->brevo->creerCampagne($this->contenuCampagne($envoi, $listeId));
            $this->brevo->envoyerCampagne($campagneId);
            $lienApercu = BrevoClient::lienRapportCampagne($campagneId);

            $envoi->update([
                'statut'            => DiffusionEnvoi::STATUT_ENVOYE,
                'nb_destinataires'  => $nbDestinataires,
                'brevo_campaign_id' => $campagneId,
                'envoye_at'         => now(),
            ]);

            Log::info('diffusion.envoyee', [
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
            ->whereIn('statut', [DiffusionEnvoi::STATUT_ENVOYE, DiffusionEnvoi::STATUT_EN_COURS])
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

    /** Une demande manuelle attend-elle déjà d'être traitée ? */
    public function demandeEnAttente(): ?DiffusionEnvoi
    {
        return DiffusionEnvoi::whereIn('statut', [DiffusionEnvoi::STATUT_DEMANDE, DiffusionEnvoi::STATUT_EN_COURS])
            ->whereIn('mode', [DiffusionEnvoi::MODE_MANUEL, DiffusionEnvoi::MODE_TEST])
            ->where('created_at', '>', now()->subHour())
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

    public function dernierEnvoiReussi(): ?DiffusionEnvoi
    {
        return DiffusionEnvoi::where('statut', DiffusionEnvoi::STATUT_ENVOYE)
            ->where('mode', '!=', DiffusionEnvoi::MODE_TEST)
            ->latest('envoye_at')
            ->first();
    }

    // ══════════════════════════════════════════════════════════════
    // BREVO
    // ══════════════════════════════════════════════════════════════

    private function listeCible(): int
    {
        $cle = $this->estModeTest() ? 'tests' : 'clients';
        $id = config("brevo.lists.{$cle}");

        if (!$id) {
            throw new BrevoException(
                $this->estModeTest()
                    ? 'BREVO_LIST_TESTS n\'est pas renseigné dans le .env (mode test).'
                    : 'BREVO_LIST_CLIENTS n\'est pas renseigné dans le .env.'
            );
        }

        return (int) $id;
    }

    /**
     * Met la liste Brevo des clients en phase avec Panora et renvoie le
     * nombre de destinataires. En mode test, la liste « Tests internes »
     * est gérée à la main dans Brevo : on se contente de la compter.
     */
    private function synchroniserDestinataires(int $listeId): int
    {
        if ($this->estModeTest()) {
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

        // Retire de la liste ceux qui ne sont plus destinataires dans
        // Panora (client supprimé, adresse changée, exclu à la main).
        $voulus = $destinataires->pluck('email')->flip();
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

        $objet = str_replace('{periode}', $periode, (string) config('diffusion.objet'));
        if ($envoi->mode === DiffusionEnvoi::MODE_TEST || $this->estModeTest()) {
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

        if ($envoi->mode === DiffusionEnvoi::MODE_TEST || $this->estModeTest()) {
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
