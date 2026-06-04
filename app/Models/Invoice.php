<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'client_id', 'campaign_id', 'created_by',
        'amount', 'tva', 'amount_ttc',
        'issued_at', 'paid_at', 'status',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'tva'        => 'decimal:2',
        'amount_ttc' => 'decimal:2',
        'issued_at'  => 'date',
        'paid_at'    => 'date',
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
        return $this->status === 'payee';
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
