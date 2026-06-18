// public/js/tech/core/offline.js — Phase 3 SM1, finalisé SM1.5 Lot 6.
//
// Détection online/offline + déclenchement de flushUploadQueue() au retour
// du réseau. Code source : bloc 15 du <script> inline (lignes 1496-1507).
//
// Depuis SM1.5 Lot 6 : import nommé de flushUploadQueue (plus de
// window.flushUploadQueue legacy).

import { flushUploadQueue } from '../features/upload.js';

const offlineBannerId = 'ts-offline-banner';

function updateOfflineState() {
    const banner = document.getElementById(offlineBannerId);
    if (banner) {
        if (navigator.onLine === false) banner.classList.add('show');
        else banner.classList.remove('show');
    }
    if (navigator.onLine !== false) flushUploadQueue();
}

export function init() {
    window.addEventListener('online',  updateOfflineState);
    window.addEventListener('offline', updateOfflineState);
    updateOfflineState();
}
