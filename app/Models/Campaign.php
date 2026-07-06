<?php
namespace App\Models;

use App\Enums\CampaignStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'client_id', 'reservation_id',
        'user_id', 'commercial_user_id', 'updated_by',
        'start_date', 'end_date', 'status',
        'total_panels', 'total_amount', 'notes',
        'cancellation_reason', 'cancellation_notes',
        'pige_token', 'pige_token_created_at',
        'total_amount_overridden_at', 'total_amount_overridden_by_id',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_amount' => 'integer', // RÈGLE 4 — entier FCFA (audit Phase 8E)
        'total_panels' => 'integer',
        'status'       => CampaignStatus::class,
        'pige_token_created_at' => 'datetime',
        'total_amount_overridden_at' => 'datetime',
    ];

    public function totalAmountOverriddenBy()
    {
        return $this->belongsTo(User::class, 'total_amount_overridden_by_id');
    }

    public function isTotalAmountOverridden(): bool
    {
        return $this->total_amount_overridden_at !== null;
    }

    /** Cache mémoire pour les helpers de progression (évite les recalculs répétés en Blade) */
    protected array $progressCache = [];

    // ── Relations ─────────────────────────────────────────────────
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Commercial assigné — responsable du suivi relation client + emails.
     * Si null → fallback sur user (créateur) via resolveCommercialContact().
     */
    public function commercial()
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    /**
     * Résout le commercial à contacter (priorité commercial assigné > créateur).
     * Aligne le pattern Reservation::resolveCommercialContact().
     */
    public function resolveCommercialContact(): ?User
    {
        try {
            $c = $this->commercial;
            if ($c) return $c;
        } catch (\Throwable) {
            // colonne pas encore migrée
        }
        return $this->user;
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /** Relation pose_tasks (HasMany) pour les helpers SLA. */
    public function poseTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\PoseTask::class);
    }

    // ─── M3 SLA enrichi — helpers bandeau "Retard détecté" ──────────────

    /**
     * Cas (a) Précision A : signalements problem_reported NON RÉSOLUS
     * (resolved_at IS NULL ET maintenance_id IS NULL — Précision B).
     * Mémoïsé pour éviter N×N requêtes sur une même page.
     */
    public function openProblemSignals(): \Illuminate\Support\Collection
    {
        if ($this->relationLoaded('_open_signals')) {
            return $this->_open_signals;
        }
        $signals = \App\Models\PoseTaskAction::query()
            ->where('action', \App\Models\PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->whereNull('resolved_at')
            ->whereNull('maintenance_id')
            ->whereHas('task', fn ($q) => $q->where('campaign_id', $this->id))
            ->with(['task.panel:id,reference'])
            ->get();
        $this->_open_signals = $signals;
        return $signals;
    }

    /**
     * Cas (b) Précision A : poses 'planifiee' avec scheduled_at < now()
     * SANS signalement actif sur cette même pose (déjà signalé = couvert
     * par cas a, on ne double-affiche pas).
     */
    public function overduePoseTasks(): \Illuminate\Support\Collection
    {
        if ($this->relationLoaded('_overdue_poses')) {
            return $this->_overdue_poses;
        }
        $openSignalPoseIds = $this->openProblemSignals()->pluck('pose_task_id')->unique()->all();
        $overdue = $this->poseTasks()
            ->where('status', 'planifiee')
            ->where('scheduled_at', '<', \App\Models\PoseTask::lateThreshold())
            ->when(!empty($openSignalPoseIds), fn ($q) => $q->whereNotIn('id', $openSignalPoseIds))
            ->with('panel:id,reference')
            ->get();
        $this->_overdue_poses = $overdue;
        return $overdue;
    }

    /**
     * Motif majoritaire des signalements ouverts (cas a).
     * Délègue à PoseTaskAction::effectiveMotif() pour respecter les amendements.
     */
    public function dominantMotif(): ?\App\Enums\DelayReason
    {
        $signals = $this->openProblemSignals();
        if ($signals->isEmpty()) return null;
        $bucket = [];
        foreach ($signals as $sig) {
            $motif = $sig->effectiveMotif();
            if (!$motif) continue;
            $bucket[$motif->value] = ($bucket[$motif->value] ?? 0) + 1;
        }
        if (empty($bucket)) return null;
        arsort($bucket);
        return \App\Enums\DelayReason::from(array_key_first($bucket));
    }

    public function panels()
    {
        // wherePivot('type', 'interne') : protège les lignes externes de
        // campaign_panels (type='externe', external_panel_id IS NOT NULL)
        // contre les sync()/detach() effectués via cette relation. Les
        // externes sont gérés séparément (insert manuel + lecture dédiée).
        return $this->belongsToMany(Panel::class, 'campaign_panels')
                    ->wherePivot('type', 'interne')
                    ->withTimestamps();
    }

    /**
     * Panneaux externes liés à la campagne via campaign_panels (type='externe').
     * Lecture uniquement — l'écriture passe par INSERT direct dans le contrôleur
     * de réservation pour préserver la simplicité du flux.
     */
    public function externalPanels()
    {
        return $this->belongsToMany(\App\Models\ExternalPanel::class, 'campaign_panels', 'campaign_id', 'external_panel_id')
                    ->wherePivot('type', 'externe')
                    ->withTimestamps();
    }

    public function piges()
    {
        return $this->hasMany(Pige::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function satisfactionSurvey()
    {
        return $this->hasOne(SatisfactionSurvey::class);
    }

    // ── Scopes ────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::ACTIF->value);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', CampaignStatus::TERMINE->value);
    }

    /** Campagnes "en cours" — actif (pose terrain trackée via PoseTask). */
    public function scopeRunning($query)
    {
        return $query->where('status', CampaignStatus::ACTIF->value);
    }

    public function scopeNonFacturees($query)
    {
        return $query->whereIn('status', ['actif', 'termine'])
                     ->doesntHave('invoices');
    }

    /**
     * RBAC commercial : restreint aux campagnes du commercial donné.
     * Couvre 4 cas (source unique pour controllers + policies + KPI service) :
     *   1) campaign.commercial_user_id == uid (assignation directe)
     *   2) reservation.commercial_user_id == uid (résa parente)
     *   3) reservation.user_id == uid (créateur fallback résa sans commercial)
     *   4) campaign.user_id == uid (campagne directe sans résa)
     *
     * Avant cette refactorisation, le scope était dupliqué dans 4 endroits
     * (DashboardController, CampaignController, RapportController,
     * InvoiceController) avec des divergences silencieuses. La version
     * unique évite qu'on en oublie un.
     */
    public function scopeForCommercialUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('commercial_user_id', $userId)
              ->orWhereHas('reservation', fn($r) =>
                    $r->where('commercial_user_id', $userId)
                      ->orWhere(function ($rr) use ($userId) {
                          $rr->whereNull('commercial_user_id')
                             ->where('user_id', $userId);
                      })
              )
              ->orWhere(function ($qq) use ($userId) {
                  $qq->whereDoesntHave('reservation')
                     ->where('user_id', $userId);
              });
        });
    }

    /**
     * Test rapide : cette campagne appartient-elle au commercial donné ?
     * Sert aux policies (CampaignPolicy::view, etc.) pour bloquer les
     * IDOR sans re-jouer toute la query SQL.
     */
    public function belongsToCommercialUser(int $userId): bool
    {
        if ((int) $this->commercial_user_id === $userId) return true;
        if ((int) ($this->user_id ?? 0) === $userId
            && $this->reservation_id === null) return true;
        $r = $this->relationLoaded('reservation') ? $this->reservation : null;
        if (!$r && $this->reservation_id) {
            $r = \App\Models\Reservation::find($this->reservation_id);
        }
        if (!$r) return false;
        if ((int) ($r->commercial_user_id ?? 0) === $userId) return true;
        if ($r->commercial_user_id === null
            && (int) ($r->user_id ?? 0) === $userId) return true;
        return false;
    }

    public function scopeEndingSoon($query, int $days = 14)
    {
        return $query->running()
            ->where('end_date', '>=', now()->startOfDay())
            ->where('end_date', '<=', now()->addDays($days)->endOfDay());
    }

    // ── Helpers durée ─────────────────────────────────────────────

    public function durationInDays(): int
    {
        return (int) abs($this->start_date->copy()->startOfDay()
                          ->diffInDays($this->end_date->copy()->startOfDay()));
    }

    /**
     * RÈGLE PATRONNE v2 (validée par écrit 2026-06-25) — Résout une période
     * en {mois, jours, propre}. Le moteur unique partagé par occupation,
     * affichage et facturation.
     *
     *   1) Compte les mois civils atteints (addMonthsNoOverflow + grâce -1j
     *      pour absorber les fins de mois courts type 28/29).
     *   2) Si < 1 mois : retourne jours réels inclusifs.
     *   3) Sinon : compte résiduel signé au dernier anniversaire. Tolérance
     *      $tolPlus jours absorbe le dépassement (par défaut +1).
     *
     * @param int $tolPlus Tolérance (en jours) au-delà de l'anniversaire
     *                     mensuel — passe à 2 pour absorber jusqu'à +2j.
     * @return array{mois:int, jours:int, propre:bool}
     */
    public static function resolvePeriodBetween(Carbon $debut, Carbon $fin, int $tolPlus = 1): array
    {
        $debut = $debut->copy()->startOfDay();
        $fin   = $fin->copy()->startOfDay();

        // 1) Mois atteints (grâce -1j : fin de mois civil avant l'anniv pile)
        $mois = 0;
        while ($fin->gte($debut->copy()->addMonthsNoOverflow($mois + 1)->subDay())) {
            $mois++;
        }

        // 2) Moins d'un mois → jours réels inclusifs
        if ($mois === 0) {
            return [
                'mois'   => 0,
                'jours'  => (int) $debut->diffInDays($fin) + 1,
                'propre' => true,
            ];
        }

        // 3) Résiduel signé au dernier anniversaire
        $anniv    = $debut->copy()->addMonthsNoOverflow($mois);
        $residuel = (int) $anniv->diffInDays($fin, false);
        $propre   = $residuel <= $tolPlus;

        return [
            'mois'   => $mois,
            'jours'  => $propre ? 0 : $residuel,
            'propre' => $propre,
        ];
    }

    /** Version d'instance — applique resolvePeriodBetween sur les dates de la campagne. */
    public function resolvePeriod(int $tolPlus = 1): array
    {
        return self::resolvePeriodBetween($this->start_date, $this->end_date, $tolPlus);
    }

    /**
     * Nombre de mois facturables — équation préservée `montant = billableMonths × tarif`.
     *
     * Cas couverts (RÈGLE PATRONNE 2026-06-25) :
     *   < 15 j           → 0.5                            (forfait demi-mois)
     *   15-29 j          → 0.5 + (jours - 15) / 30        (½ mois + prorata journalier)
     *   1 mois propre    → mois                           (anniversaire ±1j de tolérance)
     *   1 mois + Y j     → mois + Y / 30                  (mois + prorata journalier)
     *
     * Source unique partagée par CampaignService, InvoiceFromCampaignBuilder,
     * CampaignAmountConsistency, Reservation, exports, vues, emails.
     *
     * Non-rétroactivité : les factures déjà émises ont leurs invoice_lines
     * figées en BDD au moment de l'émission. Pas de recalcul automatique.
     */
    public function billableMonths(): float
    {
        return self::computeBillableMonths($this->start_date, $this->end_date);
    }

    /** Helper static — calcule billableMonths sur n'importe quel couple (start, end). */
    public static function computeBillableMonths(Carbon $start, Carbon $end): float
    {
        $r = self::resolvePeriodBetween($start, $end);
        if ($r['mois'] === 0) {
            return $r['jours'] < 15 ? 0.5 : 0.5 + ($r['jours'] - 15) / 30;
        }
        return $r['propre'] ? (float) $r['mois'] : $r['mois'] + $r['jours'] / 30;
    }

    /**
     * Libellé d'AFFICHAGE de la durée (RÈGLE PATRONNE 2026-06-25) :
     *   - < 1 mois        : "X jours"
     *   - 1 mois propre   : "X mois"
     *   - 1 mois + Y j    : "X mois + Y j"
     */
    public function durationLabel(): string
    {
        $r = $this->resolvePeriod();
        if ($r['mois'] === 0) {
            return $r['jours'] . ' jour' . ($r['jours'] > 1 ? 's' : '');
        }
        if ($r['propre']) {
            return $r['mois'] . ' mois';
        }
        return $r['mois'] . ' mois + ' . $r['jours'] . ' j';
    }

    /** Alias pour compatibilité — toujours basé sur billableMonths() */
    public function durationInMonths(): int
    {
        return max(1, (int) ceil($this->billableMonths()));
    }

    // ── Facturation : montant HT de référence ────────────────────────
    //
    // Une campagne porte un montant HT "vrai" qui est, par ordre de
    // préférence :
    //
    //   1. campaigns.total_amount (snapshot saisi/calculé à la création
    //      ou override admin via total_amount_overridden_*)
    //   2. reservations.total_amount de la résa parente (négocié globalement)
    //   3. somme des reservation_panels.total_price pour cette résa
    //      (= unit_price × billableMonths déjà calculé en pivot)
    //   4. 0 — pas de panneaux, pas de résa, pas de prix
    //
    // Servir cette valeur côté facturation évite que l'admin tape un
    // montant à la main alors qu'il est déjà connu dans le système.

    public function computedAmountHt(): float
    {
        if ($this->total_amount !== null) {
            return (float) $this->total_amount;
        }

        if (!$this->reservation_id) return 0.0;

        $resa = $this->relationLoaded('reservation')
            ? $this->reservation
            : Reservation::find($this->reservation_id);

        if (!$resa) return 0.0;
        if ($resa->total_amount !== null) {
            return (float) $resa->total_amount;
        }

        // Fallback : somme du pivot reservation_panels.total_price.
        return (float) \Illuminate\Support\Facades\DB::table('reservation_panels')
            ->where('reservation_id', $resa->id)
            ->sum('total_price');
    }

    /**
     * Total HT déjà facturé pour cette campagne — somme des factures
     * non annulées (brouillon + envoyée + payée). Sert à proposer un
     * montant "reste à facturer" cohérent en cas de facturation multiple.
     */
    public function alreadyBilledHt(): float
    {
        return (float) Invoice::query()
            ->where('campaign_id', $this->id)
            ->whereIn('status', ['brouillon', 'envoyee', 'payee'])
            ->sum('amount');
    }

    /**
     * Reste à facturer HT = montant de référence − déjà facturé.
     * Borné à 0 pour ne pas suggérer de montant négatif si la campagne
     * a été sur-facturée par erreur (l'admin reverra son historique).
     */
    public function remainingToBillHt(): float
    {
        return max(0.0, $this->computedAmountHt() - $this->alreadyBilledHt());
    }

    /**
     * Lot 9.1 — S'assure qu'une PoseTask existe pour chaque panneau
     * interne de la campagne. Idempotent. À appeler par les orchestrateurs
     * APRÈS le sync des panneaux (l'observer `created` est trop tôt).
     *
     * Retourne le nombre de tâches créées (0 si toutes existent déjà).
     */
    public function ensurePoseTasksAutoCreated(): int
    {
        return \App\Observers\CampaignObserver::createPoseTasksForCampaign($this);
    }

    /**
     * Durée lisible : "3 mois", "15 jours", "2 mois 5 j"
     *
     * Utilise Carbon::diff() pour respecter les MOIS CALENDAIRES réels
     * (25/05 → 25/06 = 1 mois exactement, pas 1 mois 1 jour). L'ancienne
     * version basée sur floor(days/30) approximait 1 mois = 30 jours et
     * comptait alors 31 jours comme "1 mois 1 j".
     */
    public function durationHuman(): string
    {
        // FIX 2026-06-26 — Délègue à durationLabel() qui utilise le moteur
        // unique resolvePeriodBetween (RÈGLE PATRONNE v2). Avant : DateInterval
        // calculait "01/06 → 31/08" comme "2 mois 30 j" alors que c'est
        // 3 mois civils complets. Le moteur unique gère la grâce -1j +
        // tolérance +1j → renvoie correctement "3 mois".
        return $this->durationLabel();
    }

    // ── Helpers progression (mémoïsés) ────────────────────────────

    /**
     * Pourcentage d'avancement avec précision sub-jour pour une animation fluide.
     * Mémoïsé pendant la requête pour éviter les recalculs en Blade.
     */
    public function progressPercent(): float
    {
        if (isset($this->progressCache['pct'])) return $this->progressCache['pct'];

        $now   = now();
        $start = $this->start_date->copy()->startOfDay();
        $end   = $this->end_date->copy()->endOfDay();

        if ($now->lt($start)) return $this->progressCache['pct'] = 0.0;
        if ($now->gte($end))  return $this->progressCache['pct'] = 100.0;

        // abs() obligatoire — Carbon 3.x peut renvoyer un delta signé
        $totalSec   = abs($end->diffInSeconds($start));
        $elapsedSec = abs($now->diffInSeconds($start));

        if ($totalSec <= 0) return $this->progressCache['pct'] = 100.0;

        $pct = round(($elapsedSec / $totalSec) * 100, 2);
        return $this->progressCache['pct'] = max(0.0, min(100.0, $pct));
    }

    /** Jours restants jusqu'à end_date (0 si terminée) */
    public function daysRemaining(): int
    {
        if (isset($this->progressCache['days'])) return $this->progressCache['days'];

        $now = now()->startOfDay();
        $end = $this->end_date->copy()->startOfDay();

        if ($now->lt($this->start_date->copy()->startOfDay())) {
            return $this->progressCache['days'] = (int) abs($now->diffInDays($end));
        }

        return $this->progressCache['days'] = max(0, (int) abs($now->diffInDays($end, false)));
    }

    /**
     * Texte humain lisible sur le temps restant
     * Ex : "Se termine aujourd'hui", "Dans 3 jours", "Dans 2 mois"
     */
    public function humanTimeRemaining(): string
    {
        $days = $this->daysRemaining();

        if ($days === 0) {
            if (now()->startOfDay()->eq($this->end_date->copy()->startOfDay())) {
                return "Se termine aujourd'hui";
            }
            return 'Terminée';
        }

        if ($days === 1) return 'Se termine demain';
        if ($days <= 7)  return "Se termine dans {$days} jours";
        if ($days <= 30) return "Se termine dans {$days} jours (" . ceil($days/7) . " sem.)";

        $months = (int) round($days / 30);
        if ($months === 1) return "Se termine dans environ 1 mois ({$days} j)";
        return "Se termine dans environ {$months} mois ({$days} j)";
    }

    /** Alerte fin proche : campagne ACTIF finissant dans <= $days jours */
    public function isEndingSoon(int $threshold = 14): bool
    {
        if ($this->status !== CampaignStatus::ACTIF) {
            return false;
        }
        $days = $this->daysRemaining();
        return $days > 0 && $days <= $threshold;
    }

    /** Statut "logique" d'après les dates — sans toucher la BDD */
    public function computedStatus(): CampaignStatus
    {
        $today = now()->startOfDay();
        $start = $this->start_date->copy()->startOfDay();
        $end   = $this->end_date->copy()->startOfDay();

        if ($start->gt($today))   return CampaignStatus::PLANIFIE;
        if ($end->lte($today))    return CampaignStatus::TERMINE;
        return CampaignStatus::ACTIF;
    }

    /**
     * Synchronise le statut en base par rapport aux dates si pertinent.
     *
     * Règles 2026-05 (refonte workflow campagne directe) :
     *
     * - ACTIF → TERMINE si end_date dépassée                   (auto)
     * - PLANIFIE → TERMINE si end_date dépassée                (auto)
     * - PLANIFIE → ACTIF : **JAMAIS** déclenché par cette méthode.
     *   La transition vers ACTIF se fait UNIQUEMENT via :
     *     · CampaignService::activate()      (manuel ou cron)
     *   pour bénéficier des gardes "≥ 1 panneau" + envoi du mail
     *   au client. Auparavant, l'auto-promotion ici bypassait ces
     *   gardes : une campagne créée le jour J avec 0 panneau
     *   passait en ACTIF à l'ouverture de la fiche, sans mail
     *   et avec un montant à 0.
     *
     * - Ne touche pas les statuts terminaux (TERMINE/ANNULE) ni PAUSE
     *
     * @return bool True si le statut a changé
     */
    public function syncStatusWithDates(): bool
    {
        if ($this->status->isTerminal()) return false;
        if ($this->status === CampaignStatus::PAUSE) return false;

        $today = now()->startOfDay();
        $end   = $this->end_date->copy()->startOfDay();
        $newStatus = null;

        if ($this->status === CampaignStatus::ACTIF && $end->lte($today)) {
            $newStatus = CampaignStatus::TERMINE;
        } elseif ($this->status === CampaignStatus::PLANIFIE && $end->lte($today)) {
            $newStatus = CampaignStatus::TERMINE;
        } else {
            // Filet de sauvetage : campagne ACTIVE avec 0 panneau →
            // c'est forcément un état hérité de l'ancien bug
            // d'auto-promotion. On la rebascule en PLANIFIE pour que
            // l'utilisateur puisse y ajouter des panneaux + démarrer
            // proprement (avec le mail client).
            if ($this->status === CampaignStatus::ACTIF) {
                $totalPanels = $this->panels()->count() + $this->externalPanels()->count();
                if ($totalPanels === 0) {
                    $newStatus = CampaignStatus::PLANIFIE;
                }
            }
        }

        if ($newStatus === null || $newStatus === $this->status) return false;

        $oldStatus    = $this->status;
        $this->status = $newStatus;
        $this->save();

        Log::info('campaign.status.auto_synced', [
            'campaign_id' => $this->id,
            'old_status'  => $oldStatus->value,
            'new_status'  => $newStatus->value,
            'reason'      => 'date_based_calculation',
        ]);

        return true;
    }

    /** @deprecated Utiliser syncStatusWithDates() */
    public function updateStatusBasedOnDates(): bool
    {
        return $this->syncStatusWithDates();
    }
}
