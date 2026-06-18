// public/js/tech/core/state.js — Phase 3 SM1.
//
// État global UI partagé entre features (filtres, tournée, etc.). Objet
// simple (pas de Proxy — décision 3 du brief SM1 : "objet simple, pas
// Proxy pour SM1"). Les features lisent/écrivent directement les clés
// dont elles ont besoin.
//
// Note : pour la SM1, on garde la même logique 1:1 que le JS inline pré-
// refonte. La SM2 introduira un store réactif si besoin.

export const state = {
    // Bloc filtres (cf. features/filters.js) — STRUCTURE EXACTE du JS pré-
    // refonte (cf. lignes 985-991 du <script> avant Phase 3) :
    //   kpi      : 'all' | 'today' (compatibilité KPI grid existant)
    //   chips    : Set<string>  ('late' | 'today' | 'problem' | 'reject' | 'en_route' | 'en_cours')
    //   zone     : string|null  (commune sélectionnée via TOC ou Select2)
    //   distance : bool         (tri haversine activé)
    //   geo      : {lat, lng}|null  (position tech captée)
    filterState: {
        kpi: 'all',
        chips: new Set(),
        zone: null,
        distance: false,
        geo: null,
    },

    // Mode tournée TSP (cf. features/geolocate.js).
    tourActive: false,
    originalParentByCard: new Map(), // restauration DOM à l'annulation

    // Heartbeat (cf. features/heartbeat.js).
    lastKnownTaskId: 0,
    firstTick: true,
};
