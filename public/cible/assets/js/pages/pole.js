/*!
 * CIBLE — pages des expertises : vitrine 3D de l'en-tête (régie, mobile, brand experience), comparateur
 * de formats à l'échelle et réalisations liées.
 * Le contenu est généré ici (avant app.js) ; les comportements qui ont besoin
 * de CibleApp (3D, fiche) s'exécutent au clic ou au chargement.
 */
(function () {
  'use strict';
  var D = window.CIBLE;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ico = function (id) { return '<svg aria-hidden="true"><use href="#' + id + '"/></svg>'; };
  var reduit = matchMedia('(prefers-reduced-motion: reduce)');
  var poleParId = {};
  D.poles.forEach(function (p) { poleParId[p.id] = p; });
  // Lien vers le brief, prérempli avec le service et le sujet consultés.
  function consulter(service, sujet) {
    return 'contact.html?service=' + encodeURIComponent(service) + '&sujet=' + encodeURIComponent(sujet) + '#brief';
  }
  var SILHOUETTE = '<svg viewBox="0 0 7 13" aria-hidden="true"><circle cx="3.5" cy="1.6" r="1.6" fill="currentColor"/><path d="M1.2 4.2h4.6l-.6 4.6H4.6L4.3 13H2.7l-.3-4.2H1.8z" fill="currentColor"/></svg>';

  /* ==================================================================== */
  /* Vitrine 3D de l'en-tête                                              */
  /* ==================================================================== */
  function dispositifsDu(pole) {
    return D.dispositifs.filter(function (d) { return d.pole === pole; }).map(function (d) { return { id: d.id, n: d.n, nom: d.nom, cat: d.cat, alt: d.alt, fiche: true }; });
  }
  var VITRINES = {
    ooh: dispositifsDu('ooh'),
    mobile: dispositifsDu('mobile'),
    // Brand experience : savoir-faire mis en scène en 3D ; le bouton mène au brief prérempli.
    experience: [
      { id: 'stand', n: '01', nom: 'Architecture événementielle', cat: 'Stands et espaces de marque', alt: 'Stand de marque avec comptoir, kakémonos et visiteurs', service: 'experience' },
      { id: 'roadshows', n: '02', nom: 'Activations et expériences', cat: 'Roadshows, lancements, animations', alt: 'Camion podium avec scène dépliée et public', service: 'experience' },
      { id: 'studio', n: '03', nom: 'Production audiovisuelle', cat: 'Films, spots, capsules', alt: 'Studio de tournage avec caméra et éclairage', service: 'experience' }
    ]
  };

  $$('[data-vitrine]').forEach(function (el) {
    var items = VITRINES[el.getAttribute('data-vitrine')];
    if (!items) return;
    var depart = el.getAttribute('data-vitrine-depart'), i0 = Math.max(0, items.findIndex(function (x) { return x.id === depart; }));
    var img = $('.vitrine__img', el), canvas = $('.vitrine__canvas', el), choix = $('.vitrine__choix', el);
    var num = $('[data-vitrine-num]', el), nom = $('[data-vitrine-nom]', el), cat = $('[data-vitrine-cat]', el);
    var bouton3d = $('[data-vitrine-3d]', el), aide = $('[data-vitrine-aide]', el);
    var couleur = poleParId[el.getAttribute('data-vitrine')] ? poleParId[el.getAttribute('data-vitrine')].couleur : 'var(--rouge)';
    var cur = i0, viewer = null, visible = true, essai = false;

    choix.innerHTML = items.map(function (it, i) {
      return '<button type="button" aria-pressed="' + (i === cur) + '" data-i="' + i + '"><span class="puce" style="--c:' + couleur + '"></span>' + esc(it.nom) + '</button>';
    }).join('');

    function regler(i, depuisClic) {
      var it = items[i];
      cur = i;
      $$('button', choix).forEach(function (b) { b.setAttribute('aria-pressed', String(Number(b.getAttribute('data-i')) === i)); });
      num.textContent = it.n;
      nom.textContent = it.nom;
      cat.textContent = it.cat;
      img.src = 'assets/img/rendus/' + it.id + '.webp';
      img.alt = it.alt || it.nom;
      // Dispositif : sa fiche 3D ; savoir-faire : le brief prérempli.
      $('[data-vitrine-action]', el).outerHTML = it.fiche
        ? '<button class="btn btn--rouge btn--sm" type="button" data-vitrine-action data-fiche-id="' + it.id + '" aria-label="Ouvrir la fiche « ' + esc(it.nom) + ' »">Voir la fiche' + ico('i-fleche') + '</button>'
        : '<a class="btn btn--rouge btn--sm" data-vitrine-action href="' + consulter(it.service, it.nom) + '">Nous consulter' + ico('i-fleche') + '</a>';
      if (viewer) viewer.show(it.id);
      if (depuisClic && viewer && visible) viewer.start();
    }
    choix.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-i]');
      if (b) regler(Number(b.getAttribute('data-i')), true);
    });
    regler(cur);

    function enCours() { return visible && !document.hidden && !document.documentElement.classList.contains('is-locked'); }
    function piloter() { if (!viewer) return; if (enCours()) viewer.start(); else viewer.stop(); }
    function activer3D() {
      if (essai) return;
      essai = true;
      if (bouton3d) { bouton3d.disabled = true; bouton3d.lastChild.textContent = 'Chargement…'; }
      window.CibleApp.charger3D().then(function (M) {
        viewer = M.createViewer(canvas);
        if (!viewer) throw new Error('WebGL indisponible');
        viewer.show(items[cur].id);
        piloter();
        requestAnimationFrame(function () { el.classList.add('has-3d'); });
        if (bouton3d) bouton3d.hidden = true;
        if (aide) aide.hidden = false;
      }).catch(function () {
        if (bouton3d) bouton3d.hidden = true;
      });
    }
    if ('IntersectionObserver' in window) new IntersectionObserver(function (e) { visible = e[0].isIntersecting; piloter(); }).observe(el);
    document.addEventListener('visibilitychange', piloter);
    new MutationObserver(piloter).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', function () { if (viewer) viewer.resize(); });
    if (bouton3d) bouton3d.addEventListener('click', activer3D);

    window.addEventListener('load', function () {
      var A = window.CibleApp;
      if (!A || !A.charger3D) return;
      // Sur mobile, en mouvement réduit ou en connexion sobre : la 3D attend un geste.
      if (A.leger() || reduit.matches) { if (bouton3d) bouton3d.hidden = false; return; }
      if ('requestIdleCallback' in window) requestIdleCallback(activer3D, { timeout: 1500 });
      else setTimeout(activer3D, 300);
    });
  });

  /* ==================================================================== */
  /* Comparateur de formats (panneaux classiques)                         */
  /* ==================================================================== */
  $$('[data-comparateur]').forEach(function (el) {
    var liste = $('[data-tailles]', el), img = $('[data-comp-img]', el);
    var ech = 6.3; // px par mètre, identique pour tous les formats
    var cur = 'classique';
    liste.innerHTML = D.formats.map(function (f) {
      return '<li><button class="taille" type="button" data-taille="' + f.id + '" aria-pressed="' + (f.id === cur) + '">' +
        '<span class="taille__echelle" aria-hidden="true">' + SILHOUETTE + '<i style="width:' + (f.w * ech).toFixed(1) + 'px;height:' + (f.h * ech).toFixed(1) + 'px"></i></span>' +
        '<span><strong>' + esc(f.nom) + '</strong><span>' + esc(f.dim) + '</span></span>' +
        '<span class="taille__surface">' + esc(f.surface) + '</span></button></li>';
    }).join('');
    function regler(id) {
      var f = D.formats.find(function (x) { return x.id === id; });
      if (!f) return;
      cur = id;
      $$('[data-taille]', el).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-taille') === id)); });
      $('[data-comp-nom]', el).textContent = f.nom + ' · ' + f.dim;
      $('[data-comp-surface]', el).textContent = f.surface + ' de surface d’affichage';
      img.classList.add('is-change');
      setTimeout(function () {
        img.src = 'assets/img/rendus/panneaux-classiques' + (id === 'classique' ? '' : '--' + id) + '.webp';
        img.alt = 'Panneau ' + f.nom.toLowerCase() + ' (' + f.dim + ') au bord d’une voie, sur une île flottante';
        img.classList.remove('is-change');
      }, reduit.matches ? 0 : 180);
      $('[data-comp-3d]', el).setAttribute('data-fiche-format', id);
      $('[data-comp-contact]', el).href = consulter('ooh', 'Dispositif : Panneaux classiques — ' + f.nom + ' ' + f.dim);
    }
    liste.addEventListener('click', function (e) {
      var b = e.target.closest('[data-taille]');
      if (b) regler(b.getAttribute('data-taille'));
    });
    regler(cur);
  });

  /* ==================================================================== */
  /* Réalisations liées                                                   */
  /* ==================================================================== */
  $$('[data-realisations-liees]').forEach(function (el) {
    var filtres = el.getAttribute('data-realisations-liees').split(',');
    el.innerHTML = D.realisations.filter(function (r) { return r.filtres.some(function (f) { return filtres.indexOf(f) >= 0; }); }).slice(0, 3).map(function (r, i) {
      return '<a class="projet" href="travaux.html#' + r.slug + '" data-reveal style="--d:' + (i * 0.08) + 's">' +
        '<span class="projet__img"><img src="' + r.image + '" alt="' + esc(r.cat + ' — ' + r.nom) + '" loading="lazy"><span class="projet__marque">' + esc(r.nom) + '</span><span class="credit-photo">' + esc(r.credit) + '</span></span>' +
        '<span class="projet__corps"><span class="projet__cat">' + esc(r.cat) + '</span><strong class="projet__titre">' + esc(r.titre) + '</strong>' +
        '<span class="projet__services">' + r.services.map(esc).join(' · ') + '</span></span></a>';
    }).join('');
  });

})();
