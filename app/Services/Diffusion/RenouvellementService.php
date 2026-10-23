<?php

namespace App\Services\Diffusion;

use App\Enums\CampaignStatus;
use App\Mail\MailClientCampagne;
use App\Mail\RenouvellementReponseMail;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\DiffusionDesinscription;
use App\Models\RelanceFinCampagne;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\NotificationMailer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Renouvellement des campagnes annuelles (2026-10-22).
 *
 * SEUL endroit qui porte les règles validées par la direction :
 *   · campagnes concernées : actives de 12 mois ou plus (Campaign::estAnnuelle) ;
 *   · séquence J-60, J-45, J-30 (date limite), J-15, J-7, avec boutons de
 *     réponse (textes : ModelesMail, modifiables dans Panora) ;
 *   · la séquence s'arrête dès que le dossier n'est plus « en attente » :
 *     réponse du client, ou statut changé par l'équipe après un appel ;
 *   · réponse du client : accusé de réception au client, mail au
 *     commercial de la campagne avec commercial@ en copie ;
 *   · le MP confirme le renouvellement → les panneaux sortent du
 *     catalogue des disponibilités (panneauxRetenus) ;
 *   · chaque matin, mail aux commerciaux (clients à appeler dès J-25,
 *     demandes de rappel, réponses reçues) et aux MP (à confirmer).
 *
 * Envoi, destinataire, mode test et journal : socle commun avec le mail
 * J-7 simple (RelanceFinCampagneService::envoyerEtJournaliser). Tout part
 * par Brevo, un tag par étape (renouvellement-j60…).
 *
 * Pas de règle « autre campagne du client » ici (contrairement au mail
 * J-7 simple) : le mail porte sur les emplacements d'UNE campagne. La
 * liste signale une autre campagne planifiée du client, à vérifier.
 */
class RenouvellementService
{
    /** Étape → jours avant la fin, dans l'ordre d'envoi. */
    public const ETAPES = ['j60' => 60, 'j45' => 45, 'j30' => 30, 'j15' => 15, 'j7' => 7];

    /** Appels des commerciaux à partir de J-25 (process validé). */
    public const JOURS_APPEL = 25;

    /** Filtre « Toutes » de l'écran : toutes les campagnes annuelles en cours. */
    public const HORIZON_TOUTES = 3650;

    public function __construct(
        private RelanceFinCampagneService $socle,
        private NotificationMailer $mailer,
    ) {}

    /**
     * Étape due pour N jours restants : la plus proche échéance encore à
     * venir (≥ N). Un jour manqué est rattrapé tant que l'étape suivante
     * n'est pas due. Fonction pure.
     *   50 jours → j60 · 44 → j45 · 30 → j30 · 8 → j15 · 7 → j7 · 61 → null
     */
    public static function etapePour(int $joursRestants): ?string
    {
        if ($joursRestants <= 0) {
            return null;
        }

        $etape = null;
        foreach (self::ETAPES as $cle => $jours) {
            if ($joursRestants <= $jours) {
                $etape = $cle;
            }
        }

        return $etape;
    }

    // ══════════════════════════════════════════════════════════════
    // SÉQUENCE DE MAILS
    // ══════════════════════════════════════════════════════════════

    /** @return Collection<int, Campaign>  annuelles actives qui finissent dans 1 à 60 jours */
    public function campagnesDuJour(): Collection
    {
        return Campaign::query()
            ->where('status', CampaignStatus::ACTIF->value)
            ->whereDate('end_date', '>', today()->toDateString())
            ->whereDate('end_date', '<=', today()->addDays(max(self::ETAPES))->toDateString())
            ->with(['client.contacts', 'commercial', 'user'])
            ->orderBy('end_date')
            ->get()
            ->filter(fn(Campaign $c) => $c->estAnnuelle())
            ->values();
    }

    /** Dossier de la campagne pour sa date de fin actuelle (créé au besoin). */
    public function dossier(Campaign $campagne): Renouvellement
    {
        return $this->dossierExistant($campagne)
            ?? Renouvellement::create([
                'campaign_id'  => $campagne->id,
                'client_id'    => $campagne->client_id,
                'fin_campagne' => $campagne->end_date->toDateString(),
                'statut'       => Renouvellement::EN_ATTENTE,
                'token'        => Str::random(48),
            ]);
    }

