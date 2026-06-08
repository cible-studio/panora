<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'client_id', 'campaign_id', 'created_by',
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
    ];

    protected $casts = [
        'remise_pct'           => 'decimal:2',
        'amount'               => 'decimal:2',
        'net_ht'               => 'decimal:2',
        'tva'                  => 'decimal:2',
        'tva_amount'           => 'decimal:2',
        'tsp_amount'           => 'decimal:2',
        'tm_total'             => 'decimal:2',
        'odp_total'            => 'decimal:2',
        'services_impression'  => 'decimal:2',
        'services_pose_depose' => 'decimal:2',
        'amount_ttc'           => 'decimal:2',
        'total_a_payer'        => 'decimal:2',
        'issued_at'            => 'date',
        'paid_at'              => 'date',
        'locked_at'            => 'datetime',
        'campaign_year'        => 'integer',
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
     * Statut de paiement DÉRIVÉ — distinct du status (brouillon/envoyee/
     * annulee). Toujours recalculé à la demande.
     *
     *   'non_payee' : aucun versement
     *   'partielle' : versements < total
     *   'soldee'    : versements ≥ total
     */
    public function paymentStatus(): string
    {
        if ($this->status === 'annulee') return 'annulee';
        $paid  = $this->paidAmount();
        $total = (float) ($this->total_a_payer ?: $this->amount_ttc ?: 0);
        if ($paid <= 0) return 'non_payee';
        if ($paid + 0.01 >= $total) return 'soldee';
        return 'partielle';
    }

    // ═══════════════════════════════════════════════════════════════
    // VERROUILLAGE
    // ═══════════════════════════════════════════════════════════════

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
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
