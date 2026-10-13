/*!
 * CIBLE — page Contact : mini-brief en six étapes (validation accessible,
 * brouillon enregistré dans le navigateur, préremplissage depuis les autres pages),
 * puis envoi direct via envoi.php. Si l'envoi direct est impossible (site ouvert
 * sans PHP, serveur de messagerie indisponible), le brief rédigé est proposé par
 * e-mail, WhatsApp ou impression : aucune demande n'est perdue.
 */
(function () {
  'use strict';
  var D = window.CIBLE, B = D.brief, C = D.coordonnees;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ico = function (id) { return '<svg aria-hidden="true"><use href="#' + id + '"/></svg>'; };
  var reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;

  var form = $('[data-brief]');
  if (!form) return;
  var CLE = 'cible-brief-v2', CLE_ZONES = 'cible-zones-v2';
  var carte = $('[data-brief-carte]'), succes = $('[data-succes]');
  var etapes = $$('[data-etape]', form), N = etapes.length, cur = 0;
  var prec = $('[data-precedent]', form), suiv = $('[data-suivant]', form), env = $('[data-envoyer]', form);
  var erreurs = $('[data-erreurs]', form), brouillon = $('[data-brouillon]', form);
  var communes = [];
  var MAX_FICHIERS = 10 * 1024 * 1024;

  /* ---------- Gabarits ---------- */
  $$('[data-choix]', form).forEach(function (box) {
    // data-champ : plusieurs listes peuvent alimenter le même champ (services OOH + Digital).
    var liste = box.getAttribute('data-choix'), nom = box.getAttribute('data-champ') || liste, type = box.getAttribute('data-type');
    box.innerHTML = B[liste].map(function (v) {
      return '<label><input type="' + type + '" name="' + nom + '" value="' + esc(v) + '"><span>' + esc(v) + '</span></label>';
    }).join('');
  });
  var li = function (t) { return '<li>' + esc(t) + '</li>'; };
  $('[data-recevrez]', form).innerHTML = B.recevrez.map(li).join('');
  var pourquoi = $('[data-pourquoi]');
  if (pourquoi) pourquoi.innerHTML = B.pourquoi.map(li).join('');
  $('[data-progression]', form).innerHTML = etapes.map(function () { return '<span></span>'; }).join('');
  $('[data-etapes-noms]', form).innerHTML = etapes.map(function (e) { return '<li>' + esc(e.getAttribute('data-nom')) + '</li>'; }).join('');

  /* ---------- Lecture des réponses ---------- */
  function valeurs(nom) { return $$('[name="' + nom + '"]:checked', form).map(function (i) { return i.value; }); }
  function champ(nom) { var el = form.elements[nom]; return el && el.value ? el.value.trim() : ''; }
  function fichiersBruts() {
    return $$('input[type="file"]', form).reduce(function (a, i) { return a.concat(Array.prototype.slice.call(i.files || [])); }, []);
  }
  function fichiers() { return fichiersBruts().map(function (f) { return f.name; }); }
  // « Affichage OOH : … · Digital : … » à partir des cases cochées.
  function servicesTexte(liste) {
    var ooh = liste.filter(function (v) { return B.servicesOOH.indexOf(v) >= 0; });
    var dig = liste.filter(function (v) { return B.servicesDigital.indexOf(v) >= 0; });
    var autres = liste.filter(function (v) { return ooh.indexOf(v) < 0 && dig.indexOf(v) < 0; });
    return [ooh.length ? 'Affichage OOH : ' + ooh.join(', ') : '', dig.length ? 'Digital : ' + dig.join(', ') : ''].concat(autres).filter(Boolean).join(' · ');
  }
  function collecter() {
    return {
      nom: champ('nom'), entreprise: champ('entreprise'), fonction: champ('fonction'), email: champ('email'), telephone: champ('telephone'),
      objectifs: valeurs('objectifs'), cibles: valeurs('cibles'), zones: valeurs('zones'), communes: communes.slice(),
      periode: valeurs('periodes')[0] || '', services: valeurs('services'), budget: valeurs('budgets')[0] || '',
      description: champ('description'), provenance: valeurs('provenances')[0] || '', consentement: form.elements.consentement.checked
    };
  }

  /* ---------- Étapes ---------- */
  function afficher(i, focus) {
    cur = Math.max(0, Math.min(N - 1, i));
    etapes.forEach(function (e, k) { e.hidden = k !== cur; });
    $$('[data-progression] span', form).forEach(function (s, k) { s.classList.toggle('is-faite', k <= cur); });
    $$('[data-etapes-noms] li', form).forEach(function (s, k) { s.classList.toggle('is-courante', k === cur); s.classList.toggle('is-faite', k < cur); });
    $('[data-etape-texte]', form).textContent = 'Étape ' + (cur + 1) + ' sur ' + N + ' · ' + etapes[cur].getAttribute('data-nom');
    prec.hidden = cur === 0;
    suiv.hidden = cur === N - 1;
    env.hidden = cur !== N - 1;
    effacerErreurs();
    if (cur === N - 1) rendreRecap($('[data-recap]', form));
    if (focus) {
      var haut = carte.getBoundingClientRect().top;
      if (haut < 80 || haut > window.innerHeight * 0.6) carte.scrollIntoView({ behavior: reduit ? 'auto' : 'smooth', block: 'start' });
      var premier = $('input:not([type="hidden"]), textarea', etapes[cur]);
      if (premier) setTimeout(function () { premier.focus({ preventScroll: true }); }, reduit ? 0 : 250);
    }
  }

  /* ---------- Validation (messages reliés aux champs) ---------- */
  function fautesEtape(i) {
    var e = etapes[i], fautes = [];
    $$('input[required], textarea[required]', e).forEach(function (c) {
      if (c.type === 'checkbox') { if (!c.checked) fautes.push({ el: c, msg: 'Merci d’accepter d’être recontacté pour que nous puissions vous répondre.' }); return; }
      var v = c.value.trim();
      if (!v) fautes.push({ el: c, msg: 'Ce champ est obligatoire.' });
      else if (c.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) fautes.push({ el: c, msg: 'Adresse email invalide (exemple : nom@entreprise.ci).' });
      else if (c.type === 'tel' && v.replace(/\D/g, '').length < 8) fautes.push({ el: c, msg: 'Numéro incomplet : indiquez au moins 8 chiffres.' });
    });
    $$('[data-requis]', e).forEach(function (g) {
      if (!$('input:checked', g)) fautes.push({ el: $('input', g), groupe: g, msg: 'Choisissez au moins une réponse.' });
    });
    var depot = $('[data-depot]', e);
    if (depot) {
      var total = fichiersBruts().reduce(function (t, x) { return t + x.size; }, 0);
      if (total > MAX_FICHIERS) fautes.push({ el: $('input[type="file"]', depot), groupe: depot, msg: 'Vos documents dépassent 10 Mo au total (' + (total / 1048576).toFixed(1).replace('.', ',') + ' Mo). Retirez-en un : vous pourrez nous l’envoyer par e-mail.' });
    }
    return fautes;
  }
  function effacerErreurs() {
    $$('.is-erreur', form).forEach(function (el) { el.classList.remove('is-erreur'); });
    $$('.erreur', form).forEach(function (el) { el.remove(); });
    $$('[aria-invalid]', form).forEach(function (el) { el.removeAttribute('aria-invalid'); el.removeAttribute('aria-describedby'); });
    erreurs.classList.remove('is-on');
    erreurs.innerHTML = '';
  }
  function montrerErreurs(fautes) {
    effacerErreurs();
    if (!fautes.length) return;
    fautes.forEach(function (f, k) {
      var hote = f.groupe || f.el.closest('.champ, .case'), id = 'erreur-' + cur + '-' + k;
      var p = document.createElement('p');
      p.className = 'erreur'; p.id = id; p.textContent = f.msg;
      hote.classList.add('is-erreur');
      if (f.groupe) f.groupe.appendChild(p);
      else { p.setAttribute('aria-hidden', 'true'); (hote.classList.contains('case') ? hote.parentNode.insertBefore(p, hote.nextSibling) : hote.appendChild(p)); }
      f.el.setAttribute('aria-invalid', 'true');
      f.el.setAttribute('aria-describedby', id);
    });
    erreurs.innerHTML = '<p style="margin:0">Merci de vérifier les champs indiqués avant de valider votre demande.</p>' +
      (fautes.length > 1 ? '<ul>' + fautes.map(function (f) { var l = f.groupe ? $('legend', f.groupe) : f.el.closest('.champ, .case'); return '<li>' + esc((l ? l.textContent : '').replace(/\s*\*\s*$/, '').trim()) + '</li>'; }).join('') + '</ul>' : '');
    erreurs.classList.add('is-on');
    f0(fautes).focus();
  }
  function f0(fautes) { return fautes[0].el; }
  function valider(i) { var f = fautesEtape(i); montrerErreurs(f); return !f.length; }

  suiv.addEventListener('click', function () { if (valider(cur)) { afficher(cur + 1, true); sauver(); } });
  prec.addEventListener('click', function () { afficher(cur - 1, true); });
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON' || e.target.type === 'file' || e.target.type === 'submit') return;
    e.preventDefault();
    if (cur < N - 1) suiv.click();
  });
  // Les erreurs s'effacent pendant la saisie (et non à la perte de focus : le décalage
  // de mise en page ferait manquer le clic sur « Continuer »).
  function corriger(e) {
    if (e.type === 'change' && !/checkbox|radio|file/.test(e.target.type)) return;
    var hote = e.target.closest('.is-erreur');
    if (hote && fautesEtape(cur).every(function (f) { return (f.groupe || f.el.closest('.champ, .case')) !== hote; })) {
      hote.classList.remove('is-erreur');
      var m = hote.querySelector('.erreur') || (hote.nextElementSibling && hote.nextElementSibling.classList.contains('erreur') ? hote.nextElementSibling : null);
      if (m) m.remove();
      e.target.removeAttribute('aria-invalid');
      if (!$('.is-erreur', form)) { erreurs.classList.remove('is-on'); erreurs.innerHTML = ''; }
    }
  }
  form.addEventListener('input', corriger);
  form.addEventListener('change', corriger);

  /* ---------- Récapitulatif ---------- */
  function lignes(d) {
    var l = [
      ['Contact', [d.nom, d.fonction, d.entreprise].filter(Boolean).join(' · ')],
      ['Coordonnées', [d.email, d.telephone].filter(Boolean).join(' · ')],
      ['Objectif', d.objectifs.join(', ')],
      ['Cible', d.cibles.join(', ')],
      ['Zones', d.zones.concat(d.communes.length ? ['Communes : ' + d.communes.join(', ')] : []).join(' · ')],
      ['Lancement', d.periode],
      ['Services', servicesTexte(d.services)],
      ['Budget', d.budget],
      ['Projet', d.description.length > 220 ? d.description.slice(0, 220) + '…' : d.description],
      ['Documents', fichiers().join(', ')]
    ];
    return l.filter(function (x) { return x[1]; });
  }
  function rendreRecap(dl) {
    if (!dl) return;
    dl.innerHTML = '<p style="margin:0 0 4px; font-family:var(--titre); font-weight:700; font-size:16px">Votre demande en un coup d’œil</p>' +
      lignes(collecter()).map(function (x) { return '<div><dt>' + esc(x[0]) + '</dt><dd>' + esc(x[1]) + '</dd></div>'; }).join('');
  }

  /* ---------- Communes choisies sur la carte du réseau ---------- */
  function rendreCommunes() {
    var box = $('[data-communes-choisies]', form);
    box.hidden = !communes.length;
    box.innerHTML = communes.length ? '<p class="aide" style="margin:0 0 8px">Communes choisies sur la carte&nbsp;:</p><ul class="tags">' + communes.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('') + '</ul>' +
      '<p style="margin:10px 0 0"><a class="lien" href="reseau.html#carte">Modifier sur la carte' + ico('i-fleche') + '</a></p>' : '';
  }

  /* ---------- Brouillon ---------- */
  var tSauve = 0;
  function sauver() {
    try {
      var d = collecter();
      delete d.consentement;
      localStorage.setItem(CLE, JSON.stringify(d));
      brouillon.hidden = false;
      brouillon.lastChild.textContent = 'Brouillon enregistré';
    } catch (e) { /* stockage indisponible */ }
  }
  form.addEventListener('input', function () { clearTimeout(tSauve); tSauve = setTimeout(sauver, 500); });
  form.addEventListener('change', function () { clearTimeout(tSauve); tSauve = setTimeout(sauver, 200); });
  function cocher(nom, v) {
    var i = $$('[name="' + nom + '"]', form).find(function (x) { return x.value === v; });
    if (i) { i.checked = true; return true; }
    return false;
  }
  function ajouterLigne(t) {
    var ta = form.elements.description;
    if (ta.value.indexOf(t) >= 0) return;
    // Retour à la ligne final : ce que la personne tape ensuite commence sur une nouvelle ligne.
    ta.value = (ta.value ? ta.value.replace(/\s+$/, '') + '\n' : '') + t + '\n';
  }
  (function restaurer() {
    var d = null;
    try { d = JSON.parse(localStorage.getItem(CLE)); } catch (e) { d = null; }
    if (!d) return;
    ['nom', 'entreprise', 'fonction', 'email', 'telephone', 'description'].forEach(function (k) { if (d[k] && form.elements[k]) form.elements[k].value = d[k]; });
    [['objectifs', d.objectifs], ['cibles', d.cibles], ['zones', d.zones], ['services', d.services]].forEach(function (p) { (p[1] || []).forEach(function (v) { cocher(p[0], v); }); });
    if (d.periode) cocher('periodes', d.periode);
    if (d.budget) cocher('budgets', d.budget);
    if (d.provenance) cocher('provenances', d.provenance);
    if (d.communes) communes = d.communes;
    brouillon.hidden = false;
    brouillon.lastChild.textContent = 'Brouillon restauré';
  })();

  /* ---------- Préremplissage depuis les autres pages ---------- */
  var OBJECTIFS = {
    'Accroître la notoriété d’une marque': 'Développer la notoriété de ma marque',
    'Campagne nationale de notoriété': 'Développer la notoriété de ma marque',
    'Lancer un produit ou un service': 'Lancer un nouveau produit ou service',
    'Lancement de produit ou de service': 'Lancer un nouveau produit ou service',
    'Générer du trafic vers un point de vente': 'Générer du trafic vers un point de vente',
    'Génération de trafic en magasin': 'Générer du trafic vers un point de vente',
    'Ouverture d’un point de vente': 'Générer du trafic vers un point de vente',
    'Campagne promotionnelle': 'Augmenter mes ventes',
    'Prise de parole événementielle': 'Communiquer sur un événement',
    'Valoriser une institution ou une entreprise': 'Valoriser mon entreprise',
    'Communication institutionnelle': 'Communication institutionnelle'
  };
  (function preremplir() {
    var q = new URLSearchParams(location.search), fait = false;
    var service = q.get('service'), objectif = q.get('objectif'), sujet = q.get('sujet'), zones = q.get('zones');
    // service=ooh | digital (liens des pages d'expertise) ou libellé exact d'un service.
    if (service) {
      var s = service.toLowerCase();
      if (s === 'ooh') fait = cocher('services', /écran|ecran|dooh|digital/i.test(sujet || '') ? B.servicesOOH[1] : B.servicesOOH[0]);
      else if (s === 'digital') fait = cocher('services', B.servicesDigital[0]);
      else fait = cocher('services', service);
    }
    if (objectif) {
      cocher('objectifs', OBJECTIFS[objectif] || 'Autre');
      if (/nationale/i.test(objectif)) cocher('zones', 'National');
      ajouterLigne('Objectif : ' + objectif);
      fait = true;
    }
    if (sujet) { ajouterLigne(sujet); fait = true; }
    var liste = zones ? zones.split('|') : (function () { try { return JSON.parse(localStorage.getItem(CLE_ZONES)) || []; } catch (e) { return []; } })();
    var connues = D.communes.map(function (c) { return c[0]; });
    liste = liste.filter(function (n) { return connues.indexOf(n) >= 0; });
    if (liste.length) {
      communes = liste;
      liste.forEach(function (n) {
        var c = D.communes.find(function (x) { return x[0] === n; });
        if (!cocher('zones', n)) cocher('zones', c[1] === 'abidjan' ? 'Grand Abidjan' : 'Intérieur du pays');
      });
      if (zones) fait = true;
    }
    rendreCommunes();
    if (fait) sauver();
  })();

  /* ---------- Envoi ---------- */
  function composer(d) {
    var f = fichiers(), t = [];
    t.push('Bonjour,', '', 'Je souhaite recevoir une recommandation média.', '');
    t.push('— Contact —', 'Nom : ' + d.nom, 'Entreprise : ' + d.entreprise);
    if (d.fonction) t.push('Fonction : ' + d.fonction);
    t.push('Email : ' + d.email, 'Téléphone : ' + d.telephone, '');
    t.push('— Projet —', 'Objectif : ' + (d.objectifs.join(', ') || 'à préciser'));
    if (d.cibles.length) t.push('Cible : ' + d.cibles.join(', '));
    if (d.zones.length) t.push('Zones : ' + d.zones.join(', '));
    if (d.communes.length) t.push('Communes choisies : ' + d.communes.join(', '));
    if (d.periode) t.push('Lancement : ' + d.periode);
    t.push('');
    if (d.services.length) t.push('— Services souhaités —', servicesTexte(d.services), '');
    if (d.budget) t.push('— Budget envisagé —', d.budget, '');
    if (d.description) t.push('— Description —', d.description, '');
    if (f.length) t.push('— Documents (en pièces jointes) —', f.join(', '), '');
    if (d.provenance) t.push('Comment nous avez-vous connus : ' + d.provenance);
    t.push('', 'Envoyé depuis le site CIBLE.');
    return t.join('\n');
  }
  function resumeCourt(d) {
    return 'Bonjour CIBLE, je souhaite recevoir une recommandation média.\n' +
      d.nom + ' — ' + d.entreprise + ' (' + d.email + ', ' + d.telephone + ')\n' +
      'Objectif : ' + (d.objectifs.join(', ') || 'à préciser') +
      (d.zones.length || d.communes.length ? '\nZones : ' + d.zones.concat(d.communes).join(', ') : '') +
      (d.services.length ? '\nServices : ' + servicesTexte(d.services) : '') +
      (d.budget ? '\nBudget : ' + d.budget : '') +
      (d.description ? '\nProjet : ' + (d.description.length > 400 ? d.description.slice(0, 400) + '…' : d.description) : '');
  }
  var envoiEnCours = false, debutSaisie = Date.now();
  var texteEnvoyer = $('[data-envoyer-texte]', form);
  function donneesEnvoi(d) {
    var fd = new FormData();
    ['nom', 'entreprise', 'fonction', 'email', 'telephone', 'periode', 'budget', 'description', 'provenance'].forEach(function (k) { fd.append(k, d[k] || ''); });
    fd.append('objectifs', d.objectifs.join(', '));
    fd.append('cibles', d.cibles.join(', '));
    fd.append('zones', d.zones.join(', '));
    fd.append('communes', d.communes.join(', '));
    fd.append('services', servicesTexte(d.services));
    fd.append('consentement', d.consentement ? 'oui' : '');
    fd.append('site_web', form.elements.site_web.value);
    fd.append('duree', String(Math.round((Date.now() - debutSaisie) / 1000)));
    $$('input[type="file"]', form).forEach(function (i) { if (i.files && i.files[0]) fd.append(i.name, i.files[0]); });
    return fd;
  }
  function montrer(etat, d) {
    var ok = etat === 'ok';
    $('[data-succes-ok]', succes).hidden = !ok;
    $('[data-succes-repli]', succes).hidden = ok;
    if (!ok) {
      $('[data-envoi-mail]', succes).href = 'mailto:' + C.commercial + '?subject=' + encodeURIComponent('Demande de recommandation média — ' + d.entreprise) + '&body=' + encodeURIComponent(composer(d));
      $('[data-envoi-whatsapp]', succes).href = C.whatsapp + '?text=' + encodeURIComponent(resumeCourt(d));
      $('[data-repli-texte]', succes).textContent = etat === 'erreur'
        ? 'Une erreur est survenue lors de l’envoi de votre demande. Merci de réessayer ou de nous contacter directement au ' + C.mobile.affiche + '. Votre brief est rédigé : envoyez-le en un clic ci-dessous.'
        : 'Envoyez-le en un clic : un membre de notre équipe analysera votre projet et vous contactera dans les 24 heures ouvrées.';
      var f = fichiers(), note = $('[data-fichiers-note]', succes);
      note.hidden = !f.length;
      note.textContent = f.length ? 'Pensez à joindre vos documents à l’e-mail : ' + f.join(', ') + '.' : '';
    }
    rendreRecap($('[data-recap-final]', succes));
    form.hidden = true;
    succes.hidden = false;
    carte.scrollIntoView({ behavior: reduit ? 'auto' : 'smooth', block: 'start' });
    succes.focus({ preventScroll: true });
  }
  function occupe(on) {
    envoiEnCours = on;
    env.disabled = on;
    env.setAttribute('aria-busy', String(on));
    texteEnvoyer.textContent = on ? 'Envoi en cours…' : 'Recevoir ma recommandation média';
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (envoiEnCours) return;
    for (var i = 0; i < N; i++) {
      if (fautesEtape(i).length) { afficher(i, true); valider(i); return; }
    }
    var d = collecter();
    sauver();
    // Ouvert directement depuis le disque : pas de serveur, on passe au brief prêt à envoyer.
    if (location.protocol === 'file:' || !window.fetch || !window.FormData) { montrer('pret', d); return; }
    occupe(true);
    var minuteur = window.AbortController ? new AbortController() : null;
    var delai = setTimeout(function () { if (minuteur) minuteur.abort(); }, 30000);
    fetch(form.getAttribute('action'), { method: 'POST', body: donneesEnvoi(d), headers: { 'Accept': 'application/json' }, signal: minuteur ? minuteur.signal : undefined })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { return r.ok && j && j.ok === true; }); })
      .catch(function () { return false; })
      .then(function (ok) {
        clearTimeout(delai);
        occupe(false);
        if (ok) {
          try { localStorage.removeItem(CLE); localStorage.removeItem(CLE_ZONES); } catch (x) { /* stockage indisponible */ }
          brouillon.hidden = true;
          montrer('ok', d);
        } else {
          montrer('erreur', d);
        }
      });
  });
  $('[data-modifier-brief]', succes).addEventListener('click', function () {
    succes.hidden = true;
    form.hidden = false;
    afficher(N - 1, true);
  });
  $('[data-imprimer-brief]', succes).addEventListener('click', function () {
    var root = document.documentElement;
    root.classList.add('impression-brief');
    window.print();
    setTimeout(function () { root.classList.remove('impression-brief'); }, 500);
  });

  /* ---------- Démarrage ---------- */
  afficher(0, false);
})();
