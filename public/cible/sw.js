/*!
 * CIBLE — service worker : « réseau d'abord », avec une copie locale en secours.
 * Les pages et ressources consultées restent lisibles hors ligne ; le contenu
 * affiché est toujours le plus récent dès que le réseau répond.
 */
var VERSION = 'cible-v3-2026-10-12-couleurs-2';
var HORS_LIGNE = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hors ligne — CIBLE</title></head>' +
  '<body style="margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#E20613;color:#fff;font-family:system-ui,sans-serif;text-align:center">' +
  '<div><p style="margin:0;font-size:64px;font-weight:900;letter-spacing:-2px">CIBLE</p><h1 style="font-size:24px">Vous êtes hors ligne.</h1><p style="margin:0 0 8px;font-weight:800">Vous visez juste.</p>' +
  '<p>Cette page n’a pas encore été consultée sur cet appareil.<br>Appelez-nous&nbsp;: <a href="tel:+2250798496674" style="color:#fff;font-weight:800">+225 07 98 49 66 74</a></p></div></body></html>';
var ESSENTIELS = [
  './', 'index.html', 'qui-sommes-nous.html', 'expertises.html', 'affichage.html', 'digital.html',
  'reseau.html', 'travaux.html', 'contact.html', 'confidentialite.html', '404.html',
  'assets/css/cible.css', 'assets/js/data/site.js', 'assets/js/layout.js', 'assets/js/app.js',
  'assets/js/pages/accueil.js', 'assets/js/pages/pole.js', 'assets/js/pages/reseau.js', 'assets/js/pages/travaux.js', 'assets/js/pages/contact.js',
  'assets/fonts/poppins-800-latin.woff2', 'assets/fonts/poppins-600-latin.woff2', 'assets/fonts/nunito-400-800-latin.woff2',
  'assets/img/marque/logo-couleur.png', 'assets/img/marque/logo-fond-noir.png', 'assets/img/marque/motif.svg', 'assets/img/marque/nuage.svg',
  'assets/img/og/poster-hero-1600.webp', 'assets/img/og/poster-hero-780.webp'
];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(VERSION)
    .then(function (c) { return Promise.all(ESSENTIELS.map(function (u) { return c.add(u).catch(function () { /* ressource absente : ignorée */ }); })); })
    .then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys()
    .then(function (cles) { return Promise.all(cles.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); })); })
    .then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
  var r = e.request;
  // Vidéo (requêtes partielles) et ressources externes (fonds de carte) : laissées au navigateur.
  if (r.method !== 'GET' || r.headers.has('range')) return;
  if (new URL(r.url).origin !== self.location.origin) return;
  e.respondWith(fetch(r).then(function (rep) {
    if (rep.ok && rep.type === 'basic') {
      var copie = rep.clone();
      caches.open(VERSION).then(function (c) { c.put(r, copie); });
    }
    return rep;
  }).catch(function () {
    return caches.match(r, { ignoreSearch: r.mode === 'navigate' }).then(function (m) {
      if (m) return m;
      if (r.mode === 'navigate') return new Response(HORS_LIGNE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
      return Response.error();
    });
  }));
});
