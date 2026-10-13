/*!
 * CIBLE — page d'accueil : ouverture 3D en trois chapitres, réalisations et
 * constellation du réseau (les cartes d'expertise sont générées par app.js).
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
    // Trois réalisations représentatives des deux expertises ; les autres sont sur la page Réalisations.
    var choix = ['snedai', 'sgs-sicta', 'orange'];
    projets.innerHTML = D.realisations.filter(function (r) { return choix.indexOf(r.slug) >= 0; }).map(function (r, i) {
      return '<a class="projet" href="travaux.html#' + r.slug + '" data-reveal style="--d:' + ((i % 3) * 0.08) + 's">' +
        '<span class="projet__img"><img src="' + r.image + '" alt="' + esc(r.cat + ' — ' + r.nom) + '" loading="lazy"><span class="projet__marque">' + esc(r.nom) + '</span><span class="credit-photo">' + esc(r.credit) + '</span></span>' +
        '<span class="projet__corps"><span class="projet__cat">' + esc(r.cat) + '</span><strong class="projet__titre">' + esc(r.titre) + '</strong>' +
        '<span class="projet__services">' + r.services.map(esc).join(' · ') + '</span></span></a>';
    }).join('');
  }

  /* ---------- Constellation du réseau (projection simple lat/lng) ---------- */
  var conste = $('[data-constellation]');
  if (conste) {
    var W = 600, H = 700, lng0 = -8.1, lng1 = -2.4, lat0 = 4.4, lat1 = 10.1;
    var px = function (lng) { return 40 + (lng - lng0) / (lng1 - lng0) * (W - 80); };
    var py = function (lat) { return 40 + (lat1 - lat) / (lat1 - lat0) * 560; };
    // côté de l'étiquette imposé là où les noms se chevaucheraient
    var cote = { Korhogo: 'g', Dabou: 'g', Bondoukou: 'g', Aboisso: 'd' };
    var abj = D.communes.filter(function (c) { return c[1] === 'abidjan'; }), int = D.communes.filter(function (c) { return c[1] === 'interieur'; });
    var ax = px(-4.02), ay = py(5.34);
    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Carte schématique : Grand Abidjan et 16 villes de l’intérieur">';
    for (var g = 0; g <= 6; g++) { svg += '<line x1="40" x2="' + (W - 40) + '" y1="' + (40 + g * 560 / 6) + '" y2="' + (40 + g * 560 / 6) + '" class="c-grille"/>'; }
    int.forEach(function (c) {
      var x = px(c[3]), y = py(c[2]), mx = (x + ax) / 2, my = Math.min(y, ay) - 40;
      svg += '<path class="c-route" d="M' + ax.toFixed(1) + ' ' + ay.toFixed(1) + ' Q' + mx.toFixed(1) + ' ' + my.toFixed(1) + ' ' + x.toFixed(1) + ' ' + y.toFixed(1) + '"/>';
    });
    int.forEach(function (c, i) {
      var x = px(c[3]), y = py(c[2]), gauche = cote[c[0]] ? cote[c[0]] === 'g' : x > W - 140;
      svg += '<g class="c-ville" style="--dl:' + (i * 0.06).toFixed(2) + 's"><circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="7"/><text x="' + (gauche ? x - 12 : x + 12).toFixed(1) + '" y="' + (y + 5).toFixed(1) + '" text-anchor="' + (gauche ? 'end' : 'start') + '">' + esc(c[0]) + '</text></g>';
    });
    // médaillon Grand Abidjan (en mer, sous la côte)
    var ix = 505, iy = 622, ir = 66, la0 = 5.22, la1 = 5.52, lo0 = -4.25, lo1 = -3.86;
    svg += '<line class="c-lien" x1="' + ax.toFixed(1) + '" y1="' + ay.toFixed(1) + '" x2="' + (ix - ir * 0.7).toFixed(1) + '" y2="' + (iy - ir * 0.7).toFixed(1) + '"/>';
    svg += '<circle class="c-medaillon" cx="' + ix + '" cy="' + iy + '" r="' + ir + '"/>';
    abj.forEach(function (c) {
      var x = ix - ir * 0.78 + (c[3] - lo0) / (lo1 - lo0) * ir * 1.56, y = iy - ir * 0.78 + (la1 - c[2]) / (la1 - la0) * ir * 1.56;
      svg += '<g class="c-commune"><circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="4.5"/><title>' + esc(c[0]) + '</title></g>';
    });
    svg += '<text class="c-titre" x="' + (ix - ir - 16) + '" y="' + (iy + 2) + '" text-anchor="end">Grand Abidjan<tspan x="' + (ix - ir - 16) + '" dy="18">15 zones</tspan></text>';
    svg += '<g class="c-abidjan"><circle class="c-onde" cx="' + ax.toFixed(1) + '" cy="' + ay.toFixed(1) + '" r="14"/><circle cx="' + ax.toFixed(1) + '" cy="' + ay.toFixed(1) + '" r="11"/><text x="' + (ax - 16).toFixed(1) + '" y="' + (ay + 32).toFixed(1) + '" text-anchor="end">Abidjan</text></g>';
    svg += '</svg>';
    conste.innerHTML = svg + '<figcaption><span><i class="puce" style="--c:var(--rouge)"></i>Grand Abidjan</span><span><i class="puce" style="--c:var(--jaune)"></i>Intérieur du pays</span><span>31 communes et villes</span></figcaption>';
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
