/*!
 * CIBLE — page Travaux : filtres (comportement commun dans app.js), fiches des
 * réalisations, fenêtre de détail avec navigation et lien profond (#orange…).
 */
(function () {
  'use strict';
  var D = window.CIBLE;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ico = function (id) { return '<svg aria-hidden="true"><use href="#' + id + '"/></svg>'; };
  var R = D.realisations;
  // Filtre principal d'une réalisation → expertise correspondante (page, libellé, service du brief).
  var OOH = { page: 'affichage.html', libelle: 'Découvrir l’affichage OOH', service: 'ooh' };
  var DIGITAL = { page: 'digital.html', libelle: 'Découvrir le digital', service: 'digital' };
  var POLES = { affichage: OOH, street: OOH, digital: DIGITAL, audiovisuel: DIGITAL };
  var AUTRE = { page: 'expertises.html', libelle: 'Découvrir nos services', service: '' };

  /* ---------- Filtres (avec le nombre de réalisations par filtre) ---------- */
  var barre = $('[data-filtres-barre]');
  if (barre) {
    barre.innerHTML = D.filtresRealisations.map(function (f, i) {
      var n = f.id === 'tout' ? R.length : R.filter(function (r) { return r.filtres.indexOf(f.id) >= 0; }).length;
      return '<button class="puce-filtre" type="button" data-filtre="' + f.id + '" aria-pressed="' + (i === 0) + '">' + esc(f.libelle) + '<span>' + n + '</span></button>';
    }).join('');
  }

  /* ---------- Fiches ---------- */
  var grille = $('#liste-realisations');
  if (grille) {
    grille.innerHTML = R.map(function (r, i) {
      return '<article class="realisation" id="' + r.slug + '" data-filtres-item="' + r.filtres.join(' ') + '" data-reveal style="--d:' + ((i % 2) * 0.08) + 's">' +
        '<button class="realisation__media" type="button" data-projet-ouvrir="' + i + '" aria-label="Voir le projet ' + esc(r.nom) + '">' +
          '<img src="' + r.image + '" alt="Campagne ' + esc(r.nom) + ' — ' + esc(r.cat) + '" loading="lazy">' +
          '<span class="realisation__num">' + r.n + '</span><span class="realisation__cat">' + esc(r.cat) + '</span>' +
          '<span class="credit-photo">' + esc(r.credit) + '</span>' +
        '</button>' +
        '<div class="realisation__corps">' +
          '<p class="realisation__client"><span class="puce" style="--c:' + r.couleur + '"></span>' + esc(r.nom) + '</p>' +
          '<h3>' + esc(r.titre) + '</h3>' +
          // Carte courte : le texte complet et les prestations sont dans la fiche du projet.
          '<div class="realisation__pied"><button class="btn btn--noir btn--sm" type="button" data-projet-ouvrir="' + i + '">Voir le projet' + ico('i-fleche') + '</button></div>' +
        '</div></article>';
    }).join('');
  }

  /* ---------- Fenêtre de détail ---------- */
  var dlg = $('[data-projet]');
  if (!dlg) return;
  var cur = 0;
  function remplir(i) {
    var r = R[i];
    cur = i;
    var img = $('[data-projet-img]', dlg);
    img.src = r.image;
    img.alt = 'Campagne ' + r.nom + ' — ' + r.cat;
    $('[data-projet-credit]', dlg).textContent = r.credit;
    $('[data-projet-cat]', dlg).textContent = r.n + ' · ' + r.cat;
    $('[data-projet-client]', dlg).innerHTML = '<span class="puce" style="--c:' + r.couleur + '"></span>' + esc(r.nom);
    $('[data-projet-titre]', dlg).textContent = r.titre;
    $('[data-projet-texte]', dlg).textContent = r.texte;
    $('[data-projet-services]', dlg).innerHTML = r.services.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('');
    var pole = POLES[r.filtres[0]] || AUTRE;
    $('[data-projet-contact]', dlg).href = 'contact.html?' + (pole.service ? 'service=' + pole.service + '&' : '') +
      'sujet=' + encodeURIComponent('Un projet comparable à « ' + r.nom + ' » (' + r.cat + ')') + '#brief';
    var lienPole = $('[data-projet-pole]', dlg);
    lienPole.href = pole.page;
    lienPole.textContent = pole.libelle;
    if (history.replaceState) history.replaceState(null, '', '#' + r.slug);
  }
  function ouvrir(i, source) {
    remplir(i);
    window.CibleApp.ouvrir(dlg, { source: source, focus: '[data-fermer]' });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-projet-ouvrir]');
    if (b) ouvrir(Number(b.getAttribute('data-projet-ouvrir')), b);
  });
  dlg.addEventListener('click', function (e) {
    if (e.target === dlg || e.target.closest('[data-fermer]')) { window.CibleApp.fermer(dlg); return; }
    var n = e.target.closest('[data-projet-nav]');
    if (n) {
      // On parcourt les réalisations visibles avec le filtre courant.
      var visibles = R.map(function (r, i) { return i; }).filter(function (i) { var el = document.getElementById(R[i].slug); return !el || !el.classList.contains('is-filtre'); });
      var pos = visibles.indexOf(cur), pas = Number(n.getAttribute('data-projet-nav'));
      remplir(visibles[(pos + pas + visibles.length) % visibles.length]);
      $('.fenetre__panneau', dlg).scrollTop = 0;
    }
  });
  document.addEventListener('keydown', function (e) {
    if (dlg.hidden || !dlg.classList.contains('is-open')) return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      var b = $('[data-projet-nav="' + (e.key === 'ArrowRight' ? 1 : -1) + '"]', dlg);
      if (b && !/input|textarea|select/i.test(document.activeElement.tagName)) b.click();
    }
  });

  /* ---------- Filtre en paramètre : travaux.html?filtre=digital ---------- */
  var filtreDemande = (location.search.match(/[?&]filtre=([a-z]+)/) || [])[1];
  if (filtreDemande) {
    // Après app.js, qui branche le comportement des filtres au chargement du DOM.
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(function () {
        var b = barre && barre.querySelector('[data-filtre="' + filtreDemande + '"]');
        if (b) b.click();
      }, 0);
    });
  }

  /* ---------- Lien profond : travaux.html#orange (au chargement ou depuis la page même) ---------- */
  function ouvrirAncre() {
    var slug = location.hash.slice(1), i = R.findIndex(function (r) { return r.slug === slug; });
    if (i < 0) return;
    if (dlg.classList.contains('is-open')) { remplir(i); return; }
    var carte = document.getElementById(slug);
    if (carte) {
      carte.classList.add('is-cible');
      carte.scrollIntoView({ block: 'center' });
      setTimeout(function () { carte.classList.remove('is-cible'); }, 2600);
    }
    setTimeout(function () { if (window.CibleApp && window.CibleApp.ouvrir) ouvrir(i, carte && carte.querySelector('[data-projet-ouvrir]')); }, 450);
  }
  window.addEventListener('load', ouvrirAncre);
  window.addEventListener('hashchange', ouvrirAncre);
})();