    public function dossierExistant(Campaign $campagne): ?Renouvellement
    {
        if (!$campagne->exists || !$campagne->end_date) {
            return null;
        }

        return Renouvellement::where('campaign_id', $campagne->id)
            ->whereDate('fin_campagne', $campagne->end_date->toDateString())
            ->first();
    }

    /**
     * Envoie l'étape du jour si le client n'a pas encore répondu.
     *
     * @param bool    $forcerTest  envoi de test (admin) : adresses de test, même si déjà parti
     * @param ?string $etape       imposer une étape (test, ou --etape)
     * @return ?RelanceFinCampagne  null si rien à envoyer aujourd'hui
     */
    public function traiter(Campaign $campagne, bool $forcerTest = false, ?string $etape = null): ?RelanceFinCampagne
    {
        $etape ??= self::etapePour($this->socle->joursRestants($campagne));
        if ($etape === null) {
            return null;
        }

        $dossier = $this->dossier($campagne);
        if (!$forcerTest && $dossier->statut !== Renouvellement::EN_ATTENTE) {
            return null; // le client a répondu, ou l'équipe a tranché : séquence arrêtée
        }

        return $this->socle->envoyerEtJournaliser(
            $campagne,
            $etape,
            $forcerTest,
            fn(string $to, ?string $note, array $copie, array $a, int $jours) => new MailClientCampagne(
                campaign: $campagne,
                cle: $etape,
                daysRemaining: $jours,
                contact: $a['contact'],
                lienDesinscription: DiffusionDesinscription::lien($to),
                repondreA: $a['repondre_a'],
                copie: $copie,
                noteTest: $note,
                // Mail de test : liens « essai », qui n'enregistrent rien.
                liens: $this->liens($dossier, $etape, essai: $note !== null),
            ),
            regleAutresCampagnes: false,
        );
    }

    /** @return array<string, string>  choix → lien personnel du bouton */
    public function liens(Renouvellement $dossier, string $etape, bool $essai): array
    {
        $choix = ModelesMail::BOUTONS[$etape] ?? array_keys(ModelesMail::CHOIX);

        return collect($choix)->mapWithKeys(fn($c) => [$c => $essai
            ? route('renouvellement.essai', ['dossier' => $dossier->id, 'sig' => self::signatureEssai($dossier), 'choix' => $c])
            : route('renouvellement.show', ['token' => $dossier->token, 'choix' => $c])])->all();
    }

    public static function signatureEssai(Renouvellement $dossier): string
    {
        return substr(hash_hmac('sha256', 'renouvellement-essai|' . $dossier->id . '|' . $dossier->token, (string) config('app.key')), 0, 32);
    }

    /** Lien du client encore valable ? Jusqu'à 30 jours après la fin de la campagne. */
    public static function lienValable(Renouvellement $dossier): bool
    {
        return today()->lte($dossier->fin_campagne->copy()->addDays(30));
    }

    /**
     * Un mail de l'étape, pour l'aperçu (écran des textes, carte admin).
     * Ne crée rien en base ; boutons « essai » si le dossier existe déjà.
     */
    public function mail(Campaign $campagne, string $cle, ?array $modele = null, ?string $noteTest = null): MailClientCampagne
    {
        $a       = $this->socle->coordonnees($campagne);
        $dossier = $this->dossierExistant($campagne);
        $accuse  = $cle === RelanceFinCampagne::ETAPE_ACCUSE;

        return new MailClientCampagne(
            campaign: $campagne,
            cle: $cle,
            daysRemaining: $campagne->end_date ? $this->socle->joursRestants($campagne) : 0,
            contact: $a['contact'],
            lienDesinscription: filter_var($a['destinataire'], FILTER_VALIDATE_EMAIL) ? DiffusionDesinscription::lien($a['destinataire']) : null,
            repondreA: $a['repondre_a'],
            copie: $accuse ? [] : $a['copie'],
            noteTest: $noteTest,
            liens: $dossier ? $this->liens($dossier, $cle, essai: true) : [],
            reponse: $accuse ? ModelesMail::CHOIX['renouveler'] : null,
            message: $accuse ? 'Exemple : nous souhaitons garder les mêmes emplacements pour 12 mois.' : null,
            modele: $modele,
        );
    }

