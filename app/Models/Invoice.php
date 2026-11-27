<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Invoice extends Model implements Auditable
{
    use HasFactory;
    /**
     * Phase 7 cahier §13 — Traçabilité & audit.
     * Tout changement sur invoices est tracé dans la table audits
     * (avant/après JSON + user + timestamps).
     */
    use AuditableTrait;

    /**
     * Champs exclus de l'audit pour éviter le bruit :
     * timestamps techniques et indicateurs dérivés re-écrits à chaque
     * recalculateAndPersist (sinon chaque versement crée 15 lignes
     * d'audit pour des deltas mécaniques).
     */
    protected $auditExclude = [
        'updated_at',
    ];

    /**
     * Métadonnées de transition de statut posées par transitionStatusTo()
     * et lues par InvoiceObserver pour alimenter invoice_status_history.
     *
     * DOIT être déclaré comme PROPRIÉTÉ PHP TYPÉE (pas un attribut
     * dynamique), sinon Eloquent essaie de la persister en BDD →
     * "Unknown column '_transitionMeta'". Ce bug s'est manifesté en
     * prod lors du premier versement qui déclenche la transition auto
     * envoyee → partiellement_payee.
     */
    public ?array $_transitionMeta = null;

    protected $fillable = [
        'reference', 'client_id', 'campaign_id',
        'commercial_user_id', 'created_by',
        'remise_pct',
        'amount', 'net_ht',
        'tva', 'tva_amount', 'tsp_amount',
        // Override TSP par facture (ajout 2026-08-03) — cf. migration
        // add_tax_overrides_to_invoices. NULL = défaut config, 0 = TSP off.
        'tsp_rate_override',
        'tm_total', 'odp_total',
        'services_impression', 'services_pose_depose',
        'amount_ttc', 'total_a_payer',
        'issued_at', 'paid_at', 'status',
        'locked_at', 'locked_by_id',
        'credit_note_for_id', 'campaign_year',
        'notes_client',
        // Mission C — litige
        'litige_motif', 'litige_note', 'litige_opened_at',
    ];

    /**
     * Casts FNE — RÈGLE D'OR #4 : tous les MONTANTS en entiers FCFA
     * (le franc CFA n'a pas de centimes). Seuls les pourcentages
     * (remise_pct, tva) et les surfaces/durées (gérées sur InvoiceLine)
     * restent décimaux.
     */
    protected $casts = [
        'remise_pct'           => 'decimal:2',
        'amount'               => 'integer',
        'net_ht'               => 'integer',
        'tva'                  => 'decimal:2',
        'tva_amount'           => 'integer',
        'tsp_amount'           => 'integer',
        'tsp_rate_override'    => 'decimal:2',
        'tm_total'             => 'integer',
        'odp_total'            => 'integer',
        'services_impression'  => 'integer',
        'services_pose_depose' => 'integer',
        'amount_ttc'           => 'integer',
        'total_a_payer'        => 'integer',
        'issued_at'            => 'date',
        'paid_at'              => 'date',
        'locked_at'            => 'datetime',
        'campaign_year'        => 'integer',
        'litige_opened_at'     => 'datetime',
    ];

    /**
     * Liste des statuts conformes au §3 du cahier des charges.
     * Source unique : les transitions et l'observer s'y réfèrent.
     */
    public const STATUSES = [
        'brouillon', 'generee', 'validee', 'envoyee',
        'partiellement_payee', 'payee', 'en_retard', 'litige', 'annulee',
    ];

    /**
     * Libellés FR pour l'affichage utilisateur.
     *
     * Le terme "payée" est volontairement remplacé par "Soldée" côté UI
     * (alignement vocabulaire métier CIBLE CI). La valeur DB reste 'payee'
     * pour ne pas casser l'historique et tous les jobs/observers — c'est
     * un mapping COSMÉTIQUE uniquement.
     *
     * Source unique de vérité pour tout affichage de statut : badges,
     * pipelines, exports, emails, PDF.
     */
    public const STATUS_LABELS = [
        'brouillon'           => 'Brouillon',
        'generee'             => 'Générée',
        'validee'             => 'Validée',
        'envoyee'             => 'Envoyée',
        'partiellement_payee' => 'Partiellement soldée',
        'payee'               => 'Soldée',
        'en_retard'           => 'En retard',
        'litige'              => 'Litige',
        'annulee'             => 'Annulée',
    ];

    /**
     * Libellés du statut de PAIEMENT dérivé (paymentStatus), 2026-11-26 —
     * source unique des exports (avant : codes bruts « non_payee » affichés).
     */
    public const PAYMENT_STATUS_LABELS = [
        'soldee'    => 'Soldée',
        'partielle' => 'Paiement partiel',
        'non_payee' => 'Non soldée',
        'en_retard' => 'En retard',
        'annulee'   => 'Annulée',
    ];

    public static function paymentStatusLabel(?string $status): string
    {
        return self::PAYMENT_STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    // ═══════════════════════════════════════════════════════════════
    // NUMÉROTATION (2026-11-26, point 21 validé) — SOURCE UNIQUE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Prochain numéro de facture de l'année : plus grand numéro déjà
     * attribué + 1 (format config billing.reference_format, FAC-2026-001).
     * Un numéro n'est jamais réattribué, même après une suppression.
     *
     * Avant, quatre endroits comptaient les factures de l'année : deux
     * créations rapprochées, ou une facture supprimée, donnaient un doublon.
     * Les avoirs (credit_note_format, AV-…) ont leur propre suite.
     */
    public static function prochaineReference(int $annee): string
    {
        $format  = (string) config('billing.reference_format', 'FAC-%s-%03d');
        // Partie fixe avant le compteur : « FAC-2026- ».
        $prefixe = sprintf(preg_split('/%0?\d*d/', $format, 2)[0], $annee);

        $dernier = static::query()
            ->where('reference', 'like', $prefixe . '%')
            ->pluck('reference')
            ->map(fn ($r) => (int) preg_replace('/\D.*$/s', '', substr((string) $r, strlen($prefixe))))
            ->max() ?? 0;

        return sprintf($format, $annee, $dernier + 1);
    }

    /**
     * Crée une facture sous verrou : le numéro est calculé puis la facture
     * enregistrée sans qu'une autre création s'intercale (les tables sont en
     * MyISAM : pas de verrou de ligne, d'où un verrou applicatif).
     * $creer reçoit le numéro et crée la facture ; son retour est renvoyé.
     *
     * @template T
     * @param  \Closure(string): T  $creer
     * @return T
     */
    public static function creerAvecNumero(int $annee, \Closure $creer): mixed
    {
        return \Illuminate\Support\Facades\Cache::lock('factures:numerotation', 60)
            ->block(20, fn () => $creer(self::prochaineReference($annee)));
    }

    /** Helper statique — utilisable dans toutes les vues / services. */
    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst((string) $status);
    }

    /** Accessor : {{ $invoice->status_label }} dans les Blades. */
    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }

    /**
     * Motifs prédéfinis de mise en litige (mission C — capture user).
     *
     * Clé = code stocké en base (compact, snake_case).
     * Valeur = libellé affiché à l'utilisateur (Soldée, motif…).
     *
     * Quand un admin met une facture en litige, il choisit l'un de ces
     * motifs + saisit une note libre. Le motif sert ensuite de pivot
     * pour le filtre dashboard contentieux / recouvrement.
     */
    public const LITIGE_MOTIFS = [
        'echeancier_non_respecte'    => 'Échéancier non respecté',
        'retard_paiement'            => 'Retard de paiement',
        'recouvrement_amiable'       => 'Recouvrement amiable ouvert',
        'creance_echue_non_regul'    => 'Créance échue non régularisée',
        'procedures_juridiques'      => 'Procédures juridiques engagées',
        'anomalie_reglement'         => 'Anomalie de règlement',
        'risque_impaye'              => 'Risque d\'impayé identifié',
        'creance_douteuse'           => 'Créance douteuse',
        'negociation_echeancier'     => 'Négociation d\'un échéancier',
        'suivi_impaye'               => 'Suivi impayé',
        'creance_litigieuse'         => 'Créance litigieuse',
        'contentieux_client'         => 'Contentieux client',
        'relance_paiement'           => 'Relance paiement',
    ];

    public static function litigeMotifLabel(?string $motif): string
    {
        return self::LITIGE_MOTIFS[$motif] ?? '—';
    }

    /** Transitions manuelles autorisées (user action). */
    // 2026-11-26 (point 20) — alignée sur les boutons de la fiche facture.
    // Avant, « 🔒 Valider et figer » depuis un brouillon était refusé
    // (brouillon → validee absent), et la table annonçait des passages sans
    // bouton (partielle / en retard → litige, litige → payée).
    public const MANUAL_TRANSITIONS = [
        'brouillon'           => ['generee', 'validee', 'envoyee', 'payee', 'annulee'],
        'generee'             => ['brouillon', 'validee', 'annulee'],
        'validee'             => ['envoyee', 'litige', 'annulee', 'brouillon'],
        'envoyee'             => ['payee', 'litige', 'annulee', 'brouillon'],
        'partiellement_payee' => ['annulee', 'brouillon'],
        'payee'               => ['brouillon'], // rebascule possible si erreur
        'en_retard'           => ['annulee', 'brouillon'],
        'litige'              => ['envoyee', 'annulee', 'brouillon'],
        'annulee'             => ['brouillon'],
    ];

    // Identité du client figée à l'émission (2026-10-30) — règle : IdentiteClientFacture.
    protected static function booted(): void
    {
        static::saving(fn (self $facture) => \App\Support\IdentiteClientFacture::appliquer($facture));
    }

    /**
     * Nom, NCC, RCCM, régime, adresse à imprimer sur la facture : figés dès
     * l'émission, la fiche actuelle tant que c'est un brouillon.
     *
     * @return array{nom: ?string, ncc: ?string, rccm: ?string, regime_imposition: ?string, adresse: ?string, fige: bool, nom_actuel: ?string}
     */
    public function identiteClient(): array
    {
        return \App\Support\IdentiteClientFacture::lire($this);
    }

    public function client()
    {
        // withTrashed() : on garde la relation lisible même si le client a été
        // soft-deleted, pour que les vues facture (show, listing, PDF) ne
        // plantent pas — la facture reste un document fiscal valide.
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Adresses où envoyer la facture et ses relances : l'adresse de la fiche
     * client et l'interlocuteur de la campagne facturée
     * (Client::destinatairesDocuments, 2026-10-29). Vide si ni l'un ni l'autre.
     *
     * @return list<string>
     */
    public function adressesClient(): array
    {
        return $this->client ? array_column($this->client->destinatairesDocuments($this->campaign?->interlocuteur), 'email') : [];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Commercial responsable du compte client — récupéré automatiquement
     * depuis la campagne au moment de la génération (§1 cahier). Distinct
     * de created_by qui est l'utilisateur ayant cliqué "Créer la facture"
     * (potentiellement un admin différent du commercial titulaire).
     */
    public function commercialUser()
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    /** Historique des transitions de statut — §3 et §13 du cahier. */
    public function statusHistory()
    {
        return $this->hasMany(InvoiceStatusHistory::class)->orderBy('created_at');
    }

    public function isPaid(): bool
    {
        return $this->status === 'payee'
            || $this->paymentStatus() === 'soldee';
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ═══════════════════════════════════════════════════════════════
    // RELATIONS FNE
    // ═══════════════════════════════════════════════════════════════

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('order_index');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_at');
    }

    public function schedules()
    {
        return $this->hasMany(InvoiceSchedule::class)->orderBy('due_date');
    }

    /**
     * Totaux tels qu'ils s'affichent (2026-11-26) — SOURCE UNIQUE de la page
     * de la facture, du PDF FNE, du mail au client et de la page publique.
     *
     * Modèle DGI (celui du PDF) : la TVA et le TOTAL TTC comprennent les
     * services annexes ; l'ODP, la TM et la TSP forment les « autres taxes ».
     * Tout part des montants enregistrés par InvoiceCalculator. Le TTC des
     * services n'étant pas stocké, il est déduit du total à payer : les
     * lignes affichées retombent ainsi toujours sur ce total, au franc près.
     *
     * Avant, la page recalculait les services sans la quantité et appliquait
     * la TVA à tous (y compris « HT strict »), et le mail annonçait le TTC
     * des panneaux à la place du total à payer.
     *
     * @return array<string, int|float>
     */
    /**
     * Communes des lignes sans tarif TM à la date d'émission (2026-11-26) :
     * la base nationale (1 000 F/m²/mois) leur a été appliquée. Sert à
     * l'avertissement « tarif TM à renseigner » de la facture et du formulaire.
     *
     * @param  iterable<array{commune_id?: mixed, panel_id?: mixed}>|null  $lignes  saisie du formulaire, sinon les lignes enregistrées
     * @return list<string>
     */
    public static function communesSansTarifTm(iterable $lignes, \DateTimeInterface|string|null $emission): array
    {
        $date = $emission ?: now();
        $noms = [];
        foreach ($lignes as $l) {
            $l = is_array($l) ? $l : $l->toArray();
            if (empty($l['commune_id'])) continue;
            $commune = Commune::find($l['commune_id']);
            if (!$commune) continue;
            $eclaire = !empty($l['panel_id']) && (bool) Panel::whereKey((int) $l['panel_id'])->value('is_lit');
            if ((float) ($commune->ratesAt($date, $eclaire)['tm'] ?? 0) <= 0) {
                $noms[$commune->id] = $commune->name;
            }
        }

        return array_values($noms);
    }

    public function totauxAffiches(): array
    {
        $services = $this->relationLoaded('services') ? $this->services : $this->services()->get();
        if ($services->isNotEmpty()) {
            $htAvecTva = (int) $services->filter(fn ($s) => (bool) $s->tva_applicable)->sum(fn ($s) => $s->montantHt());
            $htSansTva = (int) $services->reject(fn ($s) => (bool) $s->tva_applicable)->sum(fn ($s) => $s->montantHt());
        } else {
            // Factures d'avant les services libres : deux champs, soumis à TVA.
            $htAvecTva = (int) $this->services_impression + (int) $this->services_pose_depose;
            $htSansTva = 0;
        }
        $servicesHt = $htAvecTva + $htSansTva;
        $tauxTva    = (float) $this->tva;

        $ttcPanneaux = (int) $this->amount_ttc;
        $autres      = (int) $this->tsp_amount + (int) $this->tm_total + (int) $this->odp_total;
        $total       = (int) $this->total_a_payer;

        $servicesTtc = $total > 0
            ? max(0, $total - $ttcPanneaux - $autres)
            : (int) round($htAvecTva * (1 + $tauxTva / 100)) + $htSansTva;
        if ($total <= 0) {
            $total = $ttcPanneaux + $autres + $servicesTtc;
        }

        $htBrut = (int) $this->amount;
        $netHt  = $this->htApresRemise();

        return [
            'ht_brut'              => $htBrut,
            'remise_pct'           => (float) $this->remise_pct,
            'remise'               => max(0, $htBrut - $netHt),
            'net_ht'               => $netHt,
            'services_ht'          => $servicesHt,
            'services_ht_avec_tva' => $htAvecTva,
            'services_ht_sans_tva' => $htSansTva,
            'services_ttc'         => $servicesTtc,
            'taux_tva'             => $tauxTva,
            'tva_panneaux'         => (int) $this->tva_amount,
            'tva_services'         => max(0, $servicesTtc - $servicesHt),
            'tva'                  => (int) $this->tva_amount + max(0, $servicesTtc - $servicesHt),
            'ttc'                  => $ttcPanneaux + $servicesTtc,
            'tsp'                  => (int) $this->tsp_amount,
            'tm'                   => (int) $this->tm_total,
            'odp'                  => (int) $this->odp_total,
            'autres_taxes'         => $autres,
            'total_a_payer'        => $total,
        ];
    }

    /**
     * Services annexes libres (libellé + prix HT, TVA 18%).
     * Source unique depuis prompt v2 — remplace les 2 anciens
     * champs invoices.services_impression / services_pose_depose
     * (qui restent en base pour les factures historiques mais ne
     * sont plus lus par le calculator).
     */
    public function services()
    {
        return $this->hasMany(InvoiceService::class)->orderBy('order_index');
    }

    /**
     * Historique des relances effectuées sur cette facture. Ordonnées de
     * la plus RÉCENTE à la plus ancienne pour que la première ligne soit
     * directement utilisable dans le listing facturation (colonne RELANCES).
     */
    public function relances()
    {
        return $this->hasMany(Relance::class)->orderByDesc('relance_date')->orderByDesc('id');
    }

    /**
     * Prochaine échéance non payée. Null si l'échéancier est vide ou
     * entièrement réglé. Sert au tableau de suivi (colonne "Prochaine
     * échéance" + badge "🔴 À relancer" si overdue).
     */
    public function nextDueSchedule(): ?InvoiceSchedule
    {
        $rel = $this->relationLoaded('schedules')
            ? $this->schedules
            : $this->schedules()->get();

        return $rel
            ->whereNull('paid_at')
            ->sortBy('due_date')
            ->first();
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    /** Si cette facture est un avoir, pointe sur la facture source. */
    public function creditNoteFor()
    {
        return $this->belongsTo(Invoice::class, 'credit_note_for_id');
    }

    /** Avoirs émis sur cette facture (relation inverse). */
    public function creditNotes()
    {
        return $this->hasMany(Invoice::class, 'credit_note_for_id');
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS PAIEMENT
    // ═══════════════════════════════════════════════════════════════

    /**
     * Montant total à payer — SOURCE UNIQUE (2026-10-29).
     *
     * `total_a_payer` est la colonne de référence, mais elle est
     * NOT NULL DEFAULT 0 : une facture créée sans passer par
     * InvoiceCalculator y porte 0 alors que `amount_ttc` est renseigné.
     * D'où le repli, qui était recopié à quatre endroits dans ce modèle,
     * dans PaymentService, ScheduleGenerator et une dizaine de vues.
     *
     * Pour les sommes SQL, utiliser self::SQL_MONTANT_TOTAL : une
     * requête `sum('total_a_payer')` compterait ces factures pour zéro.
     */
    public function montantTotal(): float
    {
        return (float) ($this->total_a_payer ?: $this->amount_ttc ?: 0);
    }

    /**
     * Même règle que montantTotal(), en SQL — pour les agrégats.
     *
     *   Invoice::query()->selectRaw('SUM(' . Invoice::SQL_MONTANT_TOTAL . ') AS total')
     *
     * COALESCE seul ne suffirait pas : il ne couvre que NULL, pas le 0
     * que porte la colonne par défaut.
     */
    public const SQL_MONTANT_TOTAL =
        'CASE WHEN invoices.total_a_payer > 0 THEN invoices.total_a_payer ELSE COALESCE(invoices.amount_ttc, 0) END';

    /** Somme SQL du montant total à payer, repli compris. */
    public static function sommeMontantTotal($query): float
    {
        return (float) ($query->clone()->selectRaw('SUM(' . self::SQL_MONTANT_TOTAL . ') AS t')->value('t') ?? 0);
    }

    /**
     * CA FACTURÉ — SOURCE UNIQUE (règle validée par la direction le 2026-10-30).
     *
     * Une facture compte dans le chiffre d'affaires facturé et dans le dû dès
     * qu'elle sort du brouillon : générée, validée, envoyée, partiellement
     * soldée, soldée, en retard, en litige. Ne comptent jamais : le brouillon
     * (un projet), l'annulée, l'avoir. L'envoi au client n'est pas requis.
     * Le DÛ, c'est ce même ensemble moins les versements (remainingAmount()).
     * L'ENCAISSÉ, ce sont les versements (invoice_payments).
     *
     *   Invoice::facturees()->…                          (Eloquent)
     *   Invoice::restreindreAuFacture(DB::table('invoices'), 'invoices')
     */
    public const STATUTS_HORS_CA = ['brouillon', 'annulee'];

    /** Applique la règle du CA facturé à n'importe quelle requête (Eloquent ou DB::table). */
    public static function restreindreAuFacture($query, string $table = 'invoices')
    {
        $p = $table !== '' ? $table . '.' : '';

        return $query->whereNotIn($p . 'status', self::STATUTS_HORS_CA)->whereNull($p . 'credit_note_for_id');
    }

    public function scopeFacturees($query)
    {
        return self::restreindreAuFacture($query, $this->getTable());
    }

    /** Même règle, sur une facture déjà chargée (listes, PDF). */
    public function compteAuCa(): bool
    {
        return !in_array($this->status, self::STATUTS_HORS_CA, true) && $this->credit_note_for_id === null;
    }

    /**
     * DÉJÀ FACTURÉ d'une campagne (reste à facturer) : toute facture sauf
     * l'annulée — brouillons compris, pour ne pas facturer deux fois la
     * même campagne (2026-10-30).
     */
    public function scopeNonAnnulees($query)
    {
        return $query->where($this->getTable() . '.status', '!=', 'annulee');
    }

    /**
     * HT des panneaux APRÈS remise — base du « déjà facturé » d'une campagne
     * (règle A validée le 27/11 : le montant d'une campagne issue d'un devis
     * est lui-même après la remise du devis). Repli sur le HT avant remise
     * quand net_ht n'est pas renseigné (factures anciennes), comme
     * totauxAffiches(). Services exclus, comme dans le montant de la campagne.
     */
    public function htApresRemise(): int
    {
        return (int) ($this->net_ht ?: $this->amount);
    }

    /** Même règle que htApresRemise(), en SQL — pour les sommes. */
    public const SQL_HT_APRES_REMISE = 'COALESCE(NULLIF(invoices.net_ht, 0), invoices.amount, 0)';

    /**
     * Couleurs et icône de chaque statut (2026-11-27) — reprises de la page
     * de modification, la seule à couvrir les 9 statuts. Libellé :
     * statusLabel().
     */
    public const COULEURS_STATUT = [
        'brouillon'           => ['bg' => 'rgba(107,114,128,.10)', 'color' => '#4b5563', 'icon' => '📝'],
        'generee'             => ['bg' => 'rgba(232,160,32,.10)',  'color' => '#a16207', 'icon' => '📋'],
        'validee'             => ['bg' => 'rgba(99,102,241,.10)',  'color' => '#4338ca', 'icon' => '🔒'],
        'envoyee'             => ['bg' => 'rgba(59,130,246,.10)',  'color' => '#1d4ed8', 'icon' => '📤'],
        'partiellement_payee' => ['bg' => 'rgba(245,158,11,.10)',  'color' => '#b45309', 'icon' => '⏳'],
        'payee'               => ['bg' => 'rgba(34,197,94,.10)',   'color' => '#15803d', 'icon' => '✅'],
        'en_retard'           => ['bg' => 'rgba(239,68,68,.10)',   'color' => '#b91c1c', 'icon' => '⚠️'],
        'litige'              => ['bg' => 'rgba(244,63,94,.10)',   'color' => '#9f1239', 'icon' => '⚖️'],
        'annulee'             => ['bg' => 'rgba(107,114,128,.10)', 'color' => '#374151', 'icon' => '🚫'],
    ];

    /**
     * Somme des versements enregistrés sur cette facture.
     * Source : invoice_payments. Si la facture est historique (avant
     * la refonte) et n'a pas encore de InvoicePayment mais a un paid_at
     * legacy non null + status='payee', on remonte total_a_payer pour
     * que paidAmount() reflète l'état réel sans perdre l'historique.
     */
    public function paidAmount(): float
    {
        $sum = (float) ($this->relationLoaded('payments')
            ? $this->payments->sum('montant')
            : $this->payments()->sum('montant'));

        if ($sum <= 0 && $this->status === 'payee' && $this->paid_at) {
            // Facture historique sans InvoicePayment — on considère
            // le total_a_payer (ou amount_ttc en fallback) comme payé.
            return $this->montantTotal();
        }
        return $sum;
    }

    /** Reste à payer = total_a_payer − versements. Borné à 0. */
    public function remainingAmount(): float
    {
        $total = $this->montantTotal();
        return max(0.0, round($total - $this->paidAmount(), 2));
    }

    /**
     * Pourcentage payé — borné à [0, 100]. Utile pour les barres
     * de progression et les KPI recouvrement.
     */
    public function paidPercentage(): float
    {
        $total = $this->montantTotal();
        if ($total <= 0) return 0.0;
        return round(min(100.0, ($this->paidAmount() / $total) * 100), 1);
    }

    /**
     * Statuts où le montant est réclamable au client (2026-11-26) : la
     * facture est partie, n'est ni soldée, ni annulée, ni en litige.
     * Seules ces factures peuvent être « en retard » et être relancées.
     */
    public const STATUTS_RECLAMABLES = ['envoyee', 'partiellement_payee', 'en_retard'];

    /**
     * Échéance de la facture (2026-11-26) — SOURCE UNIQUE, règle validée
     * le 26/11 : la plus ancienne échéance non soldée de l'échéancier ; sans
     * échéancier, la date d'émission + le délai de paiement (30 jours, cf.
     * config billing.delai_paiement_jours). Null : échéancier entièrement soldé,
     * ou facture sans date.
     *
     * Avant, quatre définitions coexistaient (statut, échéancier seul, date
     * d'émission seule…) : une facture sans échéancier n'était jamais en retard.
     */
    public function echeance(): ?\Carbon\Carbon
    {
        $schedules = $this->relationLoaded('schedules') ? $this->schedules : $this->schedules()->get();
        if ($schedules->isNotEmpty()) {
            $prochaine = $schedules->whereNull('paid_at')->sortBy('due_date')->first();

            return $prochaine?->due_date?->copy()->startOfDay();
        }

        $base = $this->issued_at ?? $this->created_at;

        return $base
            ? \Carbon\Carbon::parse($base)->startOfDay()->addDays((int) config('billing.delai_paiement_jours', 30))
            : null;
    }

    /**
     * En retard (2026-11-26, définition unique) : facture réclamable dont
     * l'échéance est dépassée et dont il reste quelque chose à payer.
     */
    public function isOverdue(): bool
    {
        if (!in_array($this->status, self::STATUTS_RECLAMABLES, true)) return false;
        if ($this->remainingAmount() <= 0.01) return false;

        $echeance = $this->echeance();

        return $echeance !== null && $echeance->lt(now()->startOfDay());
    }

    /**
     * Statut de paiement DÉRIVÉ — distinct du status (brouillon/envoyee/
     * annulee). Toujours recalculé à la demande. Conforme prompt v2 § 4.3.
     *
     *   'annulee'   : facture annulée (court-circuit)
     *   'soldee'    : versements ≥ total
     *   'en_retard' : échéance dépassée + reste à payer (avant partielle/non_payee)
     *   'partielle' : versements > 0 et < total
     *   'non_payee' : aucun versement
     */
    public function paymentStatus(): string
    {
        if ($this->status === 'annulee') return 'annulee';
        $paid  = $this->paidAmount();
        $total = $this->montantTotal();
        if ($total > 0 && $paid + 0.01 >= $total) return 'soldee';
        if ($this->isOverdue()) return 'en_retard';
        if ($paid <= 0) return 'non_payee';
        return 'partielle';
    }

    // ═══════════════════════════════════════════════════════════════
    // VERROUILLAGE
    // ═══════════════════════════════════════════════════════════════

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Transition de statut tracée — utilisé par les controllers (actions
     * utilisateur) ET les services automatiques (PaymentService,
     * Job::markOverdue). Refuse les transitions non autorisées par
     * MANUAL_TRANSITIONS quand $auto=false.
     *
     * Lève DomainException si transition interdite côté manuel. Les
     * transitions auto contournent (un paiement complet peut faire passer
     * brouillon → payee même si pas dans la table manuelle).
     */
    public function transitionStatusTo(string $newStatus, ?string $reason = null, bool $auto = true, ?int $userId = null): void
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \DomainException("Statut inconnu : '$newStatus'.");
        }

        if ($this->status === $newStatus) return; // no-op

        if (!$auto) {
            $allowed = self::MANUAL_TRANSITIONS[$this->status] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                throw new \DomainException(
                    "Transition interdite : '{$this->status}' → '$newStatus'. "
                    . "Transitions autorisées depuis '{$this->status}' : "
                    . (empty($allowed) ? '(aucune)' : implode(', ', $allowed))
                );
            }
        }

        // Métadonnées lues par l'observer
        $this->_transitionMeta = [
            'reason'  => $reason,
            'auto'    => $auto,
            'user_id' => $userId ?? auth()->id(),
        ];

        $previousStatus = $this->status;
        $this->status   = $newStatus;

        // Mission C — nettoyage du contexte litige quand la facture
        // sort du statut litige (résolution / annulation / retour
        // brouillon…). On garde l'historique dans InvoiceStatusHistory.
        if ($previousStatus === 'litige' && $newStatus !== 'litige') {
            $this->litige_motif     = null;
            $this->litige_note      = null;
            $this->litige_opened_at = null;
        }

        $this->save();

        // ═══ Phase 8A cahier §3 — Verrouillage AUTO à la validation ═══
        // « validée (verrouille la facture : locked_at/lockedBy, fige les
        //   taux sur les lignes) »
        // Le snapshot des taux est déjà fait au moment de syncLines() via
        // Commune::ratesAt(issued_at). Ici on pose le verrou pour interdire
        // toute modification ultérieure des lignes/services.
        if ($newStatus === 'validee' && !$this->isLocked()) {
            $this->lock($userId ?? auth()->id());
        }

        // Symétrie : si on rebascule de validee/envoyee/payee/en_retard
        // /partiellement_payee vers brouillon (correction admin),
        // on retire automatiquement le verrou pour permettre la modif.
        if ($newStatus === 'brouillon' && $this->isLocked()) {
            $this->unlock();
        }
    }

    /** Verrouille la facture (passage à 'envoyee'). Idempotent. */
    public function lock(?int $userId = null): void
    {
        if ($this->isLocked()) return;
        $this->forceFill([
            'locked_at'    => now(),
            'locked_by_id' => $userId ?? auth()->id(),
        ])->save();
    }

    public function unlock(): void
    {
        if (!$this->isLocked()) return;
        $this->forceFill([
            'locked_at'    => null,
            'locked_by_id' => null,
        ])->save();
    }

    /** True si cette facture est un AVOIR (note de crédit). */
    public function isCreditNote(): bool
    {
        return $this->credit_note_for_id !== null;
    }

    /**
     * RBAC commercial : restreint aux factures liées à des campagnes
     * du commercial donné. Délègue au scope canonique
     * Campaign::scopeForCommercialUser (source unique de vérité).
     *
     * Cas couverts (via la campagne liée) :
     *   1) campaign.commercial_user_id == uid
     *   2) reservation.commercial_user_id == uid
     *   3) reservation.user_id == uid (créateur, résa sans commercial)
     *   4) campaign.user_id == uid (campagne directe sans résa)
     *
     * Factures sans campagne (rarissime, ne devrait pas exister) : on
     * tombe sur invoice.created_by == uid pour ne pas être trop strict.
     */
    public function scopeForCommercialUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereHas('campaign', fn($c) => $c->forCommercialUser($userId))
              ->orWhere(function ($qq) use ($userId) {
                  $qq->whereDoesntHave('campaign')
                     ->where('created_by', $userId);
              });
        });
    }

    /**
     * Test rapide d'appartenance (sert aux policies) :
     * cette facture appartient-elle au périmètre du commercial ?
     */
    public function belongsToCommercialUser(int $userId): bool
    {
        // Cas facture sans campagne : seul le créateur la voit
        if (!$this->campaign_id) {
            return (int) $this->created_by === $userId;
        }
        $c = $this->relationLoaded('campaign') ? $this->campaign : null;
        if (!$c) $c = Campaign::find($this->campaign_id);
        return $c?->belongsToCommercialUser($userId) ?? false;
    }
}
