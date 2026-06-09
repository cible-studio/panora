<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Une trace de relance de recouvrement.
 * Affichée dans le tableau de bord financier (onglet Recouvrement) et
 * sur la fiche client (historique relances) et la fiche facture.
 */
class Relance extends Model
{
    protected $fillable = [
        'client_id', 'invoice_id', 'schedule_id',
        'relance_date', 'canal', 'note', 'suite_donnee',
        'user_id',
    ];

    protected $casts = [
        'relance_date' => 'date',
    ];

    public const CANAUX = [
        'telephone' => '📞 Téléphone',
        'email'     => '✉️ Email',
        'courrier'  => '📮 Courrier',
        'visite'    => '🚶 Visite',
        'autre'     => '🔁 Autre',
    ];

    public function client()   { return $this->belongsTo(Client::class); }
    public function invoice()  { return $this->belongsTo(Invoice::class); }
    public function schedule() { return $this->belongsTo(InvoiceSchedule::class); }
    public function user()     { return $this->belongsTo(User::class); }

    public function canalLabel(): string
    {
        return self::CANAUX[$this->canal] ?? ucfirst($this->canal);
    }

    /** Restreint aux relances de factures liées à un commercial — utilise
     *  le scope canonique Invoice::forCommercialUser pour rester cohérent
     *  avec les autres vues RBAC. */
    public function scopeForCommercialUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereHas('invoice', fn($i) => $i->forCommercialUser($userId))
              ->orWhere(function ($qq) use ($userId) {
                  // Relance "globale" (sans facture) → on suit le créateur
                  $qq->whereNull('invoice_id')->where('user_id', $userId);
              });
        });
    }
}