    // ══════════════════════════════════════════════════════════════
    // RÉPONSES ET DÉCISIONS
    // ══════════════════════════════════════════════════════════════

    /** Réponse du client (page des boutons) : enregistrée, accusé, équipe prévenue. */
    public function enregistrerReponse(Renouvellement $dossier, string $choix, ?string $message): void
    {
        $message = self::nettoyer($message);

        $dossier->fill([
            'statut'          => $choix,
            'reponse_choix'   => $choix,
            'reponse_message' => $message,
            'reponse_at'      => now(),
            'reponse_par'     => 'client',
        ]);
        $dossier->noter('Réponse du client : ' . ModelesMail::CHOIX[$choix], $message, 'Client');
        $dossier->save();

        $this->apresReponse($dossier, $choix, $message, essai: false);
    }

    /** Réponse depuis un mail de test : rien n'est enregistré, les mails partent en test. */
    public function simulerReponse(Renouvellement $dossier, string $choix, ?string $message): void
    {
        $this->apresReponse($dossier, $choix, self::nettoyer($message), essai: true);
    }

    private function apresReponse(Renouvellement $dossier, string $choix, ?string $message, bool $essai): void
    {
        $campagne = Campaign::withTrashed()->with(['client.contacts', 'commercial', 'user'])->find($dossier->campaign_id);
        if (!$campagne || !$campagne->end_date) {
            return;
        }

        try {
            $this->socle->envoyerEtJournaliser(
                $campagne,
                RelanceFinCampagne::ETAPE_ACCUSE,
                $essai,
                fn(string $to, ?string $note, array $copie, array $a, int $jours) => new MailClientCampagne(
                    campaign: $campagne,
                    cle: RelanceFinCampagne::ETAPE_ACCUSE,
                    daysRemaining: $jours,
                    contact: $a['contact'],
                    lienDesinscription: DiffusionDesinscription::lien($to),
                    repondreA: $a['repondre_a'],
                    copie: [], // l'équipe reçoit son propre mail
                    noteTest: $note,
                    reponse: ModelesMail::CHOIX[$choix],
                    message: $message,
                ),
                regleAutresCampagnes: false,
                renvoyer: true,
            );
        } catch (\Throwable $e) {
            Log::error('renouvellement.accuse.echec', ['dossier' => $dossier->id, 'erreur' => $e->getMessage()]);
        }

        $this->prevenirEquipe($campagne, $dossier, $choix, $message, $essai);
    }

