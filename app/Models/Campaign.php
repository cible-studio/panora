<?php
namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    public function scopeNonFacturees($query)
    {
        return $query->whereIn('status', ['actif', 'pose', 'termine'])
                     ->doesntHave('invoices');
    }

    // ── Helpers durée ─────────────────────────────────────────────

    public function durationInDays(): int
    {
        return (int) $this->start_date->startOfDay()
                          ->diffInDays($this->end_date->startOfDay());
    }

    public function durationInMonths(): int
    {
        return max(1, (int) ceil($this->durationInDays() / 30));
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

    // ── Helpers progression ───────────────────────────────────────

    /**
     * Pourcentage d'avancement — corrigé pour éviter 0 sur une longue période
     */
    // ✅ Corrigé — logique complète
    public function progressPercent(): int
    {
        $now   = now()->startOfDay();
        $start = $this->start_date->startOfDay();
        $end   = $this->end_date->startOfDay();

        // Pas encore commencé
        if ($now->lt($start)) return 0;
        
        // Terminé
        if ($now->gte($end)) return 100;
        
        $total   = (int) $start->diffInDays($end);
        $elapsed = (int) $start->diffInDays($now);

        if ($total <= 0) return 100;
        return (int) min(100, max(0, round($elapsed / $total * 100)));
    }

    // daysRemaining() — aussi à corriger
    public function daysRemaining(): int
    {
        $now = now()->startOfDay();
        $end = $this->end_date->startOfDay();
        
        // Campagne future
        if ($now->lt($this->start_date->startOfDay())) {
            return (int) $now->diffInDays($end); // jours total restants
        }
        
        return max(0, (int) $now->diffInDays($end, false));
    }

    /**
     * Texte humain lisible sur le temps restant
     * Ex : "Se termine aujourd'hui à 23:59", "Dans 3 jours", "Dans 2 mois"
     */
    public function humanTimeRemaining(): string
    {
        $days = $this->daysRemaining();

        if ($days === 0) {
            // Vérifier si c'est vraiment aujourd'hui ou passé
            if (now()->startOfDay()->eq($this->end_date->startOfDay())) {
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

    /**
     * Alerte fin proche — true si actif et se termine dans <= 14 jours
     */
    public function isEndingSoon(): bool
    {
        $days = $this->daysRemaining();
        return $this->status === CampaignStatus::ACTIF
            && $days > 0
            && $days <= 14;
    }

    /**
     * Met à jour le statut de la campagne en fonction des dates
     * Utile pour les cron jobs ou les recalculs massifs
     * 
     * @return bool True si le statut a changé
     */
    public function updateStatusBasedOnDates(): bool
    {
        $today = now()->startOfDay();
        $start = $this->start_date->startOfDay();
        $end   = $this->end_date->startOfDay();
        
        $newStatus = null;
        
        if ($start > $today) {
            $newStatus = CampaignStatus::PLANIFIE;
        } elseif ($start <= $today && $end > $today) {
            $newStatus = CampaignStatus::ACTIF;
        } elseif ($end <= $today) {
            $newStatus = CampaignStatus::TERMINE;
        }
        
        if ($newStatus && $this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->save();
            
            Log::info('campaign.status.auto_updated', [
                'campaign_id' => $this->id,
                'old_status'  => $this->getOriginal('status'),
                'new_status'  => $newStatus->value,
                'reason'      => 'date_based_calculation',
            ]);
            
            return true;
        }
        
        return false;
    }


}