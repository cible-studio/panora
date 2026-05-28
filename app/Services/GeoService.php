<?php
// app/Services/GeoService.php

namespace App\Services;

/**
 * Calculs géographiques purs (sans dépendance Eloquent).
 *
 * Toutes les distances renvoyées sont en MÈTRES.
 * Service stateless : injectable, testable unitairement sans base.
 */
class GeoService
{
    /** Rayon moyen de la Terre en mètres. */
    private const EARTH_RADIUS_M = 6_371_000;

    /** Seuils anti-fraude (mètres) — distance pige ↔ panneau. */
    public const FRAUD_OK_M   = 50.0;
    public const FRAUD_WARN_M = 200.0;

    /**
     * Distance haversine entre deux points GPS, en mètres.
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * Médiane d'un ensemble de coordonnées.
     * Plus robuste que la moyenne : ignore les points aberrants.
     * La médiane est calculée indépendamment sur lat et lng.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @return array{lat: float, lng: float}|null  null si tableau vide
     */
    public function median(array $coords): ?array
    {
        if (empty($coords)) {
            return null;
        }

        $lats = array_map(fn($c) => (float) $c['lat'], $coords);
        $lngs = array_map(fn($c) => (float) $c['lng'], $coords);

        return [
            'lat' => $this->medianOf($lats),
            'lng' => $this->medianOf($lngs),
        ];
    }

    /**
     * Plus grande distance (mètres) entre deux points du lot (dispersion).
     * Sert au garde-fou : si > seuil, les piges divergent trop.
     *
     * @param  array<int, array{lat: float, lng: float}>  $coords
     */
    public function maxDispersion(array $coords): float
    {
        $n = count($coords);
        if ($n < 2) {
            return 0.0;
        }

        $max = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $d = $this->haversine(
                    (float) $coords[$i]['lat'], (float) $coords[$i]['lng'],
                    (float) $coords[$j]['lat'], (float) $coords[$j]['lng'],
                );
                if ($d > $max) {
                    $max = $d;
                }
            }
        }

        return $max;
    }

    /**
     * Classe une distance pige ↔ panneau selon les seuils anti-fraude.
     * Les cas no_gps / no_panel_gps sont décidés par l'appelant (l'observer),
     * pas ici : cette méthode ne traite que le cas "distance connue".
     *
     *   ≤ 50 m   → 'ok'   (cohérent)
     *   ≤ 200 m  → 'warn' (à vérifier)
     *   > 200 m  → 'out'  (hors-zone)
     */
    public function classify(?float $distanceMeters): string
    {
        if ($distanceMeters === null) {
            return 'no_gps';
        }
        if ($distanceMeters <= self::FRAUD_OK_M) {
            return 'ok';
        }
        if ($distanceMeters <= self::FRAUD_WARN_M) {
            return 'warn';
        }
        return 'out';
    }

    /**
     * Médiane d'un tableau de nombres.
     */
    private function medianOf(array $values): float
    {
        sort($values);
        $n   = count($values);
        $mid = intdiv($n, 2);

        // Nombre impair → valeur centrale. Pair → moyenne des deux centrales.
        return $n % 2 === 1
            ? (float) $values[$mid]
            : (float) (($values[$mid - 1] + $values[$mid]) / 2);
    }
}
