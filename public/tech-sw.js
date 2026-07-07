/*
 * Service Worker — espace technicien Panora (CIBLE CI)
 *
 * Stratégie offline-first volontairement minimaliste :
 *   - cache-first sur les assets statiques (CSS Select2, fonts, images
 *     panneau /storage/) → ouverture instantanée hors-ligne
 *   - network-first sur les pages tech (/tech/...) avec fallback cache
 *     → l'utilisateur voit la dernière version connue si la 4G coupe
 *   - bypass sur les actions POST (upload photo, status, report) :
 *     ces actions DOIVENT atteindre le réseau, on ne les bufferise pas
 *     ici (l'UI gère les retries via toast).
 *
 * Versioning du cache : SW_VERSION dans le nom. Bump à chaque release
 * de la PWA pour purger les anciens caches. Pas d'auto-update aggressive
 * — on attend que le tech ferme/rouvre la PWA pour swap (skipWaiting()
 * désactivé pour éviter de couper un upload en cours).
 */

// IMPORTANT : à bumper à chaque release qui modifie tech-app.js, ses
// imports ESM (core/*.js, features/*.js) ou tech-space.blade.php. Sans
// ça, le SW resert les versions cachées et le tech ne voit pas les
// nouveautés (cf. règle #4 de la table fetch ci-dessous : stale-while-
// revalidate sur tous les scripts).
//   - v1.0.0  : 1re version PWA tech (déploiement initial juin 2026)
//   - v1.5.0  : refonte SM1.5 — 14 modules ESM, 0 JS inline
//   - v2.0.0  : refonte SM2a — 9 écrans T1-T9 + CSS extrait + 18 modules JS
//   - v2.1.0  : SM2c — écrans bonus B1/B2/B3 + préférences + sm2c.css
//   - v2.2.0  : hotfix SM2a — modale aide (5 bugs critiques) + KPIs retirés
//   - v2.3.0  : refonte radicale tech-space — squelette propre + pose-card compact
//   - v2.4.0  : 3 bugs critiques post-refonte (upload undefined / card rotation / off-schedule humain)
//   - v2.5.0  : fix PHP non-évalué dans _js_config (ssrCap) qui cassait window.TECH_CONFIG
//   - v2.6.0  : diagnostic GPS amélioré côté modale T3 (motifs d'échec)
//   - v2.7.0  : refonte focus card → badge MAINTENANT dans liste, halo violet
//               pose en cours, paliers de progression 5 niveaux (drawer T2),
//               fix Ouvrir Google Maps + fallback commune/nom si pas de GPS
//   - v2.7.1  : labels paliers alignés avec le réel (En route/Arrivé/Collage/Fini)
//               + arrived_at posé au 50%, SLA temps de pose exclut le trajet
//   - v2.7.2  : progression AUTORISE la régression (correction saisie),
//               responsivité modals mobile (dvh + overflow-y auto)
//   - v2.7.3  : bandeau signalement TOUJOURS injecté après report + fond amber
//               plus visible (le tech voit clairement "j'ai déjà signalé")
//   - v2.7.4  : fix rectangle blanc opaque qui masquait le contenu des lignes
//               is-next / en_route / en_cours (gradient blanc pur à 40-60%)
//   - v2.7.5  : bandeau signalement avec styles INLINE (indépendant du cache
//               CSS SW qui peut servir une version périmée). Toujours amber.
//   - v2.8.0  : REFONTE progression → 3 boutons chronologiques (En route / Je
//               suis arrivé / Photo) + chrono en direct dans le drawer T2.
//               Retrait de la barre de 5 paliers. Admin voit "Sur place
//               depuis X min" en temps réel.
const SW_VERSION = 'v2.8.0';
const STATIC_CACHE  = `panora-tech-static-${SW_VERSION}`;
const RUNTIME_CACHE = `panora-tech-runtime-${SW_VERSION}`;
const PAGES_CACHE   = `panora-tech-pages-${SW_VERSION}`;

// Pré-cache minimal au install (sans la page tech elle-même : on la
// cache au premier hit en mode network-first).
const PRECACHE_URLS = [
    '/images/panora.png',
    '/images/favicond.png',
    '/images/panel-placeholder.svg',
    '/tech.webmanifest',
    'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
];

