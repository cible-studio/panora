// public/js/tech/core/api.js — Phase 3 SM1.
//
// Helpers fetch wrappés (CSRF + X-Requested-With + Accept JSON). Centralise
// la construction des requêtes vers le backend tech.space.* pour que les
// features n'aient plus à se soucier des headers d'auth.
//
// Aucune dépendance externe — vanilla JS. Module ES classique.

/**
 * Header CSRF source de vérité. Lu UNE FOIS au module load car le token
 * Laravel reste stable tant que la session ne tourne pas. Fallback sur le
 * meta tag historique si window.TECH_CONFIG n'est pas chargé (cas extrême
 * où ce module serait inclus dans un contexte sans config).
 */
function getCsrf() {
    if (window.TECH_CONFIG?.csrfToken) return window.TECH_CONFIG.csrfToken;
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/**
 * Remplace le placeholder __TASK__ dans une route template par l'ID réel.
 * Cohérent avec _js_config.blade.php (statusTpl, photoTpl, reportTpl).
 */
export function urlForTask(template, taskId) {
    return String(template).replace('__TASK__', String(taskId));
}

/**
 * POST JSON simple. Renvoie l'objet Response brut pour que l'appelant puisse
 * inspecter status + body. Throws sur erreur réseau (le fallback offline
 * queue est géré côté upload.js, pas ici).
 */
export async function postJson(url, body = null) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrf(),
    };
    if (body !== null && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    }
    return fetch(url, { method: 'POST', headers, body, credentials: 'same-origin' });
}

/**
 * GET JSON simple. Renvoie l'objet Response brut.
 */
export async function getJson(url) {
    return fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
}
