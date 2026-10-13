/*!
 * CIBLE — page Réseau : carte Leaflet chargée à l'approche de la section, liste
 * filtrable des 31 communes et villes, composition d'une zone de campagne
 * transmise au brief. Le nombre de faces par commune n'est jamais affiché.
 */
(function () {
  'use strict';
  var D = window.CIBLE;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ico = function (id) { return '<svg aria-hidden="true"><use href="#' + id + '"/></svg>'; };
  var reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;

  var bloc = $('[data-carte-reseau]');
  if (!bloc) return;
  var CLE = 'cible-zones-v2';
  var ABIDJAN = [5.34, -4.02];
  var COTES = { Korhogo: 'left', Dabou: 'left', 'Grand-Bassam': 'bottom', Aboisso: 'right' };
  var communes = D.communes.map(function (c) { return { nom: c[0], zone: c[1], lat: c[2], lng: c[3], region: c[4] || '' }; });
  var noms = communes.map(function (c) { return c.nom; });
  var choisies = (function () { try { return JSON.parse(localStorage.getItem(CLE)) || []; } catch (e) { return []; } })()
    .filter(function (n) { return noms.indexOf(n) >= 0; });
  var filtre = 'tout', requete = '';
  var liste = $('[data-liste-communes]', bloc), compte = $('[data-compte-communes]', bloc);
  var lien = $('[data-zone-lien]', bloc), texte = $('[data-zone-texte]', bloc), etat = $('[data-carte-etat]', bloc);
  var L = null, carte = null, marqueurs = {}, etiquetteAbidjan = null;

  function sansAccent(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function sauver() { try { localStorage.setItem(CLE, JSON.stringify(choisies)); } catch (e) { /* stockage indisponible */ } }
  function lieu(c) { return c.zone === 'abidjan' ? 'Grand Abidjan' : 'Intérieur du pays' + (c.region ? ' · ' + c.region : ''); }

  /* ---------- Liste ---------- */
  function rendreListe() {
    var q = sansAccent(requete.trim());
    // Recherche au début du nom ou de l'un de ses mots (« bassam » trouve Grand-Bassam).
    var vis = communes.filter(function (c) {
      var n = sansAccent(c.nom);
      return (filtre === 'tout' || c.zone === filtre) && (!q || n.split(/[\s-]+/).concat(n).some(function (m) { return m.indexOf(q) === 0; }));
    });
    liste.innerHTML = vis.map(function (c) {
      var on = choisies.indexOf(c.nom) >= 0;
      return '<li class="' + (on ? 'is-choisie' : '') + '" data-commune="' + esc(c.nom) + '">' +
        '<button type="button" data-voler aria-label="Voir ' + esc(c.nom) + ' sur la carte"><span class="nom-commune">' + esc(c.nom) + '<small>' + esc(lieu(c)) + '</small></span>' + ico('i-pin') + '</button>' +
        '<button type="button" class="basculer" data-basculer aria-pressed="' + on + '" aria-label="' + esc(c.nom) + ' : ' + (on ? 'retirer de' : 'ajouter à') + ' ma zone"><span class="coche-commune">' + ico('i-coche') + '</span></button></li>';
    }).join('') || '<li class="carte-reseau__vide">Aucune commune ne correspond à « ' + esc(requete) + ' ».</li>';
    compte.textContent = vis.length + (vis.length > 1 ? ' communes et villes' : vis.length ? ' commune' : ' résultat');
  }

  function majZone() {
    var n = choisies.length;
    texte.textContent = n
      ? (n > 1 ? n + ' communes choisies : ' : '1 commune choisie : ') + choisies.join(', ') + '.'
      : 'Cochez les communes qui vous intéressent pour composer votre zone.';
    lien.href = 'contact.html?service=ooh' + (n ? '&zones=' + encodeURIComponent(choisies.join('|')) : '') + '#brief';
    lien.firstChild.textContent = n ? 'Demander les emplacements (' + n + ')' : 'Recevoir une sélection d’emplacements';
    Object.keys(marqueurs).forEach(function (nom) {
      var m = marqueurs[nom], on = choisies.indexOf(nom) >= 0, el = m.getElement(), t = m.getTooltip();
      if (el) el.firstChild.classList.toggle('is-on', on);
      if (t && t.getElement()) t.getElement().classList.toggle('is-choisie', on);
    });
  }

  function basculer(nom) {
    var i = choisies.indexOf(nom);
    if (i >= 0) choisies.splice(i, 1); else choisies.push(nom);
    sauver();
    rendreListe();
    majZone();
    if (window.CibleApp && window.CibleApp.toast) window.CibleApp.toast(i >= 0 ? nom + ' retirée de votre zone' : nom + ' ajoutée à votre zone');
  }

  function voler(nom) {
    var c = communes.find(function (x) { return x.nom === nom; });
    if (!c || !carte) return;
    $$('[data-voler]', liste).forEach(function (b) { b.classList.toggle('is-on', b.parentNode.getAttribute('data-commune') === nom); });
    if (window.innerWidth <= 1080) carte.getContainer().scrollIntoView({ behavior: reduit ? 'auto' : 'smooth', block: 'center' });
    carte.flyTo([c.lat, c.lng], c.zone === 'abidjan' ? 13 : 11, { duration: reduit ? 0 : 1.1 });
    carte.once('moveend', function () { marqueurs[nom].openPopup(); });
  }

  liste.addEventListener('click', function (e) {
    var li = e.target.closest('[data-commune]');
    if (!li) return;
    var nom = li.getAttribute('data-commune');
    if (e.target.closest('[data-basculer]')) {
      basculer(nom);
      var b = $('[data-commune="' + CSS.escape(nom) + '"] [data-basculer]', liste);
      if (b) b.focus();
    } else if (e.target.closest('[data-voler]')) voler(nom);
  });
  $('[data-segment]', bloc).addEventListener('click', function (e) {
    var b = e.target.closest('[data-zone]');
    if (!b) return;
    filtre = b.getAttribute('data-zone');
    $$('[data-zone]', this).forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
    rendreListe();
    if (carte) {
      var pts = communes.filter(function (c) { return filtre === 'tout' || c.zone === filtre; }).map(function (c) { return [c.lat, c.lng]; });
      carte.flyToBounds(L.latLngBounds(pts), { padding: [40, 40], duration: reduit ? 0 : 1 });
    }
  });
  var champ = $('[data-recherche]', bloc);
  champ.addEventListener('input', function () { requete = champ.value; rendreListe(); });
  champ.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var premier = $('[data-commune]', liste);
    if (premier) voler(premier.getAttribute('data-commune'));
  });

  /* ---------- Carte ---------- */
  function popup(c) {
    var on = choisies.indexOf(c.nom) >= 0;
    return '<strong>' + esc(c.nom) + '</strong><br><span>' + esc(lieu(c)) + '</span><br>' +
      '<button class="btn btn--sm ' + (on ? 'btn--ligne' : 'btn--rouge') + '" type="button" data-popup-basculer="' + esc(c.nom) + '" style="margin-top:10px">' + (on ? 'Retirer de ma zone' : 'Ajouter à ma zone') + '</button>';
  }
  function echelle() {
    var z = carte.getZoom();
    carte.getContainer().classList.toggle('carte--loin', z < 10);
    carte.getContainer().classList.toggle('carte--pays', z < 9);
    // Vue d'ensemble sur petit écran : les noms se chevaucheraient, ils restent dans les bulles.
    carte.getContainer().classList.toggle('carte--sans-noms', z < 7);
    if (z < 10) etiquetteAbidjan.addTo(carte); else etiquetteAbidjan.remove();
  }
  function initCarte() {
    L = window.L;
    carte = L.map($('[data-carte]', bloc), { scrollWheelZoom: false, minZoom: 6, maxZoom: 16, zoomSnap: 0.5 });
    // Fond Esri « World Light Gray » (sans clé), comme la maquette V2.
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
      maxNativeZoom: 16, maxZoom: 16,
      attribution: 'Fond &copy; Esri — Esri, HERE, Garmin, &copy; OpenStreetMap'
    }).addTo(carte);
    carte.fitBounds(L.latLngBounds(communes.map(function (c) { return [c.lat, c.lng]; })), { padding: [28, 28] });
    communes.forEach(function (c, i) {
      var abj = c.zone === 'abidjan', proche = !abj && Math.hypot(c.lat - ABIDJAN[0], c.lng - ABIDJAN[1]) < 0.9;
      var icone = L.divIcon({
        className: 'marqueur-ic' + (abj ? ' marqueur-abj' : ''),
        html: '<span class="marqueur' + (abj ? '' : ' marqueur--int') + (choisies.indexOf(c.nom) >= 0 ? ' is-on' : '') + '" style="animation-delay:' + (i * 35) + 'ms"></span>',
        iconSize: [22, 22], iconAnchor: [11, 11]
      });
      var m = L.marker([c.lat, c.lng], { icon: icone, title: c.nom, riseOnHover: true }).addTo(carte);
      var cote = COTES[c.nom] || 'right';
      m.bindTooltip(esc(c.nom), {
        permanent: true, direction: cote, offset: cote === 'left' ? [-12, 0] : cote === 'bottom' ? [0, 12] : [12, 0],
        className: 'etiquette-commune' + (abj ? ' tt-abj' : '') + (proche ? ' tt-proche' : '') + (choisies.indexOf(c.nom) >= 0 ? ' is-choisie' : '')
      });
      m.bindPopup(function () { return popup(c); });
      marqueurs[c.nom] = m;
    });
    var abjBornes = L.latLngBounds(communes.filter(function (c) { return c.zone === 'abidjan'; }).map(function (c) { return [c.lat, c.lng]; }));
    var etroite = carte.getSize().x < 600;
    etiquetteAbidjan = L.marker(ABIDJAN, {
      icon: L.divIcon({ className: 'marqueur-ic marqueur-ic--etiquette' + (etroite ? ' a-gauche' : ''), html: '<span class="etiquette-abidjan">Grand Abidjan · 15 zones</span>', iconSize: [190, 34], iconAnchor: etroite ? [204, 17] : [-14, 17] }),
      title: 'Cadrer le Grand Abidjan', keyboard: true
    });
    etiquetteAbidjan.on('click', function () { carte.flyToBounds(abjBornes, { padding: [40, 40], duration: reduit ? 0 : 1.1 }); });
    carte.on('zoomend', echelle);
    echelle();
    carte.on('click', function () { carte.scrollWheelZoom.enable(); });
    carte.on('mouseout', function () { carte.scrollWheelZoom.disable(); });
    carte.on('popupopen', function (e) {
      var b = e.popup.getElement().querySelector('[data-popup-basculer]');
      if (b) b.addEventListener('click', function () { basculer(b.getAttribute('data-popup-basculer')); carte.closePopup(); });
    });
    etat.hidden = true;
  }
  function charger() {
    if (window.L) return Promise.resolve();
    var css = new Promise(function (ok, ko) {
      var l = document.createElement('link');
      l.rel = 'stylesheet'; l.href = 'assets/vendor/leaflet/leaflet.css'; l.onload = ok; l.onerror = ko;
      document.head.appendChild(l);
    });
    var js = new Promise(function (ok, ko) {
      var s = document.createElement('script');
      s.src = 'assets/vendor/leaflet/leaflet.js'; s.onload = ok; s.onerror = ko;
      document.head.appendChild(s);
    });
    return Promise.all([css, js]);
  }
  var lance = false;
  function demarrer() {
    if (lance) return;
    lance = true;
    charger().then(initCarte).then(majZone).catch(function () {
      etat.innerHTML = '<span>Carte momentanément indisponible.<br>Le réseau couvre 31 communes et villes&nbsp;: la liste reste disponible.</span>';
    });
  }

  rendreListe();
  majZone();
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) { io.disconnect(); demarrer(); } }, { rootMargin: '600px 0px' });
    io.observe(bloc);
  } else demarrer();
  if (location.hash === '#carte') demarrer();
})();