// ─── Install : pré-cache + activate immédiat ────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS).catch(() => {
                // Ne pas faire échouer l'install si un CDN refuse :
                // les ressources se cacheront au runtime.
            }))
    );
    // skipWaiting volontairement DÉSACTIVÉ — on ne veut pas couper un
    // upload en cours. Le nouveau SW prendra effet au prochain reload.
});

// ─── Activate : purge des anciens caches ────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k.startsWith('panora-tech-') && !k.endsWith(SW_VERSION))
                    .map((k) => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ─── Fetch : routing par type de requête ────────────────────────────
self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Ne JAMAIS intercepter les actions mutantes : photo, status, report.
    // Ces requêtes DOIVENT atteindre le serveur, le buffering offline
    // ouvre trop de bugs (envoi en double, photo corrompue, etc.).
    if (req.method !== 'GET') {
        return; // passe-plat navigateur
    }

    const url = new URL(req.url);

    // 1) Pages tech (/tech/{token}/poses, /piges, /poses/route-sheet) :
    //    network-first avec fallback cache → le tech voit toujours la
    //    dernière version connue si offline.
    if (url.pathname.startsWith('/tech/') && req.destination === 'document') {
        event.respondWith(networkFirst(req, PAGES_CACHE));
        return;
    }

    // 2) Heartbeat (GET JSON) : network-only, pas de cache (on veut le
    //    vrai compteur live). Si pas de réseau, fail silently → l'UI
    //    affiche le dernier état SSR.
    if (url.pathname.includes('/heartbeat')) {
        return;
    }

    // 3) Photos panneau /storage/, fonts CDN, Select2 : cache-first
    //    avec MAJ en arrière-plan (stale-while-revalidate).
    if (url.pathname.startsWith('/storage/')
        || url.hostname.includes('cdnjs.cloudflare.com')
        || url.hostname.includes('fonts.googleapis.com')
        || url.hostname.includes('fonts.gstatic.com')) {
        event.respondWith(staleWhileRevalidate(req, RUNTIME_CACHE));
        return;
    }

    // 4) Assets statiques de l'app (CSS, JS, images) : cache-first.
    if (req.destination === 'script' || req.destination === 'style'
        || req.destination === 'image' || req.destination === 'font') {
        event.respondWith(staleWhileRevalidate(req, STATIC_CACHE));
        return;
    }

    // 5) Search endpoint (Select2 AJAX) : network-first, fallback cache.
    //    Permet au tech de retrouver les dernières recherches même
    //    hors-ligne (utile pour repérer un panneau précis).
    if (url.pathname.includes('/poses/search')) {
        event.respondWith(networkFirst(req, RUNTIME_CACHE));
        return;
    }

    // Fallback : passe-plat sans cache.
});

// ─── Stratégies ──────────────────────────────────────────────────────

async function networkFirst(request, cacheName) {
    try {
        const fresh = await fetch(request);
        if (fresh && fresh.status === 200) {
            const cache = await caches.open(cacheName);
            cache.put(request, fresh.clone()).catch(() => {});
        }
        return fresh;
    } catch (err) {
        const cache = await caches.open(cacheName);
        const cached = await cache.match(request);
        if (cached) return cached;
        // Page offline générique si rien en cache
        return new Response(
            '<!DOCTYPE html><meta charset="utf-8"><title>Hors ligne</title>'
            + '<style>body{font-family:system-ui;padding:40px;text-align:center;color:#374151}'
            + 'h1{color:#c2570d}</style>'
            + '<h1>📵 Hors ligne</h1><p>Connecte-toi à Internet pour charger l\'espace technicien.</p>'
            + '<p><a href="javascript:location.reload()" style="color:#c2570d;font-weight:700">Réessayer</a></p>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
        );
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const fetchPromise = fetch(request)
        .then((fresh) => {
            if (fresh && fresh.status === 200) {
                cache.put(request, fresh.clone()).catch(() => {});
            }
            return fresh;
        })
        .catch(() => cached); // si réseau down, on garde cached
    return cached || fetchPromise;
}

// ─── Sync message : la page peut demander un purge cache (dev) ──────
self.addEventListener('message', (event) => {
    if (event.data?.type === 'PURGE_CACHE') {
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('panora-tech-')).map((k) => caches.delete(k))))
            .then(() => event.ports[0]?.postMessage({ ok: true }));
    }
});
