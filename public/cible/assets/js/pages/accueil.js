/*!
 * CIBLE — page d'accueil : ouverture 3D en trois chapitres et réalisations
 * (cartes d'expertise et constellation du réseau générées par app.js).
 */
(function () {
  'use strict';
  var D = window.CIBLE;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ico = function (id) { return '<svg aria-hidden="true"><use href="#' + id + '"/></svg>'; };
  var clamp = function (x, a, b) { return Math.min(b, Math.max(a, x)); };
  var smooth = function (a, b, x) { var t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); };

  /* ---------- Contenus générés depuis les données ---------- */
  var projets = $('[data-projets]');
  if (projets) {
    // Trois réalisations représentatives ; les autres sont sur la page Réalisations.
    var choix = ['snedai', 'sgs-sicta', 'orange'];
    projets.innerHTML = D.realisations.filter(function (r) { return choix.indexOf(r.slug) >= 0; }).map(function (r, i) {
      return '<a class="projet" href="travaux.html#' + r.slug + '" data-reveal style="--d:' + ((i % 3) * 0.08) + 's">' +
        '<span class="projet__img"><img src="' + r.image + '" alt="' + esc(r.cat + ' — ' + r.nom) + '" loading="lazy"><span class="projet__marque">' + esc(r.nom) + '</span><span class="credit-photo">' + esc(r.credit) + '</span></span>' +
        '<span class="projet__corps"><span class="projet__cat">' + esc(r.cat) + '</span><strong class="projet__titre">' + esc(r.titre) + '</strong>' +
        '<span class="projet__services">' + r.services.map(esc).join(' · ') + '</span></span></a>';
    }).join('');
  }

  /* ---------- Ouverture 3D ---------- */
  var intro = $('[data-intro]');
  if (!intro) return;
  var canvas = $('[data-hero-canvas]', intro);
  var panneaux = {};
  $$('[data-panneau]', intro).forEach(function (el) { panneaux[el.getAttribute('data-panneau')] = el; });
  var hero = null, tick = false, visible = true;
  function progression() {
    var total = intro.offsetHeight - window.innerHeight;
    return total > 0 ? clamp(-intro.getBoundingClientRect().top / total, 0, 1) : 0;
  }
  function regler(el, o, ty) {
    if (!el) return;
    el.style.setProperty('--o', o.toFixed(3));
    el.style.setProperty('--ty', ty.toFixed(1) + 'px');
    el.style.visibility = o < 0.01 ? 'hidden' : 'visible';
    el.setAttribute('aria-hidden', String(o < 0.5));
  }
  function maj() {
    tick = false;
    var p = progression();
    if (hero) hero.setProgress(p);
    var a = 1 - smooth(0.03, 0.15, p), b = smooth(0.33, 0.44, p) * (1 - smooth(0.58, 0.66, p)), c = smooth(0.85, 0.95, p);
    regler(panneaux.hero, a, (1 - a) * -70);
    regler(panneaux.indice, a, 0);
    regler(panneaux.claim, b, (1 - b) * (p < 0.5 ? 40 : -40));
    regler(panneaux.suite, c, (1 - c) * 40);
    intro.style.setProperty('--p', p.toFixed(3));
    document.documentElement.classList.toggle('en-intro', p < 0.1);
  }
  window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(maj); } }, { passive: true });
  window.addEventListener('resize', function () { if (hero) hero.resize(); maj(); });
  maj();
  if (matchMedia('(pointer: fine)').matches) {
    window.addEventListener('pointermove', function (e) { if (hero) hero.setPointer(e.clientX / window.innerWidth * 2 - 1, e.clientY / window.innerHeight * 2 - 1); }, { passive: true });
  }
  function activer() { if (hero) hero.setActive(visible && !document.hidden); }
  if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { visible = e[0].isIntersecting; activer(); }).observe(intro);
  document.addEventListener('visibilitychange', activer);

  function demarrer3D() {
    if (!window.CibleApp || !CibleApp.charger3D) return;
    CibleApp.charger3D().then(function (M) {
      try { hero = M.createHero(canvas, { lowPower: CibleApp.leger(), reducedMotion: CibleApp.reduit.matches }); } catch (e) { hero = null; }
      if (!hero) return;
      hero.onFirstFrame(function () { document.documentElement.classList.add('has-3d'); });
      activer();
      maj();
    }).catch(function () { /* l'image d'attente reste en place */ });
  }
  window.addEventListener('load', function () {
    if ('requestIdleCallback' in window) requestIdleCallback(demarrer3D, { timeout: 1200 });
    else setTimeout(demarrer3D, 200);
  });
})();
