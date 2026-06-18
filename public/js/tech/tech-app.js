// public/js/tech/tech-app.js — Phase 3 SM1.
//
// Point d'entrée du JS modulaire de l'Espace Technicien. Importe les
// modules core (api/state/offline/sw-register) + features (extraites
// progressivement depuis le <script> inline pré-refonte).
//
// Pas de bundler — chargé via <script type="module"> côté Blade. Les
// imports relatifs './core/x.js' fonctionnent nativement (ESM).
//
// Préconditions :
//  1. window.TECH_CONFIG doit être publié AVANT ce module (cf. partial
//     resources/views/public/tech/partials/_js_config.blade.php).
//  2. Le code inline restant DANS tech-space.blade.php garde la main sur
//     les features non encore migrées en Phase 3. Pas de double init.
//
// Stratégie de migration progressive : chaque lot F/G ajoute son init()
// ici en commentant le bloc inline correspondant. À la fin de Phase 3,
// le <script> inline ne contient plus que les contrats globaux (jQuery,
// $ alias, helpers toast utilisés par plusieurs features avant migration).

import { init as initOffline }     from './core/offline.js';
import { init as initSwRegister }  from './core/sw-register.js';

// Garde-fou : si TECH_CONFIG n'est pas là, on log mais on n'explose pas
// (la page continue de fonctionner via le JS inline encore présent).
if (!window.TECH_CONFIG) {
    console.warn('[tech-app] window.TECH_CONFIG absent — chargement du partial _js_config.blade.php a échoué ?');
}

function bootstrap() {
    // Lot E (Phase 3) — infrastructure :
    //   - api.js : utilitaires fetch (importé à la demande par les features)
    //   - state.js : objet d'état partagé (importé à la demande)
    //   - sw-register : enregistre le Service Worker /tech-sw.js
    //   - offline : online/offline events + flush queue retour réseau
    initSwRegister();
    initOffline();

    // Lots F + G (Phase 3) — features : à ajouter au fur et à mesure.
    // initPwaInstall();
    // initHeartbeat();
    // initFilters();
    // initSearch();
    // initUpload();
    // initGeolocate();
    // initReport();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}
