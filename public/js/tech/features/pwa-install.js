// public/js/tech/features/pwa-install.js — Phase 3 SM1.
//
// Capture l'événement 'beforeinstallprompt' (Chrome/Edge/Android) pour
// pouvoir le re-déclencher plus tard depuis un bouton custom. Pour SM1,
// pas de bouton "Installer" exposé — le navigateur affiche son propre
// prompt natif au load.
//
// Note : le manifest tech.webmanifest + les meta tags Apple touch sont
// déjà publiés via le partial _pwa_install.blade.php côté <head>. Ce
// module JS ne gère QUE le hook d'installation programmatique.
//
// Source historique : aucun bloc équivalent dans le <script> inline pré-
// refonte (cette feature n'existait pas — module créé proactivement pour
// suivre l'architecture du brief Phase 3).

let deferredPrompt = null;

function onBeforeInstallPrompt(e) {
    e.preventDefault();
    deferredPrompt = e;
    // Exposé sur window pour qu'un bouton custom puisse appeler installPrompt()
    // depuis n'importe quel autre contexte (cf. SM2 si besoin).
    window.installPrompt = installPrompt;
}

async function installPrompt() {
    if (!deferredPrompt) return false;
    deferredPrompt.prompt();
    try {
        const choice = await deferredPrompt.userChoice;
        deferredPrompt = null;
        return choice?.outcome === 'accepted';
    } catch (e) {
        return false;
    }
}

export function init() {
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
}
