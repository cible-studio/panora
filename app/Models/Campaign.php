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
        'user_id', 'updated_by',
        'start_date', 'end_date', 'status',
        'total_panels', 'total_amount', 'notes',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_amount' => 'decimal:2',
        'total_panels' => 'integer',
        'status'       => CampaignStatus::class,
    ];

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

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function panels()
    {
        return $this->belongsToMany(Panel::class, 'campaign_panels')
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

    // ── Scopes ────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::ACTIF->value);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', CampaignStatus::TERMINE->value);
    }

    /** Campagnes "en cours" : actif + pose (pour la barre de progression) */
    public function scopeRunning($query)
    {
        return $query->whereIn('status', [
            CampaignStatus::ACTIF->value,
            CampaignStatus::POSE->value,
        ]);
    }

    public function scopeNonFacturees($query)
    {
        return $query->whereIn('status', ['actif', 'pose', 'termine'])
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

    /**
     * Durée lisible : "3 mois", "15 jours", "2 mois 5 j"
     */
    public function durationHuman(): string
    {
        $days   = $this->durationInDays();
        $months = (int) floor($days / 30);
        $remDays = $days % 30;

        if ($months === 0) {
            return $days . ' jour' . ($days > 1 ? 's' : '');
        }
        if ($remDays === 0) {
            return $months . ' mois';
        }
        return $months . ' mois ' . $remDays . ' j';
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

    /** Alerte fin proche : campagne en cours (actif/pose) finissant dans <= $days jours */
    public function isEndingSoon(int $threshold = 14): bool
    {
        if (!in_array($this->status, [CampaignStatus::ACTIF, CampaignStatus::POSE])) {
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
     * - PLANIFIE → ACTIF si start_date atteinte
     * - ACTIF/POSE → TERMINE si end_date dépassée
     * - Ne touche pas les statuts terminaux (TERMINE/ANNULE)
     *
     * @return bool True si le statut a changé
     */
    public function syncStatusWithDates(): bool
    {
        if ($this->status->isTerminal()) return false;

        $today = now()->startOfDay();
        $start = $this->start_date->copy()->startOfDay();
        $end   = $this->end_date->copy()->startOfDay();
        $newStatus = null;

        if ($this->status === CampaignStatus::PLANIFIE && $start->lte($today) && $end->gt($today)) {
            $newStatus = CampaignStatus::ACTIF;
        } elseif (in_array($this->status, [CampaignStatus::ACTIF, CampaignStatus::POSE]) && $end->lte($today)) {
            $newStatus = CampaignStatus::TERMINE;
        } elseif ($this->status === CampaignStatus::PLANIFIE && $end->lte($today)) {
            $newStatus = CampaignStatus::TERMINE;
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