    /** Mail interne au commercial de la campagne, commercial@ en copie. */
    private function prevenirEquipe(Campaign $campagne, Renouvellement $dossier, string $choix, ?string $message, bool $essai): void
    {
        $commercial = $campagne->resolveCommercialContact();
        $copie      = $this->socle->copie();
        $pour       = filter_var($commercial?->email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($commercial->email) : ($copie[0] ?? null);

        if ($essai || $this->socle->estModeTest()) {
            $note = 'En production, ce mail irait à ' . ($pour ?? '—')
                . ($copie ? ', copie ' . implode(', ', $copie) : '') . '.';
            $destinataires = DiffusionDisponibilitesService::adresses(config('diffusion.confirmation_email'));
            $cc = null;
        } else {
            $note = null;
            $destinataires = $pour ? [$pour] : [];
            $cc = collect($copie)->first(fn($e) => $e !== $pour);
        }

        foreach ($destinataires as $email) {
            $this->mailer->sendSilently(
                $email,
                new RenouvellementReponseMail($dossier, $campagne, $choix, $message, $note),
                cc: $cc,
                context: ['renouvellement_id' => $dossier->id, 'choix' => $choix],
            );
        }
    }

    /**
     * Décision de l'équipe après un appel : statut, note, date de rappel.
     *
     * @param string|false|null $rappelLe  date « Y-m-d » ; null = effacer ; false = inchangée
     */
    public function changerStatut(Renouvellement $dossier, string $statut, ?string $note, User $auteur, string|false|null $rappelLe = false): void
    {
        $avant    = $dossier->statut;
        $note     = self::nettoyer($note);
        $libelles = array_map(fn($s) => $s[0], Renouvellement::STATUTS);
        $actions  = [];

        if ($statut !== $avant) {
            $dossier->statut = $statut;
            if ($statut === Renouvellement::CONFIRME) {
                $dossier->confirme_par = $auteur->id;
                $dossier->confirme_at  = now();
            } elseif ($avant === Renouvellement::CONFIRME) {
                $dossier->confirme_par = null;
                $dossier->confirme_at  = null;
            }
            $actions[] = "Statut : {$libelles[$avant]} → {$libelles[$statut]}";
        }

        if ($rappelLe !== false) {
            $nouvelle = $rappelLe ? Carbon::parse($rappelLe)->toDateString() : null;
            if ($nouvelle !== $dossier->rappel_le?->toDateString()) {
                $dossier->rappel_le = $nouvelle;
                $actions[] = $nouvelle ? 'Rappel prévu le ' . Carbon::parse($nouvelle)->format('d/m/Y') : 'Rappel retiré';
            }
        }

        if ($actions || $note) {
            $dossier->noter($actions ? implode(' · ', $actions) : 'Note', $note, $auteur->name);
        }

        $dossier->save();
    }

    /**
     * La campagne a été renouvelée avec le bouton « Renouveler la campagne »
     * de sa fiche (CampaignController::duplicate) : les panneaux sont
     * réservés pour la période suivante. Le dossier passe à
     * « Renouvellement confirmé » et les relances au client s'arrêtent.
     */
    public function campagneRenouvelee(Campaign $source, Campaign $nouvelle, ?User $auteur): void
    {
        if (!$source->end_date || !$source->estAnnuelle()) {
            return;
        }

        $dossier  = $this->dossier($source);
        $texte    = "Campagne « {$nouvelle->name} » créée du {$nouvelle->start_date?->format('d/m/Y')} au {$nouvelle->end_date?->format('d/m/Y')}.";
        $libelles = array_map(fn($s) => $s[0], Renouvellement::STATUTS);

        if ($dossier->statut !== Renouvellement::CONFIRME) {
            $dossier->noter("Renouvellement saisi dans Panora · Statut : {$libelles[$dossier->statut]} → {$libelles[Renouvellement::CONFIRME]}", $texte, $auteur?->name);
            $dossier->statut       = Renouvellement::CONFIRME;
            $dossier->confirme_par = $auteur?->id;
            $dossier->confirme_at  = now();
        } else {
            $dossier->noter('Renouvellement saisi dans Panora', $texte, $auteur?->name);
        }

        $dossier->save();
    }

    private static function nettoyer(?string $texte): ?string
    {
        $texte = trim((string) $texte);

        return $texte === '' ? null : mb_substr($texte, 0, 2000);
    }

    // ══════════════════════════════════════════════════════════════
    // CATALOGUE DES DISPONIBILITÉS
    // ══════════════════════════════════════════════════════════════

    /**
     * Panneaux des renouvellements confirmés par le MP : ils ne sont plus
     * proposés dans le catalogue envoyé aux clients. La retenue court
     * jusqu'à N jours après la fin de la campagne (config
     * diffusion.renouvellement.retenue_jours) : le temps de saisir la
     * nouvelle réservation, qui prend ensuite le relais.
     *
     * @return int[]
     */
    public static function panneauxRetenus(): array
    {
        try {
            $jours = (int) config('diffusion.renouvellement.retenue_jours', 30);

            $campagnes = Renouvellement::where('statut', Renouvellement::CONFIRME)
                ->whereDate('fin_campagne', '>=', today()->subDays($jours)->toDateString())
                ->pluck('campaign_id');

            if ($campagnes->isEmpty()) {
                return [];
            }

            return DB::table('campaign_panels')
                ->whereIn('campaign_id', $campagnes)
                ->where('type', 'interne')
                ->whereNotNull('panel_id')
                ->distinct()
                ->pluck('panel_id')
                ->map(fn($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            // Ne jamais bloquer l'envoi des disponibilités pour ça.
            Log::error('renouvellement.panneaux_retenus', ['erreur' => $e->getMessage()]);

            return [];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ÉCRAN « RENOUVELLEMENTS » ET MAIL DU MATIN
    // ══════════════════════════════════════════════════════════════

    /**
     * Campagnes annuelles qui se terminent dans les $horizon jours (et
     * celles terminées depuis moins de 30 jours, pour finir le suivi),
     * avec leur dossier, la frise des mails et la prochaine action.
     *
     * @param ?int $commercialId  périmètre d'un commercial (Campaign::forCommercialUser)
     * @return Collection<int, array>
     */
    public function lignes(?int $commercialId = null, int $horizon = 90, ?int $campagneId = null): Collection
    {
        $q = Campaign::query()
            ->whereIn('status', [CampaignStatus::ACTIF->value, CampaignStatus::TERMINE->value])
            ->whereDate('end_date', '>=', today()->subDays(30)->toDateString())
            ->whereDate('end_date', '<=', today()->addDays($horizon)->toDateString())
            ->with(['client.contacts', 'commercial:id,name,email', 'user:id,name,email', 'reservation'])
            ->withCount('panels')
            ->orderBy('end_date');

        if ($commercialId) {
            $q->forCommercialUser($commercialId);
        }
        if ($campagneId) {
            $q->whereKey($campagneId);
        }

        $campagnes = $q->get()->filter(fn(Campaign $c) => $c->estAnnuelle())->values();
        if ($campagnes->isEmpty()) {
            return collect();
        }

        $cle = fn($id, $fin) => $id . '|' . Carbon::parse($fin)->toDateString();

        $dossiers = Renouvellement::with('confirmateur:id,name')->whereIn('campaign_id', $campagnes->pluck('id'))->get()
            ->keyBy(fn(Renouvellement $d) => $cle($d->campaign_id, $d->fin_campagne));

        $journal = RelanceFinCampagne::whereIn('campaign_id', $campagnes->pluck('id'))
            ->where('mode', $this->socle->mode())
            ->whereIn('etape', [...array_keys(self::ETAPES), RelanceFinCampagne::ETAPE_ACCUSE])
            ->get()
            ->groupBy(fn(RelanceFinCampagne $l) => $cle($l->campaign_id, $l->fin_campagne))
            ->map(fn(Collection $lignes) => $lignes->keyBy('etape'));

        $planifiees = Campaign::whereIn('client_id', $campagnes->pluck('client_id')->filter())
            ->where('status', CampaignStatus::PLANIFIE->value)
            ->get(['id', 'name', 'client_id'])
            ->groupBy('client_id');

        return $campagnes->map(function (Campaign $c) use ($cle, $dossiers, $journal, $planifiees) {
            $k       = $cle($c->id, $c->end_date);
            $dossier = $dossiers[$k] ?? null;
            $statut  = $dossier?->statut ?? Renouvellement::EN_ATTENTE;
            $jours   = $this->socle->joursRestants($c);
            $mails   = $journal[$k] ?? collect();
            // filter() et non only() : sur une collection Eloquent, only() filtre par id de ligne.
            $envoyes = $mails->filter(fn($m, $etape) => isset(self::ETAPES[$etape]) && $m->statut === RelanceFinCampagne::STATUT_ENVOYE);

            $ligne = [
                'campagne'        => $c,
                'dossier'         => $dossier,
                'statut'          => $statut,
                'jours'           => $jours,
                'montant'         => (int) round($c->computedAmountHt()),
                'panneaux'        => (int) ($c->total_panels ?: $c->panels_count),
                'commercial'      => $c->resolveCommercialContact(),
                'contact'         => $c->client?->destinataireCommercial(),
                'telephone'       => self::telephone($c->client),
                'dernier_mail'    => $envoyes->sortBy(fn($l) => self::ETAPES[$l->etape] ?? 0)->first(),
                'prochain_mail'   => $statut === Renouvellement::EN_ATTENTE ? self::prochainMail($c, $jours, $envoyes->keys()->all()) : null,
                'sequence'        => self::sequence($c, $statut, $mails, $jours),
                'accuse'          => $mails[RelanceFinCampagne::ETAPE_ACCUSE] ?? null,
                'autre_planifiee' => $c->client_id ? ($planifiees[$c->client_id] ?? collect())->first()?->name : null,
            ];
            $ligne['action'] = self::action($ligne);

            return $ligne;
        });
    }

    /**
     * Frise des 5 mails : pour chaque étape, ce qui s'est passé ou se passera.
     *
     * @param Collection<string, RelanceFinCampagne> $mails  journal de la campagne, par étape
     * @return list<array{etape: string, jours: int, date: Carbon, etat: string, info: string}>
     *         etat : envoye | echec | exclu | aujourdhui | prevu | arrete | saute
     */
    public static function sequence(Campaign $c, string $statut, Collection $mails, int $joursRestants): array
    {
        $due = self::etapePour($joursRestants);
        $frise = [];

        foreach (self::ETAPES as $etape => $j) {
            $date  = $c->end_date->copy()->subDays($j);
            $ligne = $mails[$etape] ?? null;

            [$etat, $info] = match (true) {
                $ligne?->statut === RelanceFinCampagne::STATUT_ENVOYE => ['envoye', 'Envoyé le ' . ($ligne->envoye_at ?? $ligne->updated_at)->format('d/m/Y')],
                $ligne?->statut === RelanceFinCampagne::STATUT_ECHEC  => ['echec', 'À retenter : ' . $ligne->motif],
                $ligne?->statut === RelanceFinCampagne::STATUT_EXCLU  => ['exclu', 'Non envoyé : ' . $ligne->motif],
                $statut !== Renouvellement::EN_ATTENTE                => ['arrete', 'Pas envoyé : relances arrêtées'],
                $due === $etape                                       => ['aujourdhui', 'Part au prochain envoi (10 h)'],
                $j < $joursRestants                                   => ['prevu', 'Prévu le ' . $date->format('d/m/Y')],
                default                                               => ['saute', 'Non envoyé : date passée'],
            };

            $frise[] = ['etape' => $etape, 'jours' => $j, 'date' => $date, 'etat' => $etat, 'info' => $info];
        }

        return $frise;
    }

    /**
     * Prochaine action à faire sur le dossier.
     *
     * @return array{0: string, 1: string}  [texte, ton : ok | info | alerte | urgent | neutre]
     */
    public static function action(array $l): array
    {
        $rappel = $l['dossier']?->rappel_le;

        return match (true) {
            $l['statut'] === Renouvellement::CONFIRME   => ['Renouvellement confirmé', 'ok'],
            $l['statut'] === Renouvellement::REFUS      => ['Aucune : ne renouvelle pas', 'neutre'],
            $l['statut'] === Renouvellement::RAPPEL     => ['Rappeler le client (à sa demande)', 'urgent'],
            in_array($l['statut'], [Renouvellement::RENOUVELER, Renouvellement::AJUSTER], true)
                                                        => ['À confirmer par le MP', 'alerte'],
            $rappel !== null && $rappel->lte(today())   => [$rappel->isToday() ? 'Rappel prévu aujourd\'hui' : 'Rappel en retard (' . $rappel->format('d/m') . ')', 'urgent'],
            $rappel !== null                            => ['Rappel prévu le ' . $rappel->format('d/m'), 'info'],
            $l['jours'] <= 0                            => ['Terminée sans réponse', 'neutre'],
            $l['jours'] <= self::JOURS_APPEL            => ['Appeler le client', 'urgent'],
            $l['prochain_mail'] !== null                => [
                'Mail ' . strtoupper(str_replace('j', 'J-', $l['prochain_mail'][0])) . ' '
                    . ($l['prochain_mail'][1]->isToday() ? 'aujourd\'hui' : 'le ' . $l['prochain_mail'][1]->format('d/m')),
                'info',
            ],
            default                                     => ['En attente de réponse', 'info'],
        };
    }

    /**
     * Prochaine campagne annuelle active qui finit APRÈS l'horizon affiché
     * (pour expliquer une liste vide).
     */
    public function prochaineAnnuelle(?int $commercialId, int $apresJours): ?Campaign
    {
        $q = Campaign::query()
            ->where('status', CampaignStatus::ACTIF->value)
            ->whereDate('end_date', '>', today()->addDays($apresJours)->toDateString())
            ->with('client:id,name')
            ->orderBy('end_date');

        if ($commercialId) {
            $q->forCommercialUser($commercialId);
        }

        return $q->limit(200)->get()->first(fn(Campaign $c) => $c->estAnnuelle());
    }

    /**
     * Prochain mail de la séquence : [étape, date].
     *
     * @param string[] $dejaEnvoyees
     * @return ?array{0: string, 1: Carbon}
     */
    public static function prochainMail(Campaign $c, int $jours, array $dejaEnvoyees): ?array
    {
        $due = self::etapePour($jours);
        if ($due && !in_array($due, $dejaEnvoyees, true)) {
            return [$due, today()];
        }

        foreach (self::ETAPES as $etape => $j) {
            if ($j < $jours && !in_array($etape, $dejaEnvoyees, true)) {
                return [$etape, $c->end_date->copy()->subDays($j)];
            }
        }

        return null;
    }

    /**
     * Indicateurs de l'écran. Définitions (affichées dans le guide) :
     *   · en attente  : sans réponse, échéance dans 60 jours ou moins ;
     *   · à risque    : CA en attente à 30 jours ou moins (date limite passée) ;
     *   · en discussion : veut renouveler, veut ajuster, demande un rappel ;
     *   · sécurisé    : renouvellement confirmé ;
     *   · perdu       : ne renouvelle pas ;
     *   · taux de réponse : clients ayant répondu / clients ayant reçu au moins un mail.
     *
     * @return array<string, mixed>
     */
    public static function compteurs(Collection $lignes): array
    {
        $fenetre  = fn(int $max) => $lignes->filter(fn($l) => $l['jours'] > 0 && $l['jours'] <= $max);
        $bloc     = fn(Collection $c) => ['nb' => $c->count(), 'montant' => (int) $c->sum('montant')];
        $attente  = $lignes->filter(fn($l) => $l['statut'] === Renouvellement::EN_ATTENTE && $l['jours'] > 0 && $l['jours'] <= max(self::ETAPES));
        $sollicites = $lignes->filter(fn($l) => $l['dernier_mail'] !== null);

        return [
            'a_venir'      => $bloc($lignes->filter(fn($l) => $l['jours'] > 0)),
            '30'           => $bloc($fenetre(30)),
            '60'           => $bloc($fenetre(60)),
            '90'           => $bloc($fenetre(90)),
            'attente'      => $bloc($attente),
            'a_risque'     => (int) $attente->filter(fn($l) => $l['jours'] <= 30)->sum('montant'),
            'sans_reponse' => $attente->filter(fn($l) => $l['jours'] <= self::JOURS_APPEL)->count(),
            'discussion'   => $bloc($lignes->whereIn('statut', [Renouvellement::RENOUVELER, Renouvellement::AJUSTER, Renouvellement::RAPPEL])),
            'confirmes'    => $bloc($lignes->where('statut', Renouvellement::CONFIRME)),
            'perdus'       => $bloc($lignes->where('statut', Renouvellement::REFUS)),
            'renouvellent' => $lignes->whereIn('statut', [Renouvellement::RENOUVELER, Renouvellement::CONFIRME])->count(),
            'a_confirmer'  => $lignes->whereIn('statut', [Renouvellement::RENOUVELER, Renouvellement::AJUSTER])->count(),
            'refus'        => $lignes->where('statut', Renouvellement::REFUS)->count(),
            'taux_reponse' => $sollicites->isEmpty() ? null
                : (int) round(100 * $sollicites->filter(fn($l) => $l['dossier']?->reponse_at !== null)->count() / $sollicites->count()),
        ];
    }

    /**
     * Mail du matin. Commerciaux : rappels prévus ce jour, clients sans
     * réponse à appeler (dès J-25), demandes de rappel, réponses reçues
     * depuis le dernier mail. MP : renouvellements à confirmer.
     *
     * @return Collection<int, array{email: string, nom: string, role: string, a_rappeler: Collection, appeler: Collection, rappels: Collection, reponses: Collection, a_confirmer: Collection}>
     */
    public function digest(): Collection
    {
        $lignes = $this->lignes(null, max(self::ETAPES));
        // Lundi : tout ce qui est arrivé depuis vendredi matin.
        $depuis = now()->subDays(now()->isMonday() ? 3 : 1);

        $copie   = $this->socle->copie()[0] ?? null;
        $parCommercial = $lignes->groupBy(fn($l) => mb_strtolower((string) ($l['commercial']?->email ?: $copie)));

        $mails = collect();
        foreach ($parCommercial as $email => $siennes) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $aRappeler = $siennes->filter(fn($l) => $l['dossier']?->rappel_le?->lte(today())
                && !in_array($l['statut'], [Renouvellement::CONFIRME, Renouvellement::REFUS], true))->values();
            $appeler  = $siennes->filter(fn($l) => $l['statut'] === Renouvellement::EN_ATTENTE && $l['jours'] > 0 && $l['jours'] <= self::JOURS_APPEL)
                ->reject(fn($l) => $aRappeler->contains(fn($r) => $r['campagne']->id === $l['campagne']->id))->values();
            $rappels  = $siennes->where('statut', Renouvellement::RAPPEL)->values();
            $reponses = $siennes->filter(fn($l) => $l['dossier']?->reponse_at?->gte($depuis))->values();

            if ($aRappeler->isEmpty() && $appeler->isEmpty() && $rappels->isEmpty() && $reponses->isEmpty()) {
                continue;
            }

            $mails->push([
                'email'       => $email,
                'nom'         => $siennes->first()['commercial']?->name ?? 'Équipe commerciale',
                'role'        => 'commercial',
                'a_rappeler'  => $aRappeler,
                'appeler'     => $appeler,
                'rappels'     => $rappels,
                'reponses'    => $reponses,
                'a_confirmer' => collect(),
            ]);
        }

        $aConfirmer = $lignes->whereIn('statut', [Renouvellement::RENOUVELER, Renouvellement::AJUSTER])->values();
        if ($aConfirmer->isNotEmpty()) {
            foreach (User::where('role', 'mediaplanner')->where('is_active', true)->get(['id', 'name', 'email']) as $mp) {
                if (filter_var($mp->email, FILTER_VALIDATE_EMAIL)) {
                    $mails->push([
                        'email'       => mb_strtolower($mp->email),
                        'nom'         => $mp->name,
                        'role'        => 'mediaplanner',
                        'a_rappeler'  => collect(),
                        'appeler'     => collect(),
                        'rappels'     => collect(),
                        'reponses'    => collect(),
                        'a_confirmer' => $aConfirmer,
                    ]);
                }
            }
        }

        return $mails;
    }

    /**
     * Encart « Renouvellement » de la fiche campagne : dossier, statut et
     * prochaine action, ou null si la campagne n'est pas annuelle.
     */
    public function resume(Campaign $campagne): ?array
    {
        if (!$campagne->end_date || !$campagne->estAnnuelle()) {
            return null;
        }

        $ligne = $this->lignes(null, RenouvellementService::HORIZON_TOUTES, $campagne->id)->first();

        return $ligne ?? [
            'campagne' => $campagne, 'dossier' => $this->dossierExistant($campagne),
            'statut'   => $this->dossierExistant($campagne)?->statut ?? Renouvellement::EN_ATTENTE,
            'action'   => ['Campagne terminée', 'neutre'], 'sequence' => [], 'jours' => $this->socle->joursRestants($campagne),
        ];
    }

    /** Téléphone à appeler : celui de l'interlocuteur principal, sinon de la fiche client. */
    public static function telephone(?Client $client): ?string
    {
        if (!$client) {
            return null;
        }

        $contact = $client->contacts->first(fn(ClientContact $c) => trim((string) $c->phone) !== '');

        return trim((string) ($contact?->phone ?: $client->phone)) ?: null;
    }
}
