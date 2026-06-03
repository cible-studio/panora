<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'city', 'region', 'odp_rate', 'tm_rate',
    ];

    protected $casts = [
        'odp_rate' => 'decimal:2',
        'tm_rate'  => 'decimal:2',
    ];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function panels()
    {
        return $this->hasMany(Panel::class);
    }

    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }

    public function externalPanels()
    {
        return $this->hasMany(ExternalPanel::class);
    }

    public function rateHistory()
    {
        return $this->hasMany(CommuneTaxRateHistory::class)
            ->orderByDesc('effective_from');
    }

    /**
     * Tarifs ODP / TM applicables à la date donnée.
     * Cherche d'abord dans l'historique pour cohérence des calculs
     * rétroactifs (changement tarifaire en 2026 → un calcul sur janvier
     * doit utiliser le tarif de janvier, pas le nouveau).
     *
     * Fallback : tarifs courants de la fiche commune (utiles tant que
     * l'historique n'a pas encore été initialisé pour cette date).
     *
     * @return array{odp:float, tm:float}
     */
    public function ratesAt(\DateTimeInterface|string $date): array
    {
        $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        $row = $this->rateHistory()
            ->where('effective_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($row) {
            return [
                'odp' => (float) $row->odp_rate,
                'tm'  => (float) $row->tm_rate,
            ];
        }

        return [
            'odp' => (float) ($this->odp_rate ?? 0),
            'tm'  => (float) ($this->tm_rate ?? 0),
        ];
    }
}