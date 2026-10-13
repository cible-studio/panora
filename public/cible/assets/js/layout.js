/*!
 * CIBLE — gabarit commun (source unique) : icônes, en-tête, menus, pied de page, calques,
 * préchargement des pages. Chargé en synchrone en tête de <body> : les éléments
 * <cible-entete>, <cible-pied> et <cible-calques> sont rendus pendant l'analyse de la page.
 */
(function () {
  'use strict';
  var D = window.CIBLE;
  var page = document.body.getAttribute('data-page') || '';
  var ico = function (id, cls) { return '<svg' + (cls ? ' class="' + cls + '"' : '') + ' aria-hidden="true"><use href="#' + id + '"/></svg>'; };

  /* ---------- Sprite d'icônes ---------- */
  var S = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
  var icones = {
    'i-fleche': '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'i-bas': '<path d="M12 5v14M6 13l6 6 6-6"/>',
    'i-chevron': '<path d="M6 9l6 6 6-6"/>',
    'i-coche': '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    'i-croix': '<path d="M6 6l12 12M18 6 6 18"/>',
    'i-tourner': '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 4v4.5h-4.5"/>',
    'i-pin': '<path d="M12 21s6.5-5.8 6.5-11a6.5 6.5 0 1 0-13 0c0 5.2 6.5 11 6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
    'i-tel': '<path d="M6.6 3.5h2.6l1.4 4-2 1.3a11 11 0 0 0 6.6 6.6l1.3-2 4 1.4v2.6a2 2 0 0 1-2.1 2A16.5 16.5 0 0 1 4.5 5.6a2 2 0 0 1 2.1-2.1z"/>',
    'i-mail': '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7 7.5 6 7.5-6"/>',
    'i-info': '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.6v.1"/>',
    'i-whatsapp': '<path d="M4.2 19.8l1.1-3.9A8.3 8.3 0 1 1 8.6 19z"/><path d="M9.2 8.6c.3-.7.6-.7.9-.7h.6c.2 0 .4.1.5.4l.7 1.7c.1.2 0 .5-.1.7l-.5.6c-.1.2-.1.4 0 .6.7 1.2 1.6 2.1 2.9 2.7.2.1.4.1.6-.1l.6-.7c.2-.2.4-.2.7-.1l1.6.8c.3.1.4.3.4.5 0 .5-.2 1.3-.8 1.7-.6.4-1.6.6-3.6-.3a10 10 0 0 1-4.2-4c-.9-1.6-.6-2.7-.3-3.3z"/>',
    'i-partager': '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="M8.2 10.8 15.8 6.7M8.2 13.2l7.6 4.1"/>',
    'i-imprimer': '<path d="M6.5 9V3.5h11V9M6.5 17H4.5a1 1 0 0 1-1-1v-5.5a1.5 1.5 0 0 1 1.5-1.5h14a1.5 1.5 0 0 1 1.5 1.5V16a1 1 0 0 1-1 1h-2"/><rect x="6.5" y="13.5" width="11" height="7" rx="1"/>',
    'i-recherche': '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
    'i-medaille': '<circle cx="12" cy="14.5" r="5.5"/><path d="M8.5 10 6 3.5h4l2 4.5 2-4.5h4L15.5 10"/><path d="m12 12.2.9 1.8 2 .3-1.4 1.4.3 2-1.8-1-1.8 1 .3-2-1.4-1.4 2-.3z"/>',
    'i-cible': '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1" fill="currentColor"/>',
    'i-pinceau': '<path d="M14.5 4.5l5 5-8 8-5-5z"/><path d="M6.5 12.5 4 15a2.5 2.5 0 0 0 0 3.5l1.5 1.5A2.5 2.5 0 0 0 9 20l2.5-2.5"/>',
    'i-boussole': '<circle cx="12" cy="12" r="8.5"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
    'i-megaphone': '<path d="M4 10v4a1 1 0 0 0 1 1h2l6 4V5L7 9H5a1 1 0 0 0-1 1z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
    'i-mobile': '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
    'i-camera': '<rect x="3" y="7" width="13" height="10" rx="2"/><path d="m16 11 5-3v8l-5-3"/>',
    'i-etincelle': '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
    'i-calques': '<path d="M12 3.5 21 8l-9 4.5L3 8z"/><path d="m3 12 9 4.5 9-4.5M3 16l9 4.5 9-4.5"/>',
    'i-graphe': '<path d="M4 20V4M4 20h16"/><path d="m7.5 14.5 3.5-4 3 2.5 5-6.5"/>',
    'i-ecran': '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M12 16v4M8 20h8"/>',
    'i-oeil': '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    'i-photo': '<rect x="3" y="5.5" width="18" height="14" rx="2"/><circle cx="12" cy="12.5" r="3.5"/><path d="M8.5 5.5 10 3.5h4l1.5 2"/>',
    'i-panneau': '<rect x="2.5" y="4" width="19" height="9" rx="1"/><path d="M8 13v7M16 13v7M5 20h14"/>',
    'i-bus': '<rect x="4" y="3.5" width="16" height="14" rx="2.5"/><path d="M4 11h16M8 17.5V20M16 17.5V20"/>',
    'i-bulle': '<path d="M4 5.5h16v10H9.5L5 19.5v-4H4z"/><path d="M8 9.5h8M8 12.5h5"/>',
    'i-globe': '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.6 3.4 5.6 3.4 8.5s-1 5.9-3.4 8.5c-2.4-2.6-3.4-5.6-3.4-8.5s1-5.9 3.4-8.5z"/>',
    'i-personnes': '<circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.4"/><path d="M15.5 14.3a4.5 4.5 0 0 1 5 4.7"/>'
  };
  var sprite = '<svg class="sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
    Object.keys(icones).map(function (id) { return '<symbol id="' + id + '" viewBox="0 0 24 24"><g ' + S + '>' + icones[id] + '</g></symbol>'; }).join('') + '</svg>';
  document.body.insertAdjacentHTML('afterbegin', sprite);

  var C = D.coordonnees;
  var actif = function (p) { return page === p ? ' aria-current="page"' : ''; };
  var services = ['services', 'ooh', 'mobile', 'experience', 'data'].indexOf(page) >= 0;

  /* ---------- En-tête ---------- */
  function entete() {
    var poles = D.poles.map(function (p) {
      return '<a class="mega__pole" href="' + p.page + '"' + actif(p.id) + '><small><span class="puce" style="--c:' + p.couleur + '"></span>Expertise ' + p.n + '</small><strong>' + p.etiquette + '</strong><span>' + p.titre + '</span></a>';
    }).join('');
    return '' +
      '<a class="skip" href="#contenu">Aller au contenu</a>' +
      '<header class="hdr" data-hdr><div class="hdr__bar">' +
        '<a class="marque" href="index.html" aria-label="CIBLE, Vous visez juste — accueil"><img src="assets/img/marque/logo-couleur.png" alt="CIBLE — Vous visez juste" width="2699" height="1438"></a>' +
        '<nav class="nav" aria-label="Navigation principale">' +
          '<a href="qui-sommes-nous.html"' + actif('qui') + '>Qui sommes-nous</a>' +
          '<button class="nav__declencheur' + (services ? ' is-active' : '') + '" type="button" aria-expanded="false" aria-controls="mega" data-mega>Nos services' + ico('i-chevron') + '</button>' +
          '<a href="travaux.html"' + actif('travaux') + '>Nos réalisations</a>' +
          '<a href="reseau.html"' + actif('reseau') + '>Notre réseau</a>' +
        '</nav>' +
        '<div class="hdr__outils">' +
          '<a class="btn btn--rouge hdr__cta" href="' + (page === 'contact' ? '#brief' : 'contact.html') + '"' + actif('contact') + '>Recevoir ma recommandation média</a>' +
          '<button class="hdr__burger" type="button" aria-expanded="false" aria-controls="menu" data-burger><span aria-hidden="true"></span><span aria-hidden="true"></span><span class="sr">Menu</span></button>' +
        '</div>' +
        '<div class="mega" id="mega" data-mega-panneau><div class="mega__grille">' + poles +
          '<a class="mega__tout" href="expertises.html"' + actif('services') + '><strong>Un seul partenaire, quatre leviers : de la rue à la donnée.</strong><span>Tous nos services' + ico('i-fleche') + '</span></a>' +
        '</div></div>' +
      '</div></header>' +
      '<div class="menu" id="menu" data-menu hidden>' +
        '<nav class="menu__nav" aria-label="Navigation mobile">' +
          '<a href="index.html"' + actif('accueil') + '><span>01</span>Accueil</a>' +
          '<a href="qui-sommes-nous.html"' + actif('qui') + '><span>02</span>Qui sommes-nous</a>' +
          '<a href="expertises.html"' + actif('services') + '><span>03</span>Nos services</a>' +
          '<div class="menu__sous">' + D.poles.map(function (p) { return '<a href="' + p.page + '"' + actif(p.id) + '>' + p.etiquette + '</a>'; }).join('') + '</div>' +
          '<a href="travaux.html"' + actif('travaux') + '><span>04</span>Nos réalisations</a>' +
          '<a href="reseau.html"' + actif('reseau') + '><span>05</span>Notre réseau</a>' +
        '</nav>' +
        '<div class="menu__contact">' +
          '<a class="btn btn--blanc btn--lg" href="' + (page === 'contact' ? '#brief' : 'contact.html') + '"' + actif('contact') + '>Recevoir ma recommandation média' + ico('i-fleche') + '</a>' +
          '<p class="menu__slogan">' + D.slogan + '.</p>' +
        '</div>' +
      '</div>';
  }

  /* ---------- Pied de page ---------- */
  function pied() {
    var an = new Date().getFullYear();
    return '' +
      '<footer class="ftr">' +
        '<div class="wrap">' +
          '<div class="ftr__haut">' +
            '<div><a href="index.html" aria-label="CIBLE, Vous visez juste — accueil"><img class="ftr__logo" src="assets/img/marque/logo-fond-noir.png" alt="CIBLE — Vous visez juste" width="2699" height="1438" loading="lazy"></a>' +
              '<p class="ftr__slogan">Régie publicitaire et studio créatif. + de 30 ans à rendre les marques visibles en Côte d’Ivoire.</p>' +
              '<p class="ftr__signature">' + D.slogan + '.</p></div>' +
            '<nav class="ftr__col" aria-label="Explorer"><p>Explorer</p><ul>' +
              '<li><a href="qui-sommes-nous.html">Qui sommes-nous</a></li>' +
              '<li><a href="expertises.html">Nos services</a></li>' +
              D.poles.map(function (p) { return '<li class="ftr__sous"><a href="' + p.page + '">' + p.etiquette + '</a></li>'; }).join('') +
              '<li><a href="travaux.html">Nos réalisations</a></li><li><a href="reseau.html">Notre réseau</a></li><li><a href="contact.html">Contact</a></li></ul></nav>' +
            '<div class="ftr__col"><p>Nous joindre</p><ul>' +
              '<li><a href="tel:' + C.mobile.tel + '">' + C.mobile.affiche + '</a> <small>(mobile, WhatsApp)</small></li>' +
              '<li><a href="tel:' + C.standard.tel + '">' + C.standard.affiche + '</a> <small>(standard)</small></li>' +
              '<li><a href="mailto:' + C.commercial + '">' + C.commercial + '</a></li>' +
              '<li><a href="mailto:' + C.secretariat + '">' + C.secretariat + '</a></li>' +
              '<li>' + C.adresse[0] + '<br>' + C.adresse[1] + '</li>' +
              '<li>' + C.horaires + '</li></ul></div>' +
            '<div class="ftr__col ftr__recon"><p>Reconnaissances</p><ul>' +
              D.distinctions.map(function (d) { return '<li><b>' + d.an + '</b><span>' + d.titre + '</span></li>'; }).join('') + '</ul>' +
              '<a class="btn btn--rouge btn--sm" href="contact.html" style="margin-top:22px">Recevoir ma recommandation média</a></div>' +
          '</div>' +
          '<div class="ftr__bas"><p>© ' + an + ' CIBLE · ' + D.slogan + ' · Régie publicitaire · Côte d’Ivoire</p>' +
            '<p><a href="confidentialite.html">Données personnelles</a> · Photos des réalisations : visuels d’illustration Unsplash (crédits sur chaque image)</p></div>' +
        '</div>' +
      '</footer>';
  }

  /* ---------- Calques partagés : fiche 3D, message bref, contact rapide ---------- */
  function calques() {
    return '' +
      '<div class="fenetre" role="dialog" aria-modal="true" aria-labelledby="fiche-titre" data-fiche hidden>' +
        '<div class="fenetre__panneau">' +
          '<button class="btn-icone fenetre__fermer" type="button" data-fermer aria-label="Fermer la fiche">' + ico('i-croix') + '</button>' +
          '<div class="fiche">' +
            '<div class="fiche__visuel ciel" data-fiche-visuel>' +
              '<canvas class="fiche__canvas" data-fiche-canvas aria-hidden="true"></canvas>' +
              '<img class="fiche__img" data-fiche-img alt="">' +
              '<p class="fiche__aide">' + ico('i-tourner') + '<span>Glissez pour faire pivoter</span></p>' +
              '<span class="fiche__num" data-fiche-num></span>' +
            '</div>' +
            '<div class="fiche__corps">' +
              '<p class="fiche__cat" data-fiche-cat></p>' +
              '<h2 class="fiche__titre" id="fiche-titre" data-fiche-titre></h2>' +
              '<p class="fiche__accroche" data-fiche-court></p>' +
              '<p class="fiche__texte" data-fiche-texte></p>' +
              '<dl class="specs" data-fiche-specs></dl>' +
              '<div class="config">' +
                '<div class="champ" data-fiche-formats-champ hidden><span>Voir un autre format</span><div class="formats-choix" role="group" aria-label="Format affiché" data-fiche-formats></div></div>' +
                '<a class="btn btn--rouge btn--lg btn--bloc" href="contact.html" data-fiche-contact>Nous consulter sur ce dispositif' + ico('i-fleche') + '</a>' +
                '<a class="btn btn--ligne btn--bloc" href="tel:' + C.mobile.tel + '">' + ico('i-tel') + C.mobile.affiche + '</a>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="toast" role="status" aria-live="polite" data-toast></div>' +
      '<div class="contact-rapide">' +
        '<a href="tel:' + C.mobile.tel + '" aria-label="Appeler CIBLE au ' + C.mobile.affiche + '">' + ico('i-tel') + '</a>' +
        '<a href="' + C.whatsapp + '" rel="noopener" aria-label="Écrire à CIBLE sur WhatsApp">' + ico('i-whatsapp') + '</a>' +
      '</div>';
  }

  function definir(nom, rendu) {
    if (customElements.get(nom)) return;
    customElements.define(nom, class extends HTMLElement {
      connectedCallback() { if (!this.dataset.rendu) { this.dataset.rendu = '1'; this.innerHTML = rendu(); } }
    });
  }
  definir('cible-entete', entete);
  definir('cible-pied', pied);
  definir('cible-calques', calques);

  /* ---------- Navigation plus rapide : pages internes préchargées ---------- */
  // Chrome, Edge : règles de spéculation (préchargement à l'intention de clic).
  // Autres navigateurs : préchargement au survol ou au premier contact.
  if (location.protocol.indexOf('http') === 0) {
    if (window.HTMLScriptElement && HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules')) {
      var regles = document.createElement('script');
      regles.type = 'speculationrules';
      regles.textContent = JSON.stringify({ prefetch: [{ where: { selector_matches: 'a[href$=".html"], a[href*=".html?"], a[href*=".html#"]' }, eagerness: 'moderate' }] });
      document.head.appendChild(regles);
    } else {
      var faits = {};
      var precharger = function (e) {
        var a = e.target.closest && e.target.closest('a[href*=".html"]');
        if (!a || a.origin !== location.origin) return;
        var u = a.href.split('#')[0];
        if (faits[u] || u === location.href.split('#')[0]) return;
        faits[u] = true;
        var l = document.createElement('link'); l.rel = 'prefetch'; l.href = u; document.head.appendChild(l);
      };
      document.addEventListener('mouseover', precharger, { passive: true });
      document.addEventListener('touchstart', precharger, { passive: true });
      document.addEventListener('focusin', precharger);
    }
  }
})();
