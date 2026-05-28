<?php
// app/Services/PanelGeoLocator.php

namespace App\Services;

use App\Models\Panel;
use App\Models\Pige;
use Illuminate\Support\Facades\Log;

/**
 * Recalcule les coordonnées GPS d'un panneau à partir des piges VALIDÉES
 * qui portent un GPS. Appelé après chaque validation de pige.
 *
 * Règles métier (tranchées) :
 *  - gps_source='manual'  → on NE TOUCHE À RIEN (saisie humaine = autorité).
 *  - gps_source=NULL + lat/lng DÉJÀ présents → position legacy/manuelle non
 *    typée → on la respecte aussi (ne pas écraser une saisie historique).
 *  - 0 pige géolocalisée  → no-op.
 *  - 1 pige  → 'pige_provisional' (à confirmer).
 *  - 2+ piges → médiane → 'pige_confirmed' ; si la dispersion dépasse le
 *    seuil, on lève gps_dispersion_flag mais on garde la médiane (signaler
 *    sans bloquer).
 */
class PanelGeoLocator
{
    /** Seuil de dispersion (mètres) au-delà duquel on lève le drapeau. */
    private const DISPERSION_THRESHOLD_M = 100.0;

    /** Tolérance de comparaison lat/lng (~1 cm) pour éviter les écritures inutiles. */
    private const COORD_EPSILON = 1e-7;

    public function __construct(
        private readonly GeoService $geo,
    ) {}

    /**
     * Recalcule et enregistre le GPS du panneau si pertinent.
     *
     * @return bool true si le panneau a été mis à jour, false sinon.
     */
    public function recomputeFromPiges(Panel $panel): bool
    {
        // 1. Saisie manuelle explicite → intouchable.
        if ($panel->gps_source === 'manual') {
            return false;
        }

        // 1bis. Position legacy non typée (coords présentes, source inconnue)
        //       → potentiellement saisie main avant l'introduction de
        //       gps_source : on ne l'écrase pas.
        if ($panel->gps_source === null
            && $panel->latitude !== null
            && $panel->longitude !== null) {
            return false;
        }

        // 2. Piges VALIDÉES géolocalisées de ce panneau.
        //    Utilise l'index FK piges_panel_id_foreign (panel_id) ; le filtre
        //    status se fait sur les quelques piges du panneau (peu nombreuses).
        $coords = Pige::query()
            ->where('panel_id', $panel->id)
            ->where('status', 'verifie')
            ->whereNotNull('gps_lat')
            ->whereNotNull('gps_lng')
            ->get(['gps_lat', 'gps_lng'])
            ->map(fn(Pige $p) => [
                'lat' => (float) $p->gps_lat,
                'lng' => (float) $p->gps_lng,
            ])
            ->all();

        $count = count($coords);

        // 3. Aucune pige géolocalisée → rien à faire.
        if ($count === 0) {
            return false;
        }

        // 4. Position de référence + drapeau dispersion.
        if ($count === 1) {
            $lat        = $coords[0]['lat'];
            $lng        = $coords[0]['lng'];
            $source     = 'pige_provisional';
            $dispersion = false;
        } else {
            $median     = $this->geo->median($coords);
            $lat        = $median['lat'];
            $lng        = $median['lng'];
            $source     = 'pige_confirmed';
            $dispersion = $this->geo->maxDispersion($coords) > self::DISPERSION_THRESHOLD_M;
        }

        $lat = round($lat, 7);
        $lng = round($lng, 7);

        // 5. Optimisation : pas d'écriture (ni de log) si le résultat est
        //    identique à l'état courant du panneau.
        if ($source === $panel->gps_source
            && (bool) $panel->gps_dispersion_flag === $dispersion
            && $panel->latitude !== null && $panel->longitude !== null
            && abs((float) $panel->latitude - $lat) < self::COORD_EPSILON
            && abs((float) $panel->longitude - $lng) < self::COORD_EPSILON) {
            return false;
        }

        // 6. Persiste. forceFill : indépendant du $fillable, pas d'effet de bord.
        $panel->forceFill([
            'latitude'            => $lat,
            'longitude'           => $lng,
            'gps_source'          => $source,
            'gps_dispersion_flag' => $dispersion,
            'gps_computed_at'     => now(),
        ])->save();

        Log::info('panel.gps.auto_computed', [
            'panel_id'   => $panel->id,
            'source'     => $source,
            'pige_count' => $count,
            'dispersion' => $dispersion,
            'lat'        => $lat,
            'lng'        => $lng,
        ]);

        return true;
    }
}
