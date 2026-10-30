<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientRegroupement;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Relance;
use App\Models\User;
use App\Services\AlertService;
use App\Support\NomEntreprise;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Regrouper deux fiches client (2026-10-30) — source unique de la règle.
 *
 * Règles validées par la direction le 2026-10-30 :
 *   · Direction seulement ; la fiche gardée garde ses informations, ses
 *     champs vides sont complétés par l'autre fiche (un NCC auto-généré
 *     « CLT-AAAA-N » compte comme vide). Une valeur différente de l'autre
 *     fiche est notée dans le journal du regroupement.
 *   · Tout ce qui appartient à l'autre fiche passe à la fiche gardée, y
 *     compris ce qui est à la corbeille (14 tables, structure réelle
 *     vérifiée sur MySQL le 2026-10-30). Mises à jour directes en base :
 *     aucun mail, aucun changement de statut, aucune tâche de pose.
 *   · Interlocuteurs : une même adresse n'est gardée qu'une fois (les
 *     dossiers sont repointés vers l'interlocuteur gardé). « Reçoit les
 *     mails » reste coché seulement pour l'interlocuteur d'un dossier
 *     déplacé (règle du 2026-10-29). Le principal de la fiche gardée le reste.
 *   · Statut : client si l'une des deux l'est. « Client depuis » : la date
 *     la plus ancienne.
 *   · Refus : fiche à retirer avec un compte ou une équipe d'espace client ;
 *     NCC différents et factures déjà émises (deux sociétés, sans doute) ;
 *     table inconnue portant un client_id dans la base.
 *   · Factures déjà émises : elles se réimpriment au nom de la fiche gardée
 *     (le PDF relit la fiche) → case « J'ai compris » obligatoire.
 *   · « Relier » une réservation et une campagne de l'autre fiche :
 *     seulement si rien ne peut se déclencher derrière (cf. liaisons()).
 *
 * L'ancienne fiche est mise à la corbeille, son NCC libéré (index unique
 * qui compte aussi les fiches supprimées). ClientRegroupement garde la
 * correspondance : nom, Sheet et anciens liens mènent à la fiche gardée.
 */
final class RegroupementClients
{
    /** Tables dont les lignes passent à la fiche gardée. */
    public const TABLES = [
        'campaigns'             => 'campagnes',
        'reservations'          => 'réservations',
        'propositions'          => 'propositions',
        'invoices'              => 'factures',
        'quotes'                => 'devis',
        'client_contacts'       => 'interlocuteurs',
        'client_users'          => "membres de l'espace client",
        'client_messages'       => 'messages « Contacter la régie »',
        'public_links'          => 'liens publics',
        'relances'              => 'relances de factures',
        'relances_fin_campagne' => 'mails de fin de campagne',
        'renouvellements'       => 'dossiers de renouvellement',
        'satisfaction_surveys'  => 'enquêtes de satisfaction',
        'external_panels'       => 'panneaux de régies externes',
    ];

    /** Tables qui portent un client_id sans appartenir à la fiche. */
    private const TABLES_HORS_FICHE = ['client_regroupements'];

    /** Champs de la fiche complétés quand ils sont vides sur la fiche gardée. */
    public const CHAMPS = [
        'email'             => 'E-mail',
        'phone'             => 'Téléphone',
        'address'           => 'Adresse',
        'contact_name'      => 'Contact',
        'sector'            => 'Secteur',
        'ncc'               => 'NCC',
        'ifu'               => 'IFU',
        'rccm'              => 'RCCM',
        'regime_imposition' => "Régime d'imposition",
        'user_id'           => 'Commercial',
    ];

    /** Une facture « émise » : ni brouillon ni annulée. */
    private const FACTURES_NON_EMISES = ['brouillon', 'annulee'];

    /** Dossiers qui peuvent désigner un interlocuteur. */
    private const DOSSIERS_INTERLOCUTEUR = ['campaigns', 'reservations', 'quotes'];

    // ══════════════════════════════════════════════════════════════
    // APERÇU — ce que fera le regroupement, sans rien modifier
    // ══════════════════════════════════════════════════════════════

