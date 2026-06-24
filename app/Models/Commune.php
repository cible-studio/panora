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
        'odp_rate' => 'integer',
        'tm_rate'  => 'integer',
    ];

    /**
     * Liste officielle des 13 communes du District Autonome d'Abidjan.
     *
     * Source unique pour la classification "Abidjan vs Intérieur" — préférée
     * au champ `city` qui n'est pas fiable (selon l'import, on peut avoir
     * city='Abidjan' ou city='Bingerville' pour Bingerville). Cette liste
     * blanche garantit qu'un nouvel ajout n'oublie pas la classification.
     *
     * Match case-insensitive + tolérance des accents et tirets.
     */
    public const ABIDJAN_COMMUNES = [
        'abobo', 'adjame', 'attecoube', 'cocody', 'koumassi', 'marcory',
        'plateau', 'port-bouet', 'treichville', 'yopougon',
        'anyama', 'bingerville', 'songon',
    ];

    /**
     * Vrai si cette commune fait partie du District Autonome d'Abidjan.
     * Normalise le nom (lowercase, sans accents, sans espaces) avant match.
     */
    public function isAbidjan(): bool
    {
        return self::nameIsAbidjan($this->name);
    }

    /**
     * Version statique — utile dans les filtres SQL et les agrégations
     * où on a juste le nom (string) sans charger l'instance complète.
     */
    public static function nameIsAbidjan(?string $name): bool
    {
        if (empty($name)) return false;
        $normalized = self::normalizeForMatch($name);
        return in_array($normalized, self::ABIDJAN_COMMUNES, true);
    }

    /**
     * IDs (en BDD) de toutes les communes du District Autonome d'Abidjan.
     * Cache statique en mémoire : 1 seule requête par request HTTP même si
     * appelée 10 fois (utilisé dans les filtres de plusieurs controllers).
     *
     * Usage :
     *   Panel::whereIn('commune_id', Commune::abidjanIds())          // Abidjan
     *   Panel::whereNotIn('commune_id', Commune::abidjanIds())       // Intérieur
     */
    public static function abidjanIds(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        return $cache = self::query()
            ->select(['id', 'name'])
            ->get()
            ->filter(fn ($c) => $c->isAbidjan())
            ->pluck('id')
            ->all();
    }

    /**
     * Normalise un nom de commune pour le matching : lowercase, accents
     * remplacés (é→e, è→e, ô→o…), tirets et espaces unifiés.
     */
    public static function normalizeForMatch(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        // Translit accents → ASCII
        $name = strtr($name, [
            'à'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i',
            'ô'=>'o','ö'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ]);
        // "port-bouet", "san-pedro" — normaliser espaces internes en tiret
        $name = preg_replace('/\s+/', '-', $name);
        return $name;
    }

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