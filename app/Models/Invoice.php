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
    public const MANUAL_TRANSITIONS = [
        'brouillon'           => ['generee', 'annulee'],
        'generee'             => ['brouillon', 'validee', 'annulee'],
        'validee'             => ['envoyee', 'litige', 'annulee', 'brouillon'],
        'envoyee'             => ['litige', 'annulee', 'brouillon'],
        'partiellement_payee' => ['litige', 'brouillon'],
        'payee'               => ['brouillon'], // rebascule possible si erreur
        'en_retard'           => ['litige', 'brouillon'],
        'litige'              => ['envoyee', 'partiellement_payee', 'payee', 'annulee'],
        'annulee'             => ['brouillon'],
    ];

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
            return (float) ($this->total_a_payer ?: $this->amount_ttc ?: 0);
        }
        return $sum;
    }

    /** Reste à payer = total_a_payer − versements. Borné à 0. */
    public function remainingAmount(): float
    {
        $total = (float) ($this->total_a_payer ?: $this->amount_ttc ?: 0);
        return max(0.0, round($total - $this->paidAmount(), 2));
    }

    /**
     * Pourcentage payé — borné à [0, 100]. Utile pour les barres
     * de progression et les KPI recouvrement.
     */
    public function paidPercentage(): float
    {
        $total = (float) ($this->total_a_payer ?: $this->amount_ttc ?: 0);
        if ($total <= 0) return 0.0;
        return round(min(100.0, ($this->paidAmount() / $total) * 100), 1);
    }

    /**
     * Facture en retard : au moins une échéance prévisionnelle
     * dépassée non payée ET la facture n'est ni soldée ni annulée.
     *
     * Si pas d'échéancier configuré, on ne se base PAS sur issued_at
     * (le délai de paiement standard est dans notes_client, pas une
     * date stricte) — l'admin doit configurer un échéancier pour
     * activer le suivi recouvrement.
     */
    public function isOverdue(): bool
    {
        if (in_array($this->status, ['annulee', 'brouillon'])) return false;
        if ($this->remainingAmount() <= 0.01) return false;

        $schedules = $this->relationLoaded('schedules')
            ? $this->schedules
            : $this->schedules()->get();

        return $schedules
            ->whereNull('paid_at')
            ->contains(fn($s) =>
                $s->due_date && $s->due_date->startOfDay()->lt(now()->startOfDay())
            );
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
        $total = (float) ($this->total_a_payer ?: $this->amount_ttc ?: 0);
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
