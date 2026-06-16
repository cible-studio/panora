<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Ligne d'échéancier prévisionnel — engagement de paiement d'une facture
 * (acompte / solde / mensualité). Cf. invoice_schedules.
 *
 * Mission "bug échéancier" (juin 2026) : le statut est désormais DÉRIVÉ
 * du cumul des versements via paid_amount, jamais marqué manuellement.
 *
 * État dérivé `state` :
 *   'paid'    → paid_amount >= amount ET paid_at posé (entièrement couverte)
 *   'partial' → 0 < paid_amount < amount (paiement partiel en cours)
 *   'overdue' → 0 paid_amount ET due_date < today
 *   'soon'    → 0 paid_amount ET due_date dans les 7 prochains jours
 *   'upcoming'→ 0 paid_amount ET due_date > today + 7
 *
 * Toute mise à jour de paid_amount / paid_at / paid_payment_id doit
 * passer par ScheduleAllocationService::recomputeFromPayments.
 */
class InvoiceSchedule extends Model implements Auditable
{
    use AuditableTrait;
    protected $auditExclude = ['updated_at'];

    protected $fillable = [
        'invoice_id', 'due_date', 'amount', 'label',
        'order_index', 'paid_at', 'paid_payment_id',
        'paid_amount',
        'reminded_at', 'reminder_count', 'note',
    ];

    protected $casts = [
        'due_date'        => 'date',
        'amount'          => 'integer',
        'paid_amount'     => 'integer',
        'paid_at'         => 'date',
        'reminded_at'     => 'date',
        'order_index'     => 'integer',
        'reminder_count'  => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'paid_payment_id');
    }

    /** Entièrement soldée (paid_amount >= amount ET paid_at posé). */
    public function isPaid(): bool
    {
        return $this->paid_at !== null
            && (int) $this->paid_amount >= (int) $this->amount;
    }

    /** Au moins un versement imputé, mais pas encore totalement couverte. */
    public function isPartial(): bool
    {
        return !$this->isPaid()
            && (int) $this->paid_amount > 0;
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid()
            && $this->due_date
            && $this->due_date->startOfDay()->lt(now()->startOfDay());
    }

    public function daysUntilDue(): int
    {
        if (!$this->due_date) return 0;
        return (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
    }

    /** Reste à imputer pour solder l'échéance. */
    public function remainingAmount(): int
    {
        return max(0, (int) $this->amount - (int) $this->paid_amount);
    }

    /** Pourcentage d'avancement [0, 100]. */
    public function paymentPercentage(): float
    {
        if ((int) $this->amount <= 0) return 0.0;
        return round(min(100.0, ((int) $this->paid_amount / (int) $this->amount) * 100), 1);
    }

    public function state(): string
    {
        if ($this->isPaid())   return 'paid';
        if ($this->isPartial()) return 'partial';
        if ($this->isOverdue()) return 'overdue';
        $days = $this->daysUntilDue();
        if ($days <= 7) return 'soon';
        return 'upcoming';
    }
}
