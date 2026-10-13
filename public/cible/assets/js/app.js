/*!
 * CIBLE — comportements communs à toutes les pages :
 * navigation, fiche dispositif avec visionneuse 3D chargée à la demande (et lien
 * « Nous consulter » vers le brief prérempli), apparitions, compteurs, onglets, filtres.
 */
(function () {
  'use strict';

  var D = window.CIBLE;
  var root = document.documentElement;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var clamp = function (x, a, b) { return Math.min(b, Math.max(a, x)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ico = function (id) { return '<svg aria-hidden="true"><use href="#' + id + '"/></svg>'; };
  var reduit = matchMedia('(prefers-reduced-motion: reduce)');
  var dispoParId = {};
  D.dispositifs.forEach(function (d) { dispoParId[d.id] = d; });
  var poleParId = {};
  D.poles.forEach(function (p) { poleParId[p.id] = p; });
  var CibleApp = window.CibleApp = window.CibleApp || {};
  CibleApp.esc = esc; CibleApp.ico = ico; CibleApp.reduit = reduit;

  /* ==================================================================== */
  /* Calques : focus, inert, défilement bloqué                            */
  /* ==================================================================== */
  var calques = [];
  function fond() { return [$('main'), $('.hdr'), $('.ftr'), $('.skip'), $('.contact-rapide')]; }
  function verrou(on) {
    if (on) { var sb = window.innerWidth - root.clientWidth; root.style.paddingRight = sb > 0 ? sb + 'px' : ''; root.classList.add('is-locked'); }
    else { root.classList.remove('is-locked'); root.style.paddingRight = ''; }
  }
  function syncInert() {
    var haut = calques[calques.length - 1];
    fond().concat($('[data-fiche], [data-menu], [data-fenetre]')).forEach(function (el) {
      if (!el) return;
      var garder = haut && (el === haut.el || (haut.garder && haut.garder.indexOf(el) >= 0));
      if (haut && !garder) el.setAttribute('inert', ''); else el.removeAttribute('inert');
    });
  }
  function ouvrir(el, o) {
    o = o || {};
    if (calques.some(function (c) { return c.el === el; })) return;
    var c = { el: el, source: o.source || document.activeElement, fermeture: o.fermeture, garder: o.garder, extra: o.extra };
    calques.push(c);
    el.hidden = false; if (o.extra) o.extra.hidden = false;
    verrou(true); syncInert();
    requestAnimationFrame(function () { requestAnimationFrame(function () {
      el.classList.add('is-open'); if (o.extra) o.extra.classList.add('is-open');
      var cible = o.focus ? $(o.focus, el) : $('[data-fermer], button, a, input', el);
      if (cible) cible.focus({ preventScroll: true });
      if (o.ouverture) o.ouverture();
    }); });
  }
  function fermer(el, o) {
    o = o || {};
    var i = calques.findIndex(function (c) { return c.el === el; });
    if (i < 0) return;
    var c = calques.splice(i, 1)[0];
    el.classList.remove('is-open'); if (c.extra) c.extra.classList.remove('is-open');
    setTimeout(function () { if (!el.classList.contains('is-open')) { el.hidden = true; if (c.extra) c.extra.hidden = true; } }, reduit.matches ? 0 : 450);
    if (!calques.length) verrou(false);
    syncInert();
    if (c.fermeture) c.fermeture();
    if (!o.sansFocus && c.source && document.contains(c.source)) c.source.focus({ preventScroll: true });
  }
  function toutFermer() { calques.slice().reverse().forEach(function (c) { fermer(c.el, { sansFocus: true }); }); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && calques.length) { e.preventDefault(); fermer(calques[calques.length - 1].el); } });
  CibleApp.ouvrir = ouvrir; CibleApp.fermer = fermer; CibleApp.toutFermer = toutFermer;

  /* ==================================================================== */
  /* Message bref                                                         */
  /* ==================================================================== */
  var toastEl = $('[data-toast]'), toastT = 0;
  function toast(msg) {
    if (!toastEl) return;
    toastEl.innerHTML = ico('i-coche') + '<span>' + esc(msg) + '</span>';
    toastEl.classList.add('is-on');
    clearTimeout(toastT);
    toastT = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2800);
  }
  CibleApp.toast = toast;

  /* Lien vers le brief, prérempli avec le dispositif consulté */
  function lienContact(d, format) {
    var f = format && D.formats.find(function (x) { return x.id === format; });
    return 'contact.html?service=' + encodeURIComponent(d.pole || 'ooh') +
      '&sujet=' + encodeURIComponent('Dispositif : ' + d.nom + (f ? ' — ' + f.nom + ' ' + f.dim : '')) + '#brief';
  }
  CibleApp.lienContact = lienContact;

  /* ==================================================================== */
  /* Chargement de la 3D à la demande                                     */
  /* ==================================================================== */
  var promesse3D = null;
  function webglOk() {
    try { var c = document.createElement('canvas'); return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl'))); } catch (e) { return false; }
  }
  function sobre() {
    var n = navigator.connection || {};
    return !!(n.saveData || /(^|-)2g$/.test(n.effectiveType || '') || (navigator.deviceMemory && navigator.deviceMemory < 1));
  }
  function script(src) {
    return new Promise(function (ok, ko) {
      var s = document.createElement('script'); s.src = src; s.async = false;
      s.onload = ok; s.onerror = ko; document.head.appendChild(s);
    });
  }
  CibleApp.charger3D = function () {
    if (promesse3D) return promesse3D;
    if (sobre() || !webglOk()) { promesse3D = Promise.reject(new Error('3D désactivée')); promesse3D.catch(function () {}); return promesse3D; }
    promesse3D = (window.THREE ? Promise.resolve() : script('assets/vendor/three.min.js'))
      .then(function () { return window.CIBLE_MARQUE ? null : script('assets/js/3d/marque-data.js'); })
      .then(function () { return window.CIBLE_BAT ? null : script('assets/js/3d/bat-data.js'); })
      .then(function () { return window.CibleMonde ? null : script('assets/js/3d/monde.js'); })
      .then(function () { if (!window.CibleMonde) throw new Error('moteur 3D absent'); return window.CibleMonde.ready.then(function () { return window.CibleMonde; }); });
    promesse3D.catch(function () {});
    return promesse3D;
  };
  CibleApp.leger = function () { return (navigator.hardwareConcurrency || 8) <= 4 || matchMedia('(max-width: 760px)').matches; };

  /* ==================================================================== */
  /* En-tête : méga-menu, menu mobile                                     */
  /* ==================================================================== */
  (function entete() {
    var hdr = $('[data-hdr]');
    if (!hdr) return;
    var btn = $('[data-mega]'), mega = $('[data-mega-panneau]'), t = 0;
    function basculer(on) {
      if (!btn || !mega) return;
      btn.setAttribute('aria-expanded', String(on));
      mega.classList.toggle('is-open', on);
    }
    if (btn && mega) {
      btn.addEventListener('click', function () { basculer(btn.getAttribute('aria-expanded') !== 'true'); });
      if (matchMedia('(hover: hover)').matches) {
        [btn, mega].forEach(function (el) {
          el.addEventListener('mouseenter', function () { clearTimeout(t); basculer(true); });
          el.addEventListener('mouseleave', function () { t = setTimeout(function () { basculer(false); }, 220); });
        });
      }
      document.addEventListener('click', function (e) { if (!e.target.closest('[data-mega], [data-mega-panneau]')) basculer(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') { basculer(false); btn.focus(); } });
      mega.addEventListener('focusout', function (e) { if (!mega.contains(e.relatedTarget) && e.relatedTarget !== btn) basculer(false); });
    }
    var burger = $('[data-burger]'), menu = $('[data-menu]');
    function fermerMenu() { burger.setAttribute('aria-expanded', 'false'); fermer(menu, { sansFocus: true }); }
    if (burger && menu) {
      burger.addEventListener('click', function () {
        if (burger.getAttribute('aria-expanded') === 'true') { fermerMenu(); burger.focus(); return; }
        burger.setAttribute('aria-expanded', 'true');
        ouvrir(menu, { source: burger, garder: [hdr], focus: 'a', fermeture: function () { burger.setAttribute('aria-expanded', 'false'); } });
      });
      menu.addEventListener('click', function (e) { if (e.target.closest('a')) fermerMenu(); });
      matchMedia('(min-width: 1181px)').addEventListener('change', function (e) { if (e.matches && !menu.hidden) fermerMenu(); });
    }
  })();

  /* ==================================================================== */
  /* Fiche dispositif (visionneuse 3D)                                    */
  /* ==================================================================== */
  var fiche = $('[data-fiche]');
  var F = { d: null, format: null, viewer: null, essaye: false };
  function rendreFiche(d) {
    F.d = d; F.format = d.formats ? 'classique' : null;
    var pole = poleParId[d.pole];
    $('[data-fiche-cat]', fiche).textContent = (pole ? pole.nom + ' · ' : '') + d.cat;
    $('[data-fiche-titre]', fiche).textContent = d.nom;
    $('[data-fiche-court]', fiche).textContent = d.court;
    $('[data-fiche-texte]', fiche).textContent = d.texte || 'L’espace public est le seul média qu’on ne peut pas zapper. Nous concevons des campagnes qui se voient, se comprennent en trois secondes et se retiennent.';
    $('[data-fiche-num]', fiche).textContent = d.n;
    var specs = [['Expertise', pole ? pole.nom : '']];
    if (d.dim) specs.push(['Format', d.dim]);
    if (d.formats) specs.push(['Formats', 'Du petit format au panoramique']);
    specs.push(['Couverture', 'Abidjan & intérieur du pays']);
    specs.push(['Preuve', 'Pige photo horodatée']);
    $('[data-fiche-specs]', fiche).innerHTML = specs.map(function (s) { return '<div><dt>' + esc(s[0]) + '</dt><dd>' + esc(s[1]) + '</dd></div>'; }).join('');
    var champF = $('[data-fiche-formats-champ]', fiche), boite = $('[data-fiche-formats]', fiche);
    champF.hidden = !d.formats;
    boite.innerHTML = d.formats ? D.formats.map(function (f) {
      return '<button type="button" data-format="' + f.id + '" aria-pressed="' + (f.id === 'classique') + '">' + esc(f.nom) + ' · ' + esc(f.dim) + '</button>';
    }).join('') : '';
    $('[data-fiche-contact]', fiche).href = lienContact(d, F.format);
    var img = $('[data-fiche-img]', fiche);
    img.src = 'assets/img/rendus/' + d.id + '.webp'; img.alt = d.alt || d.nom;
  }
  function choisirFormat(id) {
    F.format = id;
    $$('[data-format]', fiche).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-format') === id)); });
    $('[data-fiche-img]', fiche).src = 'assets/img/rendus/panneaux-classiques' + (id === 'classique' ? '' : '--' + id) + '.webp';
    $('[data-fiche-contact]', fiche).href = lienContact(F.d, id);
    if (F.viewer && $('[data-fiche-visuel]', fiche).classList.contains('has-3d')) F.viewer.show(F.d.id, { format: id, keepAngle: true });
  }
  function ouvrirFiche(id, source, o) {
    var d = dispoParId[id];
    if (!d || !fiche) return;
    toutFermer();
    rendreFiche(d);
    if (o && o.format && d.formats && D.formats.some(function (f) { return f.id === o.format; })) choisirFormat(o.format);
    var visuel = $('[data-fiche-visuel]', fiche);
    visuel.classList.remove('has-3d');
    ouvrir(fiche, {
      source: source, focus: '[data-fermer]',
      ouverture: function () {
        CibleApp.charger3D().then(function (M) {
          if (F.d !== d || !fiche.classList.contains('is-open')) return;
          if (!F.viewer && !F.essaye) { F.essaye = true; F.viewer = M.createViewer($('[data-fiche-canvas]', fiche)); }
          if (!F.viewer) return;
          F.viewer.show(d.id, { format: F.format });
          F.viewer.start();
          requestAnimationFrame(function () { visuel.classList.add('has-3d'); });
        }).catch(function () { /* visuel fixe conservé */ });
      },
      fermeture: function () {
        if (F.viewer) F.viewer.stop();
        if (location.hash.indexOf('#fiche-') === 0) history.replaceState(null, '', location.pathname + location.search);
      }
    });
    if (location.hash !== '#fiche-' + id) history.replaceState(null, '', '#fiche-' + id);
  }
  CibleApp.ouvrirFiche = ouvrirFiche;
  if (fiche) {
    fiche.addEventListener('click', function (e) {
      if (e.target === fiche || e.target.closest('[data-fermer]')) { fermer(fiche); return; }
      var f = e.target.closest('[data-format]');
      if (f) choisirFormat(f.getAttribute('data-format'));
    });
    window.addEventListener('resize', function () { if (F.viewer && fiche.classList.contains('is-open')) F.viewer.resize(); });
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-fiche-id]');
    if (o) { e.preventDefault(); ouvrirFiche(o.getAttribute('data-fiche-id'), o, { format: o.getAttribute('data-fiche-format') }); }
  });
  function routeFiche() { var m = location.hash.match(/^#fiche-([\w-]+)$/); if (m && dispoParId[m[1]]) ouvrirFiche(m[1]); }
  window.addEventListener('hashchange', routeFiche);

  /* ==================================================================== */
  /* Gabarits générés (catalogues, clients, distinctions, chiffres…)      */
  /* ==================================================================== */
  /* Exécuté au DOMContentLoaded : les scripts de page ont déjà généré leur contenu. */
  function initialiser() {
    CibleApp.carteDispositif = function (d, i) {
      return '<article class="dispo" data-reveal style="--d:' + ((i % 3) * 0.08) + 's">' +
        '<button class="dispo__visuel ciel" type="button" data-fiche-id="' + d.id + '" aria-label="Ouvrir la fiche « ' + esc(d.nom) + ' » en 3D">' +
          '<span class="dispo__num">' + d.n + '</span><span class="dispo__cat"><span class="puce" style="--c:' + poleParId[d.pole].couleur + '"></span>' + esc(d.cat) + '</span>' +
          '<img src="assets/img/rendus/' + d.id + '.webp" alt="' + esc(d.alt || d.nom) + '" width="800" height="800" loading="lazy" decoding="async" style="--flot:' + (-i * 0.9) + 's">' +
          '<span class="dispo__voir">Voir en 3D' + ico('i-tourner') + '</span>' +
        '</button>' +
        '<div class="dispo__corps"><h3 class="dispo__titre">' + esc(d.nom) + '</h3>' +
          (d.dim ? '<p class="dispo__spec">' + esc(d.dim) + '</p>' : (d.formats ? '<p class="dispo__spec">Du petit format (2×1 m) au panoramique (14×5 m)</p>' : '')) +
          '<p class="dispo__texte">' + esc(d.court) + '</p>' +
          '<ul class="tags">' + d.tags.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>' +
          '<div class="dispo__pied"><button class="btn btn--ligne" type="button" data-fiche-id="' + d.id + '">Voir en 3D' + ico('i-tourner') + '</button>' +
          '<a class="btn btn--rouge" href="' + lienContact(d) + '" aria-label="Nous consulter sur « ' + esc(d.nom) + ' »">Nous consulter' + ico('i-fleche') + '</a></div>' +
        '</div></article>';
    };
    $$('[data-catalogue]').forEach(function (el) {
      var pole = el.getAttribute('data-catalogue');
      el.innerHTML = D.dispositifs.filter(function (d) { return d.pole === pole; }).map(CibleApp.carteDispositif).join('');
    });
    /* Les deux expertises (accueil, page Nos services) */
    $$('[data-expertises]').forEach(function (el) {
      el.innerHTML = D.poles.map(function (p, i) {
        var visuel = p.id === 'digital'
          ? '<span class="expertise__visuel"><span class="posts"><img src="assets/img/photos/post-regie.webp" alt="" loading="lazy"><img src="assets/img/photos/post-camion-podium.webp" alt="" loading="lazy"></span></span>'
          : '<span class="expertise__visuel ciel"><img src="' + p.visuel + '" alt="" loading="lazy"></span>';
        return '<a class="expertise" href="' + p.page + '" style="--c:' + p.couleur + ';--d:' + (i * 0.08) + 's" data-reveal>' + visuel +
          '<span class="expertise__corps"><span class="expertise__num">' + p.n + ' · ' + esc(p.etiquette) + '</span>' +
          '<h3>' + esc(p.titre) + '</h3><p>' + esc(p.court) + '</p>' +
          '<ul class="tags">' + p.prestations.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' +
          '<span class="expertise__lien"><i>' + ico('i-fleche') + '</i>Découvrir l’expertise</span></span></a>';
      }).join('');
    });
    /* Continuer la visite : les trois pages suivantes du parcours, en liens compacts (fin de page) */
    $$('[data-suite]').forEach(function (el) {
      var ordre = ['qui', 'ooh', 'digital', 'reseau', 'realisations'], ici = el.getAttribute('data-suite');
      var depart = ordre.indexOf(ici), liste = (el.getAttribute('data-suite-liste') || '').split(',').filter(Boolean);
      for (var k = 1; liste.length < 3 && k <= ordre.length; k++) {
        var id = ordre[(Math.max(depart, -1) + k + ordre.length) % ordre.length];
        if (id !== ici) liste.push(id);
      }
      el.innerHTML = '<p class="suite__entete">Continuer la visite</p><ul class="suite__liens">' + liste.map(function (id) {
        var p = D.pages.find(function (x) { return x.id === id; });
        return '<li><a href="' + p.lien + '"><strong>' + esc(p.titre) + '</strong><span>' + esc(p.texte) + '</span>' + ico('i-fleche') + '</a></li>';
      }).join('') + '</ul>';
    });
    $$('[data-clients]').forEach(function (el) {
      var items = D.clients.map(function (c) { return '<div class="client"><img src="assets/img/clients/' + c.img + '" alt="' + esc(c.nom) + '" loading="lazy"></div>'; }).join('');
      el.innerHTML = '<div class="clients__piste">' + items + items.replace(/alt="[^"]*"/g, 'alt="" aria-hidden="true"') + '</div>';
    });
    $$('[data-distinctions]').forEach(function (el) {
      el.innerHTML = D.distinctions.map(function (d, i) {
        return '<li class="distinction" data-reveal style="--d:' + (i * 0.08) + 's"><span class="medaille">' + ico('i-medaille') + '</span><span class="distinction__an">' + d.an + '</span><h3>' + esc(d.titre) + '</h3><p>' + esc(d.texte) + '</p></li>';
      }).join('');
    });
    $$('[data-chiffres]').forEach(function (el) {
      el.innerHTML = D.chiffres.map(function (c) {
        return '<li><strong>' + (c.prefixe ? '<small>' + c.prefixe + '</small>' : '') + '<span data-compteur="' + c.valeur + '"' + (c.statique ? ' data-statique' : '') + (c.pad ? ' data-pad="2"' : '') + '>' + (c.pad ? String(c.valeur).padStart(2, '0') : c.valeur) + '</span></strong><span>' + esc(c.libelle) + '</span></li>';
      }).join('');
    });
    $$('[data-bandeau]').forEach(function (piste) {
      var items = piste.getAttribute('data-bandeau').split('|');
      var html = items.concat(items).map(function (t) { return '<span class="bandeau__item">' + esc(t) + '<i class="sep"></i></span>'; }).join('');
      piste.innerHTML = html + html;
    });
    function compteurs() {
      if (reduit.matches || !('IntersectionObserver' in window)) return;
      var io = new IntersectionObserver(function (ent) {
        ent.forEach(function (en) {
          if (!en.isIntersecting) return;
          io.unobserve(en.target);
          var el = en.target, fin = Number(el.getAttribute('data-compteur')), pad = Number(el.getAttribute('data-pad') || 0), t0 = performance.now();
          (function pas(now) {
            var t = clamp((now - t0) / 1400, 0, 1), v = Math.round(fin * (1 - Math.pow(1 - t, 3)));
            el.textContent = pad ? String(v).padStart(pad, '0') : v;
            if (t < 1) requestAnimationFrame(pas);
          })(t0);
        });
      }, { threshold: 0.5 });
      $$('[data-compteur]:not([data-statique])').forEach(function (el) { el.textContent = el.hasAttribute('data-pad') ? '00' : '0'; io.observe(el); });
    }

    /* Onglets accessibles */
    $$('[data-onglets]').forEach(function (bloc) {
      var tabs = $$('[role="tab"]', bloc);
      function choisir(t, focus) {
        tabs.forEach(function (x) {
          var on = x === t;
          x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1;
          var p = document.getElementById(x.getAttribute('aria-controls'));
          if (p) { p.hidden = !on; if (on) { p.classList.remove('entre'); void p.offsetWidth; p.classList.add('entre'); } }
        });
        if (focus) t.focus();
      }
      tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { choisir(t); });
        t.addEventListener('keydown', function (e) {
          var j = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowUp' || e.key === 'ArrowLeft' ? i - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : null;
          if (j === null) return;
          e.preventDefault(); choisir(tabs[(j + tabs.length) % tabs.length], true);
        });
      });
    });
    /* Filtres */
    $$('[data-filtres]').forEach(function (barre) {
      var cible = document.getElementById(barre.getAttribute('data-filtres'));
      if (!cible) return;
      barre.addEventListener('click', function (e) {
        var b = e.target.closest('[data-filtre]');
        if (!b) return;
        var f = b.getAttribute('data-filtre'), n = 0;
        $$('[data-filtre]', barre).forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        $$('[data-filtres-item]', cible).forEach(function (it) {
          var ok = f === 'tout' || it.getAttribute('data-filtres-item').split(' ').indexOf(f) >= 0;
          it.classList.toggle('is-filtre', !ok);
          if (ok) { n++; it.classList.remove('entre'); void it.offsetWidth; it.classList.add('entre', 'is-in'); }
        });
        var c = $('[data-filtres-compte]');
        if (c) c.textContent = n + ' réalisation' + (n > 1 ? 's' : '');
      });
    });

    /* Manifeste : les mots s'allument au défilement */
    $$('[data-mots]').forEach(function (el) {
      (function couper(n) {
        Array.prototype.slice.call(n.childNodes).forEach(function (c) {
          if (c.nodeType === 3) {
            var frag = document.createDocumentFragment();
            c.textContent.split(/( +)/).forEach(function (p) {
              if (!p) return;
              if (/^ +$/.test(p)) frag.appendChild(document.createTextNode(p));
              else { var s = document.createElement('span'); s.className = 'm'; s.textContent = p; frag.appendChild(s); }
            });
            c.replaceWith(frag);
          } else if (c.nodeType === 1) couper(c);
        });
      })(el);
      var mots = $$('.m', el);
      if (reduit.matches) { mots.forEach(function (m) { m.classList.add('is-lit'); }); return; }
      var tick = false;
      function maj() {
        tick = false;
        var r = el.getBoundingClientRect(), vh = window.innerHeight;
        var t = clamp((vh * 0.9 - r.top) / (vh * 0.55 + r.height * 0.5), 0, 1), n = Math.round(t * mots.length);
        mots.forEach(function (m, i) { m.classList.toggle('is-lit', i < n); });
      }
      window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(maj); } }, { passive: true });
      maj();
    });

    /* Vidéos décoratives : lecture à l'écran seulement, jamais en « mouvement réduit » */
    $$('video[data-auto]').forEach(function (v) {
      if (reduit.matches || !('IntersectionObserver' in window)) return;
      var io = new IntersectionObserver(function (ent) {
        ent.forEach(function (en) {
          if (en.isIntersecting) { if (!v.getAttribute('src') && v.dataset.src) v.src = v.dataset.src; var p = v.play(); if (p) p.catch(function () {}); }
          else v.pause();
        });
      }, { threshold: 0.35 });
      io.observe(v);
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-rejouer]');
      if (!b) return;
      var v = document.getElementById(b.getAttribute('data-rejouer'));
      if (!v) return;
      if (!v.getAttribute('src') && v.dataset.src) v.src = v.dataset.src;
      v.currentTime = 0; var p = v.play(); if (p) p.catch(function () {});
    });

    compteurs();
  }

  /* Apparitions */
  function apparitions() {
    var els = $$('[data-reveal]:not(.is-in)');
    if (reduit.matches || !('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('is-in'); }); return; }
    var io = new IntersectionObserver(function (ent) {
      ent.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('is-in'); io.unobserve(en.target);
        setTimeout(function () { en.target.style.removeProperty('--d'); }, 1400);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });
    els.forEach(function (el) { io.observe(el); });
  }
  CibleApp.apparitions = apparitions;

  /* Démarrage (les scripts de page peuvent générer du contenu avant : DOMContentLoaded) */
  document.addEventListener('DOMContentLoaded', function () {
    initialiser();
    apparitions();
    routeFiche();
    root.classList.add('is-ready');
  });

  /* Boutons d'appel flottants (mobile) : masqués quand on descend, rendus quand on remonte */
  (function () {
    var cr = $('.contact-rapide'), y0 = window.scrollY, tick = false;
    if (!cr) return;
    window.addEventListener('scroll', function () {
      if (tick) return;
      tick = true;
      requestAnimationFrame(function () {
        tick = false;
        var y = window.scrollY, bas = y + window.innerHeight > document.documentElement.scrollHeight - 160;
        if (Math.abs(y - y0) < 12) return;
        cr.classList.toggle('is-cache', bas || (y > y0 && y > 320));
        y0 = y;
      });
    }, { passive: true });
  })();

  /* Hors ligne : cache « réseau d'abord » (http uniquement) */
  if ('serviceWorker' in navigator && location.protocol.indexOf('http') === 0) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); });
  }
})();
