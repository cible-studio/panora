// public/js/tech/tech-app.js — État après SM1.
//
// Voir docs/TECHNICAL_DEBT.md "Refonte Espace Technicien — SM1.5"
// pour les modules encore dans le <script> inline de tech-space.blade.php.
//
// Pas de bundler — chargé via <script type="module"> côté Blade. Les
// imports relatifs './core/x.js' fonctionnent nativement (ESM).
//
// Préconditions :
//   1. window.TECH_CONFIG doit être publié AVANT ce module
//      (cf. partial resources/views/public/tech/partials/_js_config.blade.php).
//   2. Le <script> inline historique gère encore les features non migrées :
//      upload, filters, search, geolocate (distance + tournée), report,
//      changements de statut (Y aller / J'y suis / modale justifier).
//      Cf. TECHNICAL_DEBT.md pour le détail.
//
// Note d'architecture : core/api.js et core/state.js sont des modules
// UTILITAIRES (helpers + état partagé) consommés à la demande par les
// features. Ils n'ont pas de fonction init() — pas d'appel bootstrap
// nécessaire.

import { init as initOffline }     from './core/offline.js';
import { init as initSwRegister }  from './core/sw-register.js';
import { init as initHeartbeat }   from './features/heartbeat.js';
import { init as initPwaInstall }  from './features/pwa-install.js';
import { init as initReport }      from './features/report.js';

// Garde-fou : si TECH_CONFIG n'est pas là, on log mais on n'explose pas
// (la page continue de fonctionner via le JS inline encore présent).
if (!window.TECH_CONFIG) {
    console.warn('[tech-app] window.TECH_CONFIG absent — chargement du partial _js_config.blade.php a échoué ?');
}

function bootstrap() {
    // Modules activés en SM1 :
    initSwRegister();   // Service Worker registration
    initOffline();      // online/offline events + flush queue
    initHeartbeat();    // polling 20s + KPIs live + détection nouvelle pose
    initPwaInstall();   // capture beforeinstallprompt
    initReport();       // [SM1.5 Lot 1] modale signalement 9 motifs

    // NOTE : filters, search, upload, geolocate, status-changes restent
    // dans le <script> inline de tech-space.blade.php.
    // Migration en cours en SM1.5 (cf. docs/TECHNICAL_DEBT.md).
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}
