<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'échéancier prévisionnel — engagement de paiement d'une
 * facture (acompte / solde / mensualité). Cf. invoice_schedules.
 *
 * État dérivé `state` :
 *   'paid'    → paid_at posé
 *   'overdue' → due_date < today et non payé
 *   'soon'    → due_date dans les 7 prochains jours
 *   'upcoming'→ due_date > today + 7
 */
class InvoiceSchedule extends Model
{
    protected $fillable = [
        'invoice_id', 'due_date', 'amount', 'label',
        'order_index', 'paid_at', 'paid_payment_id',
        'reminded_at', 'reminder_count', 'note',
    ];

    protected $casts = [
        'due_date'        => 'date',
        'amount'          => 'integer',
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

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
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

    public function state(): string
    {
        if ($this->isPaid()) return 'paid';
        if ($this->isOverdue()) return 'overdue';
        $days = $this->daysUntilDue();
        if ($days <= 7) return 'soon';
        return 'upcoming';
    }
}