    /**
     * @return array{retirer: array, garder: array, refus: list<string>, compteurs: list<array>,
     *               factures_emises: list<string>, champs: list<array>, contacts: array,
     *               liaisons: list<array>, statut: string}
     */
    public function apercu(Client $retirer, Client $garder): array
    {
        $refus = $this->refus($retirer, $garder);
        $memeFiche = (int) $retirer->id === (int) $garder->id;

        return [
            'retirer'         => $this->carte($retirer),
            'garder'          => $this->carte($garder),
            'refus'           => $refus,
            'compteurs'       => $memeFiche ? [] : $this->compteurs($retirer),
            'factures_emises' => $memeFiche ? [] : $this->facturesEmises($retirer),
            'champs'          => $memeFiche ? [] : $this->champs($retirer, $garder),
            'contacts'        => $memeFiche ? ['deplaces' => 0, 'fusionnes' => [], 'decoches' => 0] : $this->planContacts($retirer, $garder)['resume'],
            'liaisons'        => $memeFiche ? [] : $this->liaisons($retirer, $garder),
            'statut'          => Client::STATUTS[$this->statutFinal($retirer, $garder)] ?? 'Client',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // REGROUPEMENT
    // ══════════════════════════════════════════════════════════════

    /**
     * @param list<array{0: int, 1: int}> $liaisons  paires [réservation, campagne] à relier
     *
     * @throws ValidationException  refus, case « J'ai compris » manquante, liaison impossible
     */
    public function regrouper(Client $retirer, Client $garder, ?User $par = null, array $liaisons = [], bool $facturesComprises = false): ClientRegroupement
    {
        $resultat = DB::transaction(function () use ($retirer, $garder, $par, $liaisons, $facturesComprises) {
            // Relecture verrouillée : deux regroupements simultanés ne se croisent pas.
            $fiches = Client::withTrashed()->whereKey([$retirer->id, $garder->id])->lockForUpdate()->get()->keyBy('id');
            $retirer = $fiches->get($retirer->id) ?? $retirer;
            $garder = $fiches->get($garder->id) ?? $garder;

            if ($refus = $this->refus($retirer, $garder)) {
                throw ValidationException::withMessages(['regroupement' => $refus]);
            }
            $factures = $this->facturesEmises($retirer);
            if ($factures && !$facturesComprises) {
                throw ValidationException::withMessages(['factures_comprises' => "Cochez « J'ai compris » : les factures déjà émises de « {$retirer->name} » s'afficheront au nom de « {$garder->name} »."]);
            }
            $aRelier = $this->liaisonsDemandees($retirer, $garder, $liaisons);

            $detail = [
                'compteurs'       => $this->compteurs($retirer),
                'factures_emises' => $factures,
                'champs'          => $this->champs($retirer, $garder),
                'ancien_ncc'      => $retirer->ncc,
                'ancien_email'    => $retirer->email,
            ];

            // Ids des documents suivis par l'historique, avant de les déplacer.
            $audites = [];
            foreach ([Invoice::class => 'invoices', Quote::class => 'quotes', Relance::class => 'relances'] as $modele => $table) {
                if (class_exists($modele) && Schema::hasTable($table)) { // pas de module Devis sur main
                    $audites[$modele] = DB::table($table)->where('client_id', $retirer->id)->pluck('id')->all();
                }
            }

            $detail['contacts'] = $this->deplacerContacts($retirer, $garder);
            $this->completerFiche($retirer, $garder);

            foreach (array_keys(self::TABLES) as $table) {
                if ($table !== 'client_contacts' && Schema::hasTable($table)) {
                    DB::table($table)->where('client_id', $retirer->id)->update(['client_id' => $garder->id]);
                }
            }

            foreach ($aRelier as [$reservationId, $campagneId]) {
                DB::table('campaigns')->where('id', $campagneId)->whereNull('reservation_id')
                    ->update(['reservation_id' => $reservationId]);
            }
            $detail['liaisons'] = $aRelier;

            // Une fiche déjà regroupée dans celle qu'on retire pointe désormais vers la gardée.
            DB::table('client_regroupements')->where('client_id', $retirer->id)->update(['client_id' => $garder->id, 'updated_at' => now()]);

            // Corbeille + NCC libéré (l'index unique compte aussi les fiches supprimées).
            DB::table('clients')->where('id', $retirer->id)->update(['ncc' => null]);
            $retirer->refresh()->delete();

            $restants = $this->compteurs($retirer);
            if ($restants) {
                throw ValidationException::withMessages(['regroupement' => ['Des éléments sont restés sur « ' . $retirer->name . ' » : regroupement annulé, rien n\'a été modifié.']]);
            }

            $journal = ClientRegroupement::create([
                'ancien_client_id' => $retirer->id,
                'ancien_nom'       => $retirer->name,
                'client_id'        => $garder->id,
                'user_id'          => $par?->id,
                'detail'           => $detail,
            ]);

            return [$journal, $audites, $retirer, $garder];
        });

        [$journal, $audites, $retirer, $garder] = $resultat;
        app(NomEntreprise::class)->oublier();
        $this->historiser($audites, $retirer, $garder);

        Log::info('client.regroupe', [
            'ancien_client_id' => $retirer->id,
            'client_id'        => $garder->id,
            'user_id'          => $par?->id,
            'compteurs'        => $journal->detail['compteurs'] ?? [],
        ]);
        // Le regroupement est fait : une alerte qui échoue ne doit pas le faire croire raté.
        try {
            AlertService::create(
                'client_modifie',
                'info',
                '🔀 Fiches regroupées — ' . $retirer->name . ' → ' . $garder->name,
                ($par?->name ?? 'Panora') . ' a regroupé la fiche ' . $retirer->name . ' avec ' . $garder->name . '.',
                $garder
            );
        } catch (\Throwable $e) {
            Log::warning('client.regroupe.alerte', ['erreur' => $e->getMessage()]);
        }

        return $journal;
    }

    // ══════════════════════════════════════════════════════════════
    // RÈGLES
    // ══════════════════════════════════════════════════════════════

    /** @return list<string> raisons de refus, vide si le regroupement est possible */
    public function refus(Client $retirer, Client $garder): array
    {
        if ((int) $retirer->id === (int) $garder->id) {
            return ['Choisissez deux fiches différentes.'];
        }
        $refus = [];
        foreach ([$retirer, $garder] as $fiche) {
            if ($fiche->trashed()) {
                $refus[] = "« {$fiche->name} » est déjà supprimée.";
            }
        }
        if (filled($retirer->password) || (Schema::hasTable('client_users') && DB::table('client_users')->where('client_id', $retirer->id)->exists())) {
            $refus[] = "« {$retirer->name} » a un accès à l'espace client (compte ou équipe) : gardez plutôt cette fiche-là, ou retirez d'abord cet accès.";
        }
        [$nccA, $nccB] = [self::nccReel($retirer->ncc), self::nccReel($garder->ncc)];
        if ($nccA && $nccB && NomEntreprise::cle($nccA) !== NomEntreprise::cle($nccB) && $this->facturesEmises($retirer)) {
            $refus[] = "NCC différents ({$nccA} / {$nccB}) et « {$retirer->name} » a des factures émises : ce sont sans doute deux sociétés différentes.";
        }
        if ($inconnues = $this->tablesInconnues()) {
            $refus[] = 'La base contient des données client non prévues (' . implode(', ', $inconnues) . ') : regroupement bloqué par sécurité.';
        }

        return $refus;
    }

    /**
     * Paires réservation / campagne des deux fiches qui partagent des
     * panneaux, et si on peut les relier sans effet caché. Relier n'est
     * possible que si :
     *   · la réservation est confirmée (une option peut expirer, et son
     *     annulation annulerait alors la campagne) ;
     *   · la campagne est active, en pause ou terminée, ou planifiée avec un
     *     début à venir — sinon Panora la lancerait la nuit suivante et
     *     préviendrait le client ;
     *   · mêmes dates, mêmes panneaux, même montant — sinon le montant de la
     *     campagne serait recalculé avec les prix de la réservation à la
     *     prochaine modification ;
     *   · aucun panneau ne démarre après la campagne (pose et lancement reportés).
     *
     * @return list<array{reservation_id: int, reservation: string, campagne_id: int, campagne: string, possible: bool, raisons: list<string>}>
     */
    public function liaisons(Client $retirer, Client $garder): array
    {
        $ids = [$retirer->id, $garder->id];
        $reservations = DB::table('reservations')->whereIn('client_id', $ids)->whereNull('deleted_at')
            ->whereIn('status', ['en_attente', 'confirme'])
            ->whereNotExists(fn ($q) => $q->from('campaigns')->whereColumn('campaigns.reservation_id', 'reservations.id')->whereNull('campaigns.deleted_at'))
            ->get(['id', 'reference', 'client_id', 'status', 'start_date', 'end_date']);
        $campagnes = DB::table('campaigns')->whereIn('client_id', $ids)->whereNull('deleted_at')->whereNull('reservation_id')
            ->get(['id', 'name', 'client_id', 'status', 'start_date', 'end_date', 'total_amount']);
        if ($reservations->isEmpty() || $campagnes->isEmpty()) {
            return [];
        }

        $panneauxR = DB::table('reservation_panels')->whereIn('reservation_id', $reservations->pluck('id'))
            ->get(['reservation_id', 'panel_id', 'external_panel_id', 'source', 'total_price', 'panel_start_date'])->groupBy('reservation_id');
        $panneauxC = DB::table('campaign_panels')->whereIn('campaign_id', $campagnes->pluck('id'))
            ->get(['campaign_id', 'panel_id', 'external_panel_id', 'type'])->groupBy('campaign_id');
        $jeu = fn ($lignes, string $col, string $valeur, string $id) => collect($lignes)->where($col, $valeur)->pluck($id)->filter()->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
        $jour = fn ($d) => $d ? Carbon::parse($d)->toDateString() : null;
        $fr = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
        $aujourdhui = now()->toDateString();

        $paires = [];
        foreach ($reservations as $r) {
            foreach ($campagnes as $c) {
                if ((int) $r->client_id === (int) $c->client_id) {
                    continue; // même fiche : pas l'objet du regroupement
                }
                $lr = $panneauxR->get($r->id, collect());
                $lc = $panneauxC->get($c->id, collect());
                $internesR = $jeu($lr, 'source', 'interne', 'panel_id');
                $internesC = $jeu($lc, 'type', 'interne', 'panel_id');
                if (!array_intersect($internesR, $internesC)) {
                    continue;
                }

                $raisons = [];
                if ($r->status !== 'confirme') {
                    $raisons[] = "la réservation n'est pas confirmée";
                }
                $debutC = $jour($c->start_date);
                if ($c->status === 'planifie' && $debutC && $debutC <= $aujourdhui) {
                    $raisons[] = 'la campagne est planifiée et sa date de début est passée : lancez-la d\'abord à la main';
                } elseif (!in_array($c->status, ['planifie', 'actif', 'pause', 'termine'], true)) {
                    $raisons[] = 'la campagne est annulée';
                }
                if ($jour($r->start_date) !== $debutC || $jour($r->end_date) !== $jour($c->end_date)) {
                    $raisons[] = 'dates différentes (réservation ' . $fr($r->start_date) . ' → ' . $fr($r->end_date) . ', campagne ' . $fr($c->start_date) . ' → ' . $fr($c->end_date) . ')';
                }
                if ($internesR !== $internesC || $jeu($lr, 'source', 'externe', 'external_panel_id') !== $jeu($lc, 'type', 'externe', 'external_panel_id')) {
                    $raisons[] = 'panneaux différents';
                }
                $montantR = (int) collect($lr)->sum('total_price');
                if ($c->total_amount === null || (int) $c->total_amount !== $montantR) {
                    $raisons[] = 'montants différents (réservation ' . number_format($montantR, 0, ',', ' ') . ' FCFA, campagne ' . ($c->total_amount === null ? 'non renseigné' : number_format((int) $c->total_amount, 0, ',', ' ') . ' FCFA') . ')';
                }
                if ($debutC && collect($lr)->contains(fn ($p) => $p->panel_start_date && $jour($p->panel_start_date) > $debutC)) {
                    $raisons[] = 'un panneau démarre après la campagne';
                }

                $paires[] = [
                    'reservation_id' => (int) $r->id,
                    'reservation'    => (string) $r->reference,
                    'campagne_id'    => (int) $c->id,
                    'campagne'       => (string) $c->name,
                    'possible'       => $raisons === [],
                    'raisons'        => $raisons,
                ];
            }
        }

        return $paires;
    }

    // ══════════════════════════════════════════════════════════════
    // ÉTAPES
    // ══════════════════════════════════════════════════════════════

    /** @return list<array{0: int, 1: int}> paires demandées, toutes vérifiées */
    private function liaisonsDemandees(Client $retirer, Client $garder, array $demandees): array
    {
        if (!$demandees) {
            return [];
        }
        $possibles = collect($this->liaisons($retirer, $garder))->where('possible', true)
            ->map(fn ($p) => $p['reservation_id'] . ':' . $p['campagne_id'])->all();
        $retenues = [];
        $prises = [];
        foreach ($demandees as [$r, $c]) {
            $cle = (int) $r . ':' . (int) $c;
            if (!in_array($cle, $possibles, true) || isset($prises['r' . $r]) || isset($prises['c' . $c])) {
                throw ValidationException::withMessages(['liaisons' => 'Cette réservation et cette campagne ne peuvent pas être reliées (ou l\'une est déjà reliée à un autre élément).']);
            }
            $prises['r' . $r] = $prises['c' . $c] = true;
            $retenues[] = [(int) $r, (int) $c];
        }

        return $retenues;
    }

    /**
     * Interlocuteurs de l'ancienne fiche → fiche gardée.
     *
     * @return array{resume: array{deplaces: int, fusionnes: list<string>, decoches: int}, actions: list<array>}
     */
    private function planContacts(Client $retirer, Client $garder): array
    {
        $norm = fn ($e) => mb_strtolower(trim((string) $e));
        $gardes = DB::table('client_contacts')->where('client_id', $garder->id)->whereNull('deleted_at')->orderBy('id')->get();
        $parEmail = [];
        foreach ($gardes as $g) {
            if (filter_var($norm($g->email), FILTER_VALIDATE_EMAIL)) {
                $parEmail[$norm($g->email)] ??= $g;
            }
        }
        $principal = $gardes->contains(fn ($g) => (bool) $g->is_primary);

        $interlocuteurs = [];
        foreach (self::DOSSIERS_INTERLOCUTEUR as $table) {
            if (Schema::hasColumn($table, 'client_contact_id')) {
                $interlocuteurs = array_merge($interlocuteurs, DB::table($table)->where('client_id', $retirer->id)->whereNotNull('client_contact_id')->pluck('client_contact_id')->map(fn ($v) => (int) $v)->all());
            }
        }

        $actions = [];
        $resume = ['deplaces' => 0, 'fusionnes' => [], 'decoches' => 0];
        foreach (DB::table('client_contacts')->where('client_id', $retirer->id)->orderByDesc('is_primary')->orderBy('id')->get() as $ct) {
            $email = $norm($ct->email);
            $double = $ct->deleted_at === null && isset($parEmail[$email]) ? $parEmail[$email] : null;
            if ($double) {
                $actions[] = ['type' => 'fusion', 'contact' => $ct, 'vers' => $double];
                $resume['fusionnes'][] = (string) $ct->name;
                continue;
            }
            $recoit = (bool) $ct->receives_notifications && in_array((int) $ct->id, $interlocuteurs, true);
            $estPrincipal = (bool) $ct->is_primary && !$principal && $ct->deleted_at === null;
            $principal = $principal || $estPrincipal;
            if ((bool) $ct->receives_notifications && !$recoit && $ct->deleted_at === null) {
                $resume['decoches']++;
            }
            if ($ct->deleted_at === null) {
                $resume['deplaces']++;
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $parEmail[$email] ??= $ct; // deux fois la même adresse sur l'ancienne fiche : une seule gardée
                }
            }
            $actions[] = ['type' => 'deplacer', 'contact' => $ct, 'recoit' => $recoit, 'principal' => $estPrincipal];
        }

        return ['resume' => $resume, 'actions' => $actions];
    }

    /** @return array{deplaces: int, fusionnes: list<string>, decoches: int} */
    private function deplacerContacts(Client $retirer, Client $garder): array
    {
        $plan = $this->planContacts($retirer, $garder);
        $statut = $this->statutFinal($retirer, $garder);

        foreach ($plan['actions'] as $a) {
            $ct = $a['contact'];
            if ($a['type'] === 'fusion') {
                $vers = $a['vers'];
                foreach (self::DOSSIERS_INTERLOCUTEUR as $table) {
                    if (Schema::hasColumn($table, 'client_contact_id')) {
                        DB::table($table)->where('client_contact_id', $ct->id)->update(['client_contact_id' => $vers->id]);
                    }
                }
                $complement = [];
                foreach (['phone', 'position'] as $champ) {
                    if (blank($vers->{$champ}) && filled($ct->{$champ})) {
                        $complement[$champ] = $ct->{$champ};
                    }
                }
                if ($complement) {
                    DB::table('client_contacts')->where('id', $vers->id)->update($complement + ['updated_at' => now()]);
                }
                DB::table('client_contacts')->where('id', $ct->id)->update([
                    'client_id' => $garder->id, 'is_primary' => false, 'deleted_at' => now(), 'updated_at' => now(),
                ]);
                continue;
            }
            DB::table('client_contacts')->where('id', $ct->id)->update([
                'client_id'              => $garder->id,
                'statut'                 => $statut,
                'is_primary'             => $a['principal'],
                'receives_notifications' => $a['recoit'],
                'updated_at'             => now(),
            ]);
        }

        return $plan['resume'];
    }

    /** Champs vides de la fiche gardée, statut, date d'entrée, adresse de l'ancienne fiche. */
    private function completerFiche(Client $retirer, Client $garder): void
    {
        $maj = [];
        foreach ($this->champs($retirer, $garder) as $c) {
            if ($c['action'] === 'complete') {
                $maj[$c['champ']] = $retirer->getRawOriginal($c['champ']);
            }
        }
        $maj['statut_commercial'] = $this->statutFinal($retirer, $garder);
        if ($retirer->created_at && $garder->created_at && $retirer->created_at->lt($garder->created_at)) {
            $maj['created_at'] = $retirer->getRawOriginal('created_at');
        }
        if (isset($maj['ncc'])) {
            DB::table('clients')->where('id', $retirer->id)->update(['ncc' => null]); // index unique
        }
        DB::table('clients')->where('id', $garder->id)->update($maj + ['updated_at' => now()]);
        // La fiche gardée passe cliente : ses interlocuteurs aussi (comme Client::passerClient()).
        if ($garder->estProspect() && $maj['statut_commercial'] === Client::STATUT_CLIENT) {
            DB::table('client_contacts')->where('client_id', $garder->id)->update(['statut' => Client::STATUT_CLIENT]);
        }

        // L'adresse de l'ancienne fiche, si elle n'est pas reprise sur la fiche,
        // devient un interlocuteur (le Sheet la reconnaît ainsi).
        $email = mb_strtolower(trim((string) $retirer->email));
        $emailGarde = mb_strtolower(trim((string) ($maj['email'] ?? $garder->email)));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== $emailGarde
            && !DB::table('client_contacts')->where('client_id', $garder->id)->whereNull('deleted_at')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
            $principal = DB::table('client_contacts')->where('client_id', $garder->id)->whereNull('deleted_at')->where('is_primary', true)->exists();
            ClientContact::create([
                'client_id'              => $garder->id,
                'name'                   => mb_substr(filled($retirer->contact_name) ? $retirer->contact_name : strstr($email, '@', true), 0, 120),
                'email'                  => $email,
                'phone'                  => filled($retirer->phone) ? mb_substr((string) $retirer->phone, 0, 30) : null,
                'role'                   => 'autre',
                'is_primary'             => !$principal,
                'statut'                 => $maj['statut_commercial'],
                'receives_notifications' => false,
            ]);
        }
    }

