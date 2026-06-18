// public/js/tech/core/offline.js — Phase 3 SM1.
//
// Détection online/offline + déclenchement de flushUploadQueue() au retour
// du réseau. Code source : bloc 15 du <script> inline (lignes 1496-1507).
//
// flushUploadQueue() est défini dans features/upload.js — on l'invoque ici
// via window.flushUploadQueue (legacy global déjà publié par upload.js
// pour rétrocompat avec les anciens appels inline). Ce contrat reste
// valide tant que upload.js est chargé.

const offlineBannerId = 'ts-offline-banner';

function updateOfflineState() {
    const banner = document.getElementById(offlineBannerId);
    if (banner) {
        if (navigator.onLine === false) banner.classList.add('show');
        else banner.classList.remove('show');
    }
    // Retour online → on tente de rejouer la queue offline. La fonction
    // peut être absente si upload.js n'a pas (encore) été chargé — guard.
    if (navigator.onLine !== false && typeof window.flushUploadQueue === 'function') {
        window.flushUploadQueue();
    }
}

export function init() {
    window.addEventListener('online',  updateOfflineState);
    window.addEventListener('offline', updateOfflineState);
    updateOfflineState();
}
