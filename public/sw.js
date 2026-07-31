/**
 * Panora — Service Worker
 *
 * Rôle minimal :
 *   1. Rendre l'app installable (le simple fait d'enregistrer un SW +
 *      manifest valide déclenche l'invite « Ajouter à l'écran d'accueil »).
 *   2. Cache "app shell" ultra-léger pour l'installabilité + page offline.
 *   3. Aucune interception agressive : chaque requête va au réseau en
 *      priorité (Panora est une app dynamique — pas d'assets figés à mettre
 *      en cache long). Si le réseau échoue sur une navigation HTML, on
 *      sert /offline.html au lieu du "Dinosaure Chrome".
 *
 * ATTENTION métier : ne JAMAIS mettre en cache les réponses JSON/POST des
 * routes admin (/admin/*, /api/*) — les montants FCFA, taxes et statuts
 * doivent toujours refléter la base. Cf. RÈGLE N°5 CLAUDE.md (calculs
 * financiers = source unique).
 */

const VERSION = 'panora-sw-v1';
const APP_SHELL = [
  '/offline.html',
  '/manifest.webmanifest',
  '/images/pwa-192.png',
  '/images/pwa-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION).then((cache) => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Réseau seulement pour les requêtes non-GET et les endpoints métier.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Ne jamais cacher : admin, API, auth, exports, POST-back éventuels.
  if (
    url.pathname.startsWith('/admin') ||
    url.pathname.startsWith('/api')   ||
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/logout') ||
    url.pathname.includes('/export')  ||
    url.pathname.includes('/pdf')
  ) {
    return; // laisse le navigateur gérer normalement
  }

  // Pour les navigations HTML (top-level), network-first avec fallback offline.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Assets statiques (images, css, js) : cache-first léger.
  if (['image', 'style', 'script', 'font'].includes(req.destination)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        return (
          cached ||
          fetch(req).then((res) => {
            // ne cache que les 200 basic (skip opaques CDN)
            if (res && res.status === 200 && res.type === 'basic') {
              const copy = res.clone();
              caches.open(VERSION).then((c) => c.put(req, copy));
            }
            return res;
          })
        );
      })
    );
  }
});