    /** @return list<array{champ: string, libelle: string, garde: ?string, retire: ?string, action: string}> */
    private function champs(Client $retirer, Client $garder): array
    {
        $champs = [];
        foreach (self::CHAMPS as $champ => $libelle) {
            [$a, $b] = [$retirer->getRawOriginal($champ), $garder->getRawOriginal($champ)];
            if ($champ === 'ncc') {
                [$a, $b] = [self::nccReel($a), self::nccReel($b) ?? $b];
            }
            if (blank($a)) {
                continue;
            }
            $vide = $champ === 'ncc' ? self::nccReel($garder->ncc) === null : blank($b);
            if ($vide) {
                $action = 'complete';
            } elseif (mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b))) {
                continue;
            } else {
                $action = 'conserve';
            }
            $champs[] = [
                'champ'   => $champ,
                'libelle' => $libelle,
                'garde'   => $this->valeur($champ, $vide ? null : $b),
                'retire'  => $this->valeur($champ, $a),
                'action'  => $action,
            ];
        }

        return $champs;
    }

    private function valeur(string $champ, $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return $champ === 'user_id' ? (User::find($v)?->name ?? '#' . $v) : (string) $v;
    }

    private function statutFinal(Client $retirer, Client $garder): string
    {
        return $retirer->estProspect() && $garder->estProspect() ? Client::STATUT_PROSPECT : Client::STATUT_CLIENT;
    }

    /** @return list<array{table: string, libelle: string, nombre: int}> lignes de la fiche, corbeille comprise */
    private function compteurs(Client $client): array
    {
        $compteurs = [];
        foreach (self::TABLES as $table => $libelle) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $q = DB::table($table)->where('client_id', $client->id);
            if ($table === 'client_contacts') {
                $q->whereNull('deleted_at'); // un doublon fusionné reste à la corbeille de la fiche gardée
            }
            if ($n = $q->count()) {
                $compteurs[] = ['table' => $table, 'libelle' => $libelle, 'nombre' => $n];
            }
        }

        return $compteurs;
    }

    /** @return list<string> références des factures émises de la fiche */
    private function facturesEmises(Client $client): array
    {
        return DB::table('invoices')->where('client_id', $client->id)
            ->whereNotIn('status', self::FACTURES_NON_EMISES)
            ->orderBy('id')->pluck('reference')->map(fn ($r) => (string) $r)->all();
    }

    /** @return list<string> tables de la base qui ont un client_id hors de la liste prévue */
    private function tablesInconnues(): array
    {
        $connues = array_merge(array_keys(self::TABLES), self::TABLES_HORS_FICHE);
        $inconnues = [];
        // Tables de la base courante seulement (le serveur MySQL peut en héberger d'autres).
        foreach (Schema::getTableListing(schemaQualified: false) as $nom) {
            if (!in_array($nom, $connues, true) && Schema::hasColumn($nom, 'client_id')) {
                $inconnues[] = $nom;
            }
        }

        return $inconnues;
    }

    /** Un NCC auto-généré par Panora (« CLT-2026-0001 ») n'est pas un vrai NCC. */
    private static function nccReel(?string $ncc): ?string
    {
        $ncc = trim((string) $ncc);

        return $ncc === '' || preg_match('/^CLT-[0-9]{4}-[0-9]+$/', $ncc) ? null : $ncc;
    }

    /** @return array{id: int, name: string, statut: string, url: string} */
    private function carte(Client $client): array
    {
        return [
            'id'     => (int) $client->id,
            'name'   => (string) $client->name,
            'statut' => Client::STATUTS[$client->statut_commercial] ?? 'Client',
            'url'    => route('admin.clients.show', $client->id),
        ];
    }

    /** Une ligne « regroupement » dans l'historique de chaque facture, devis et relance déplacé. */
    private function historiser(array $audites, Client $retirer, Client $garder): void
    {
        foreach ($audites as $modele => $ids) {
            if (!$ids) {
                continue;
            }
            foreach ($modele::query()->withoutGlobalScopes()->whereKey($ids)->get() as $doc) {
                try {
                    $doc->auditEvent = 'regroupement';
                    $doc->isCustomEvent = true;
                    $doc->auditCustomOld = ['client_id' => $retirer->id, 'client' => $retirer->name];
                    $doc->auditCustomNew = ['client_id' => $garder->id, 'client' => $garder->name];
                    Event::dispatch(new AuditCustom($doc));
                } catch (\Throwable $e) {
                    Log::warning('client.regroupe.historique', ['modele' => $modele, 'id' => $doc->id, 'erreur' => $e->getMessage()]);
                }
            }
        }
    }
}
