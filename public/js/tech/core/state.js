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
    // Bloc filtres (cf. features/filters.js) — clés exactes utilisées par
    // le code historique pour rester comportement-identique.
    filterState: {
        kpi: 'all',           // 'all' | 'today' (cf. KPI cards)
        late: false,
        today: false,
        problem: false,
        reject: false,
        en_route: false,
        en_cours: false,
    },

    // Mode tournée TSP (cf. features/geolocate.js).
    tourActive: false,
    originalParentByCard: new Map(), // restauration DOM à l'annulation

    // Heartbeat (cf. features/heartbeat.js).
    lastKnownTaskId: 0,
    firstTick: true,
};
