<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerte planifiée sur une échéance de facture (§7 cahier).
 *
 * Matérialisée à l'avance par InvoiceAlertsJob (planifié quotidien) pour
 * chaque échéance active. Statut 'pending' au départ ; passe à 'sent'
 * quand le job traite l'envoi.
 */
class InvoiceAlert extends Model
{
    protected $fillable = [
        'invoice_id', 'schedule_id',
        'kind', 'offset_days', 'due_date',
        'channel', 'status', 'sent_at', 'error_message',
    ];

    protected $casts = [
        'due_date' => 'date',
        'sent_at'  => 'datetime',
        'offset_days' => 'integer',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(InvoiceSchedule::class, 'schedule_id'); }

    public function isOverdue(): bool
    {
        return $this->kind === 'after';
    }

    /** Libellé humain pour les notifications. */
    public function humanLabel(): string
    {
        $abs = abs($this->offset_days);
        $direction = $this->kind === 'before' ? 'avant' : 'après';
        return "J{$this->offset_days} ({$abs}j {$direction} échéance)";
    }
}
