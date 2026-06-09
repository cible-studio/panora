<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace chaque changement de statut d'une facture (§13 cahier).
 *
 * Distinct de l'éventuel package laravel-auditing (Phase 7) qui logguera
 * TOUTES les modifications. Cette table est dédiée aux transitions de
 * statut pour permettre une lecture chronologique RAPIDE par facture
 * (timeline "Brouillon le 01/01 → Validée le 03/01 → Envoyée le 05/01…").
 *
 * Une ligne est créée :
 *   - par l'observer InvoiceObserver à chaque update de la colonne status
 *   - par les transitions auto déclenchées par PaymentService / Job
 *     "passage en retard"
 */
class InvoiceStatusHistory extends Model
{
    protected $table = 'invoice_status_history';

    protected $fillable = [
        'invoice_id', 'from_status', 'to_status',
        'user_id', 'reason', 'auto',
    ];

    protected $casts = [
        'auto' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
