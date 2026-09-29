<?php

namespace App\Services\Diffusion;

use App\Mail\DiffusionDisponibilitesMail;
use App\Models\Client;
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
 *   • Le 1er et le 15 à 10h, décalés au premier jour ouvrable suivant
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
        $ids = Panel::query()
            ->whereNull('panels.deleted_at')
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
     * Destinataires : une adresse par client — le contact principal, à
     * défaut l'adresse de la fiche client. Exclus : adresses invalides,
     * désinscrits, adresses mortes, doublons.
     *
     * @return Collection<int, array{email: string, contact: ?string, societe: string, client_id: int}>
     */
    public function destinataires(): Collection
    {
        $exclues = DiffusionDesinscription::pluck('email')
            ->map(fn($e) => mb_strtolower(trim($e)))
            ->flip();

        return Client::query()
            ->with(['contacts' => fn($q) => $q->select('id', 'client_id', 'name', 'email', 'is_primary')])
            ->orderBy('name')
            ->get(['id', 'name', 'contact_name', 'email'])
            ->map(function (Client $c) {
                $contact = $c->contacts->first(fn($ct) => filter_var($ct->email, FILTER_VALIDATE_EMAIL));

                return [
                    'email'     => mb_strtolower(trim((string) ($contact?->email ?: $c->email))),
                    'contact'   => $contact?->name ?: $c->contact_name,
                    'societe'   => $c->name,
                    'client_id' => $c->id,
                ];
            })
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

            if ($existant && $existant->pdf_path && Storage::disk('local')->exists($existant->pdf_path)) {
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

            $panneaux = $this->panneauxDiffusables($debut, $fin);
            $pdf = $this->pdfBuilder->rendre($panneaux, $debut->toDateString(), $fin->toDateString(), [
                'hide_status'  => false,  // la disponibilité DOIT apparaître
                'show_pricing' => false,  // règle validée : pas de prix
            ]);

            $token = Str::random(48);
            $chemin = sprintf('diffusions/%s_%s_%s.pdf', $creneau->format('Y-m-d'), $mode, $token);
            Storage::disk('local')->put($chemin, $pdf->output());

            $envoi->update([
                'nb_panneaux' => $panneaux->count(),
                'pdf_path'    => $chemin,
                'pdf_token'   => $token,
            ]);
        } catch (\Throwable $e) {
            $envoi->update(['statut' => DiffusionEnvoi::STATUT_ECHEC, 'erreur' => $this->messageErreur($e)]);
            throw $e;
        }

        return $envoi;
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
        $valeurs = [
            'PERIODE'     => $periode,
            'MOIS'        => DiffusionCalendrier::libelleMois($envoi->periode_debut),
            'NB_PANNEAUX' => $envoi->nb_panneaux,
            'LIEN_PDF'    => $envoi->lienPdf(),
        ];

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
        $confirmation = config('diffusion.confirmation_email');

        if ($envoi->mode === DiffusionEnvoi::MODE_TEST || $this->estModeTest()) {
            $email = $envoi->auteur?->email ?: $confirmation;

            return $email ? [mb_strtolower($email)] : [];
        }

        return collect([$confirmation])
            ->merge(User::where('role', 'mediaplanner')->where('is_active', true)->pluck('email'))
            ->filter(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn($e) => mb_strtolower($e))
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
