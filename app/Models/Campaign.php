<?php
namespace App\Models;

use App\Enums\CampaignStatus;
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
        'total_amount' => 'decimal:2',
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
     * Nombre de mois facturables — RÈGLE UNIQUE de la régie CIBLE CI :
     *   - 1 à 15 jours résiduels  → +0.5 mois
     *   - 16 à 30 jours résiduels → +1 mois
     *   - minimum facturable      → 0.5 mois
     *
     * Cette méthode est la SEULE source de vérité pour les calculs de montant
     * (utilisée à la fois par le model et CampaignService).
     */
    public function billableMonths(): float
    {
        $days = $this->durationInDays();
        if ($days <= 0) return 0.5;

        $full   = (int) floor($days / 30);
        $remain = $days % 30;

        $fraction = 0.0;
        if ($remain >= 1 && $remain <= 15)      $fraction = 0.5;
        elseif ($remain > 15)                    $fraction = 1.0;

        return max($full + $fraction, 0.5);
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
        $start = $this->start_date->copy()->startOfDay();
        $end   = $this->end_date->copy()->startOfDay();
        $diff  = $start->diff($end); // DateInterval

        $months = (int) $diff->m + ((int) $diff->y * 12);
        $days   = (int) $diff->d;
        $totalDays = $this->durationInDays();

        // Moins d'un mois calendaire → exprimé en jours
        if ($months === 0) {
            return $totalDays . ' jour' . ($totalDays > 1 ? 's' : '');
        }

        // Mois pile (jour de fin == jour de début dans le mois suivant)
        if ($days === 0) {
            return $months . ' mois';
        }

        return $months . ' mois ' . $days . ' j';
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
