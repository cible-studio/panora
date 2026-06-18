// public/js/tech/core/sw-register.js — Phase 3 SM1.
//
// Enregistre le Service Worker /tech-sw.js (cf. asset() côté Blade) avec
// scope racine. Détecte les MAJ disponibles SANS les activer automatiquement
// (cohérent avec la logique pré-refonte : la nouvelle version prend effet
// au prochain cold start de la PWA).
//
// Code source : bloc 14 du <script> inline (lignes 1483-1494 de
// tech-space.blade.php avant Phase 3).
//
// Init contrôlée depuis tech-app.js — pas d'auto-init au load.

let swUrl = '/tech-sw.js';

/**
 * Surcharge l'URL du SW (utilisé si Laravel sert le fichier via une autre
 * route que /tech-sw.js — pour l'instant le Blade utilise asset('tech-sw.js')
 * qui produit /tech-sw.js).
 */
export function setSwUrl(url) {
    if (typeof url === 'string' && url.length > 0) swUrl = url;
}

export function init() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.register(swUrl, { scope: '/' })
        .then(reg => {
            reg.addEventListener('updatefound', () => {
                /* nouvelle version en cours d'install — prendra effet
                   au prochain cold start de la PWA */
            });
        })
        .catch(() => { /* échec silencieux : pas critique */ });
}
