// public/js/tech/tech-app.js — État final SM1.5.
//
// 100 % des features du tech-space sont migrées en modules ES (lots 1-6).
// Aucun <script> inline restant côté tech-space.blade.php.
//
// Pas de bundler — chargé via <script type="module"> côté Blade. Les
// imports relatifs './core/x.js' fonctionnent nativement (ESM).
//
// Précondition unique : window.TECH_CONFIG publié AVANT ce module (cf.
// partial resources/views/public/tech/partials/_js_config.blade.php).
//
// Note d'architecture : core/api.js, core/state.js, core/ui-helpers.js
// sont des modules UTILITAIRES (helpers + état partagé) consommés à la
// demande par les features. Pas de init() — pas d'appel bootstrap.

import { init as initOffline }     from './core/offline.js';
import { init as initSwRegister }  from './core/sw-register.js';
import { init as initHeartbeat }   from './features/heartbeat.js';
import { init as initPwaInstall }  from './features/pwa-install.js';
import { init as initReport }      from './features/report.js';
import { init as initStatusChanges } from './features/status-changes.js';
import { init as initFilters }     from './features/filters.js';
import { init as initSearch }      from './features/search.js';
import { init as initGeolocate }   from './features/geolocate.js';
import { init as initUpload }      from './features/upload.js';
import { init as initPoseDrawer }  from './features/pose-drawer.js';
import { init as initYAllerModal } from './features/y-aller-modal.js';

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
    initReport();         // [SM1.5 Lot 1] modale signalement 9 motifs
    initStatusChanges();  // [SM1.5 Lot 2] Y aller / J'y suis / statut générique
    initFilters();        // [SM1.5 Lot 5] chips + KPI + zone + clear + restore URL
    initSearch();         // [SM1.5 Lot 3] Select2 AJAX paginé + openFocusModal
    initGeolocate();      // [SM1.5 Lot 4] Près de moi + Mon chemin (TSP)
    initUpload();         // [SM1.5 Lot 6] photo : preview + GPS + compress + POST + IndexedDB queue
    initPoseDrawer();     // [SM2a Lot 2.1A] drawer T2 détail d'une pose + ?focus= deep-link
    initYAllerModal();    // [SM2a Lot 2.2] modale T7 confirmation Y aller (mini-carte + stats)
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}
