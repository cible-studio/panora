// public/js/tech/features/geolocate.js — Phase 3 SM1 (STUB Lot G).
//
// ⚠ EXTRACTION INCOMPLÈTE — laissée intentionnellement en stand-by pour
// rester dans le scope raisonnable de la SM1 livrable.
//
// Statut Phase 3 SM1 :
//   - Structure du module créée (init() exporté, prêt à recevoir le code)
//   - Code source NON encore migré depuis le <script> inline
//   - Le <script> inline historique de tech-space.blade.php OPÈRE TOUJOURS
//   - Aucun double-binding possible (init() est un no-op dans cette version)
//
// Blocs sources à migrer (cf. tech-space.blade.php post-lot-F) :
//   - Tri par distance GPS haversine — bloc 2 (~lignes 1236-1343)
//     Bouton "Près de moi" : navigator.geolocation + tri cards par distance
//   - Mode tournée TSP nearest-neighbor — bloc 2 (~lignes 1428-1563)
//     Bouton "Mon chemin" : POST optimize + reparenter cards dans
//     ts-tour-section + pose-tour-leg distance/temps
//
// Note d'architecture pour SM1.5 :
//   - Importer { state } depuis '../core/state.js' (tourActive,
//     originalParentByCard, filterState.distance, filterState.geo)
//   - Importer { applyFilters, writeFiltersToUrl } depuis './filters.js'
//     (re-évaluer la liste après tri/mode tournée)
//   - URL de l'endpoint TSP : window.TECH_CONFIG.routes.optimize

import { state } from '../core/state.js';

export function init() {
    // No-op : le code inline historique gère encore distance et tournée.
}
