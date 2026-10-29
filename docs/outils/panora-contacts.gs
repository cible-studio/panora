/**
 * CONTACTS → PANORA — CIBLE (2026-10-28)
 *
 * Second fichier du projet Apps Script du Sheets « Contacts », à côté de
 * l'extracteur (extracteur-contacts-gmail.gs), dont il reprend les outils :
 * feuille_, journal_, alerte_, lireReglages_, maBoite_, supprimerDeclencheurs_.
 * Guide : docs/outils/EXTRACTEUR_CONTACTS_GMAIL.md, partie « Panora ».
 *
 * Chaque matin, les adresses relevées dans « Contacts » sont rangées :
 *   · Référence : les contacts gardés par la direction (statut Client ou
 *     Prospect), envoyés à Panora par le menu « 📇 Panora » ;
 *   · À valider : les nouvelles adresses à trancher (colonne Décision) ;
 *   · Écartés : adresses ou domaines retirés, rien n'en revient ;
 *   · Ignorés : écartés par le script (adresses personnelles), avec la raison.
 * Un nouveau collègue d'une entreprise de la Référence y entre seul, marqué
 * « Nouveau ». Une ligne supprimée à la main est rangée dans « Écartés » :
 * le script garde la mémoire de toutes les adresses rangées (onglet masqué
 * « Mémoire »).
 *
 * Panora décide du rattachement (règle unique, côté Panora) ; la colonne
 * « Panora » montre son verdict, ligne par ligne. Le jeton est gardé dans
 * les propriétés de VOTRE compte, jamais dans une cellule : chaque personne
 * qui envoie se connecte une fois.
 */

var PNR = {
  REFERENCE: 'Référence',
  A_VALIDER: 'À valider',
  ECARTES: 'Écartés',
  IGNORES: 'Ignorés',
  MEMOIRE: 'Mémoire',
  CORRESPONDANCES: 'Correspondances', // noms d'entreprise à aligner sur Panora (2026-10-29)
  CONTACTS: 'Contacts',            // feuille de l'extracteur
  HEURE_TRI: 7,                    // après la récupération des boîtes (6 h)
  URL_DEFAUT: 'https://panora-cible.com',
  INTERNES: ['cible-ci.com', 'cible.ci'],
  DUREE_MAX_MS: 4.5 * 60 * 1000,   // Google coupe une exécution à 6 minutes
};

var PNR_ENTETES = {
  'Référence': ['E-mail', 'Nom', 'Poste', 'Téléphones', 'Entreprise', 'Domaine', 'Type', 'Secteur', 'NCC', 'Statut', 'Nouveau', 'Panora',
    'Premier échange', 'Dernier échange', 'Échanges', 'Boîtes CIBLE', 'Objets récents', 'Empreinte'],
  'À valider': ['Décision', 'E-mail', 'Nom', 'Poste', 'Téléphones', 'Entreprise', 'Domaine', 'Type', 'Secteur', 'NCC', 'Statut', 'Raison',
    'Premier échange', 'Dernier échange', 'Échanges', 'Boîtes CIBLE', 'Objets récents'],
  'Ignorés': ['Décision', 'E-mail', 'Nom', 'Poste', 'Téléphones', 'Entreprise', 'NCC', 'Domaine', 'Raison', 'Le',
    'Premier échange', 'Dernier échange', 'Échanges', 'Boîtes CIBLE', 'Objets récents'],
  'Écartés': ['Décision', 'E-mail ou domaine', 'Portée', 'Raison', 'Le', 'Nom', 'Entreprise'],
  'Mémoire': ['E-mail', 'Vu le'],
  'Correspondances': ['Appliquer', 'Entreprise dans le Sheet', 'Nom à utiliser', 'Contacts', 'Pourquoi', 'Résultat'],
};

var PNR_LISTES = {
  STATUTS: ['Client', 'Prospect'],
  TYPES: ['Annonceur', 'Agence de communication', 'Régie publicitaire', 'Média', 'Institution', 'Fournisseur', 'Autre', 'Non déterminé'],
  DECISIONS: {
    'À valider': ['Garder', 'Écarter', 'Écarter le domaine'],
    'Ignorés': ['À valider', 'Écarter'],
    'Écartés': ['Remettre'],
  },
};

// Activité reprise de « Contacts » à chaque tri.
var PNR_ACTIVITE = ['Premier échange', 'Dernier échange', 'Échanges', 'Boîtes CIBLE', 'Objets récents'];
// Complétés seulement s'ils sont vides : une saisie de la direction n'est jamais écrasée.
// Le NCC lu dans les mails reste dans le Sheets : il n'est pas envoyé à Panora.
var PNR_SI_VIDE = { 'Nom': 'Nom', 'Poste': 'Poste (signature)', 'Téléphones': 'Téléphones (signature)', 'NCC': 'NCC (mails)' };

// ══════════════════════════════════════════════════════════════
// MENU (appelé par onOpen de l'extracteur)
// ══════════════════════════════════════════════════════════════

function menuPanora_() {
  SpreadsheetApp.getUi().createMenu('📇 Panora')
    .addItem('Vérifier (rien n\'est écrit dans Panora)', 'verifierPanora')
    .addItem('Envoyer à Panora', 'envoyerPanora')
    .addSeparator()
    .addItem('Ranger maintenant (applique aussi les décisions)', 'rangerMaintenant')
    .addItem('Activer le tri chaque matin', 'activerTriQuotidien')
    .addItem('Arrêter le tri chaque matin', 'arreterTriQuotidien')
    .addSeparator()
    .addItem('Appliquer les correspondances (noms d\'entreprise)', 'appliquerCorrespondances')
    .addSeparator()
    .addItem('Connecter à Panora', 'connecterPanora')
    .addToUi();
}

function rangerMaintenant() {
  alerte_(texteBilan(ranger_()));
}

/** Déclencheur du matin. */
function rangerChaqueMatin() {
  ranger_();
}

function activerTriQuotidien() {
  supprimerDeclencheurs_('rangerChaqueMatin');
  ScriptApp.newTrigger('rangerChaqueMatin').timeBased().everyDays(1).atHour(PNR.HEURE_TRI).create();
  alerte_('Tri activé : chaque matin vers ' + PNR.HEURE_TRI + ' h, après la récupération des boîtes.');
}

function arreterTriQuotidien() {
  supprimerDeclencheurs_('rangerChaqueMatin');
  alerte_('Tri du matin arrêté.');
}

function verifierPanora() { envoyerPanora_(true); }
function envoyerPanora() { envoyerPanora_(false); }

// ══════════════════════════════════════════════════════════════
// TRI (cœur sans Google, testé sous Node)
// ══════════════════════════════════════════════════════════════

/**
 * Range les onglets d'après « Contacts ». Modifie `t` sur place.
 *
 * @param {{ref: Object[], aval: Object[], ign: Object[], eca: Object[], mem: Object[]}} t  lignes des onglets ({entête: valeur})
 * @param {Object[]} contacts  lignes de « Contacts »
 * @param {{le: Date, perso: string[], internes: string[]}} o
 * @return {Object} bilan chiffré
 */
function rangerOnglets(t, contacts, o) {
  var b = { nouveaux: 0, aValider: 0, ignores: 0, retires: 0, gardes: 0, ecartes: 0, remis: 0, completes: 0, sansStatut: 0 };
  var perso = ensemble_(o.perso);
  var parEmail = {};
  contacts.forEach(function (c) { var e = email_(c['E-mail']); if (e) parEmail[e] = c; });

  // 1. Décisions de la direction.
  // Écartés → « Remettre » : un domaine cesse d'être écarté (ses adresses
  // reviennent par le tri), une adresse repasse dans « À valider ».
  t.eca = t.eca.filter(function (x) {
    if (texte_(x['Décision']) !== 'Remettre') return true;
    var v = cle_(x['E-mail ou domaine']);
    if (x['Portée'] !== 'Domaine' && email_(v)) {
      var a = depuisContact_(v, parEmail[v] || {});
      a['Nom'] = a['Nom'] || x['Nom'] || '';
      a['Entreprise'] = x['Entreprise'] || '';
      a['Raison'] = 'Remis par la direction';
      t.aval.unshift(a);
    }
    b.remis++;
    return false;
  });
  var domEcartes = {};
  t.eca.forEach(function (x) { if (x['Portée'] === 'Domaine') domEcartes[cle_(x['E-mail ou domaine'])] = true; });

  t.aval = t.aval.filter(function (x) {
    var d = texte_(x['Décision']), e = email_(x['E-mail']);
    if (!d || !e) return true;
    var dom = domaine_(e);
    if (d === 'Garder') {
      var statut = statut_(x['Statut']);
      if (!statut) { x['Raison'] = 'Choisir le statut (Client ou Prospect) avant de garder.'; b.sansStatut++; return true; }
      var r = copie_(x);
      r['Statut'] = statut; r['Nouveau'] = ''; r['Panora'] = ''; r['Empreinte'] = '';
      t.ref.push(r);
      b.gardes++;
      return false;
    }
    // Une messagerie personnelle n'est jamais écartée en entier : seule l'adresse l'est.
    if (d === 'Écarter le domaine' && !perso[dom]) {
      if (!domEcartes[dom]) t.eca.push(ecarte_(dom, 'Domaine', 'Domaine écarté par la direction', o.le, x));
      domEcartes[dom] = true;
      b.ecartes++;
      return false;
    }
    if (d === 'Écarter' || d === 'Écarter le domaine') {
      t.eca.push(ecarte_(e, 'Adresse', 'Écartée par la direction', o.le, x));
      b.ecartes++;
      return false;
    }
    return true;
  });

  t.ign = t.ign.filter(function (x) {
    var d = texte_(x['Décision']), e = email_(x['E-mail']);
    if (!d || !e) return true;
    if (d === 'À valider') {
      var a = copie_(x);
      a['Raison'] = 'Remis par la direction';
      t.aval.unshift(a);
      b.remis++;
      return false;
    }
    if (d === 'Écarter') {
      t.eca.push(ecarte_(e, 'Adresse', 'Écartée par la direction', o.le, x));
      b.ecartes++;
      return false;
    }
    return true;
  });

  // Un domaine écarté emporte ses adresses encore en attente (pas celles de la Référence).
  var horsDomaine = function (x) { return !domEcartes[domaine_(email_(x['E-mail']))]; };
  t.aval = t.aval.filter(horsDomaine);
  t.ign = t.ign.filter(horsDomaine);

  // 2. Où est rangée chaque adresse ; une adresse de la mémoire absente de
  // tous les onglets a été supprimée à la main : elle passe dans « Écartés ».
  var places = {};
  [t.ref, t.aval, t.ign].forEach(function (l) {
    l.forEach(function (x) { var e = email_(x['E-mail']); if (e) places[e] = true; });
  });
  t.eca.forEach(function (x) { if (x['Portée'] !== 'Domaine') places[cle_(x['E-mail ou domaine'])] = true; });

  var memoire = {};
  t.mem.forEach(function (m) { var e = email_(m['E-mail']); if (e) memoire[e] = m; });
  Object.keys(memoire).forEach(function (e) {
    if (places[e]) return;
    if (domEcartes[domaine_(e)]) { delete memoire[e]; return; } // couverte par son domaine
    t.eca.push(ecarte_(e, 'Adresse', 'Supprimée à la main', o.le, { 'Nom': (parEmail[e] || {})['Nom'] }));
    places[e] = true;
    b.retires++;
  });

  // 3. Entreprises de la Référence, par domaine professionnel.
  var refDom = {};
  t.ref.forEach(function (x) {
    var e = email_(x['E-mail']);
    if (!e) return;
    var d = domaine_(e);
    if (perso[d] || dansDomaines_(d, o.internes)) return;
    var r = refDom[d] || (refDom[d] = { modele: x, client: false });
    if (statut_(x['Statut']) === 'Client') r.client = true;
  });

  // 4. Nouvelles adresses de « Contacts ».
  var nouveauxRef = [], nouveauxAval = [];
  Object.keys(parEmail).forEach(function (e) {
    if (places[e]) return;
    var dom = domaine_(e);
    if (dansDomaines_(dom, o.internes) || domEcartes[dom]) return;
    var c = parEmail[e];
    if (refDom[dom]) {
      var r = depuisContact_(e, c);
      var m = refDom[dom].modele;
      r['Entreprise'] = m['Entreprise'] || '';
      r['Type'] = m['Type'] || '';
      r['Secteur'] = m['Secteur'] || '';
      r['Statut'] = refDom[dom].client ? 'Client' : 'Prospect';
      r['Nouveau'] = o.le;
      nouveauxRef.push(r);
      b.nouveaux++;
    } else if (perso[dom]) {
      var g = depuisContact_(e, c);
      g['Raison'] = 'Adresse personnelle';
      g['Le'] = o.le;
      t.ign.unshift(g);
      b.ignores++;
    } else {
      var a = depuisContact_(e, c);
      a['Entreprise'] = c['Entreprise (déduite)'] || '';
      a['Statut'] = texte_(c['Statut CRM']) === 'Client' ? 'Client' : '';
      a['Raison'] = 'Nouvelle entreprise';
      nouveauxAval.push(a);
      b.aValider++;
    }
    places[e] = true;
  });
  // Les nouveautés en haut, pour les voir en ouvrant l'onglet.
  t.ref = nouveauxRef.concat(t.ref);
  t.aval = nouveauxAval.concat(t.aval);

  // 5. Activité à jour ; nom, poste, téléphones complétés s'ils sont vides.
  [t.ref, t.aval, t.ign].forEach(function (l) {
    l.forEach(function (x) {
      var e = email_(x['E-mail']);
      var c = parEmail[e];
      if (!c) return;
      if (vide_(x['Domaine'])) x['Domaine'] = domaine_(e);
      PNR_ACTIVITE.forEach(function (k) { if (!vide_(c[k])) x[k] = c[k]; });
      Object.keys(PNR_SI_VIDE).forEach(function (k) {
        var v = c[PNR_SI_VIDE[k]];
        if (vide_(x[k]) && !vide_(v)) { x[k] = v; b.completes++; }
      });
    });
  });

  // 6. Mémoire : toute adresse rangée.
  Object.keys(places).forEach(function (e) {
    if (!memoire[e] && email_(e)) memoire[e] = { 'E-mail': e, 'Vu le': o.le };
  });
  t.mem = Object.keys(memoire).sort().map(function (e) { return memoire[e]; });

  return b;
}

/**
 * Aligne le nom d'entreprise des lignes sur la table « Correspondances »
 * (lignes « Appliquer = Oui ») : « Moov-africa » → « MOOV », pour que
 * Panora reconnaisse la fiche existante au lieu d'en créer une seconde.
 * Comparaison sans casse, sans espaces superflus, tirets typographiques
 * ramenés au tiret simple. Modifie les lignes.
 *
 * @return {Object} par nom d'origine (clé) : { vers, index (ligne de la table), n (lignes corrigées) }
 */
function appliquerTable(lignes, table) {
  var cle = function (v) { return texte_(v).replace(/[‐-―−]/g, '-').toLowerCase(); };
  var actives = {};
  table.forEach(function (c, i) {
    if (texte_(c['Appliquer']).toLowerCase() !== 'oui') return;
    var de = cle(c['Entreprise dans le Sheet']), vers = texte_(c['Nom à utiliser']);
    if (de && vers) actives[de] = { vers: vers, index: i, n: 0 };
  });
  lignes.forEach(function (x) {
    var a = actives[cle(x['Entreprise'])];
    if (a && texte_(x['Entreprise']) !== a.vers) { x['Entreprise'] = a.vers; a.n++; }
  });
  return actives;
}

function texteBilan(b) {
  var p = function (n, un, plusieurs) { return n + ' ' + (n > 1 ? plusieurs : un); };
  var t = 'Tri : ' + p(b.nouveaux, 'nouveau collègue ajouté', 'nouveaux collègues ajoutés') + ' à la Référence, '
    + p(b.aValider, 'adresse', 'adresses') + ' à valider, '
    + p(b.ignores, 'ignorée', 'ignorées') + ', '
    + p(b.retires, 'supprimée à la main', 'supprimées à la main') + '.';
  if (b.gardes || b.ecartes || b.remis) {
    t += ' Décisions appliquées : ' + p(b.gardes, 'gardée', 'gardées') + ', ' + p(b.ecartes, 'écartée', 'écartées') + ', ' + p(b.remis, 'remise', 'remises') + '.';
  }
  if (b.sansStatut) t += ' ⚠ ' + p(b.sansStatut, 'ligne', 'lignes') + ' « Garder » sans statut : choisir Client ou Prospect.';
  return t;
}

// ══════════════════════════════════════════════════════════════
// ENVOI À PANORA (cœur sans Google)
// ══════════════════════════════════════════════════════════════

/** Ce qui est envoyé : une ligne modifiée après son envoi repart. */
function empreinte(x) {
  return [email_(x['E-mail']), x['Nom'], x['Poste'], x['Téléphones'], x['Entreprise'], x['Secteur'], statut_(x['Statut'])]
    .map(function (v) { return texte_(v); }).join('|');
}

/** Lignes de la Référence à envoyer : avec un statut, jamais envoyées telles quelles. */
function lignesAEnvoyer(ref) {
  return ref.filter(function (x) {
    return email_(x['E-mail']) && statut_(x['Statut']) && empreinte(x) !== texte_(x['Empreinte']);
  });
}

function versPanora(x) {
  return {
    ligne: x._ligne,
    email: email_(x['E-mail']),
    nom: texte_(x['Nom']),
    poste: texte_(x['Poste']),
    telephones: texte_(x['Téléphones']),
    entreprise: texte_(x['Entreprise']),
    secteur: texte_(x['Secteur']),
    relation: statut_(x['Statut']),
  };
}

function texteVerdict(r, simulation, le) {
  var libelle = { cree: 'Créé', complete: 'Complété', a_jour: 'À jour', refuse: 'Refusé' }[r.resultat] || r.resultat;
  var date = ('0' + le.getDate()).slice(-2) + '/' + ('0' + (le.getMonth() + 1)).slice(-2);
  return (simulation ? '🔎 Vérifié le ' + date + ' : ' + libelle : libelle + ' le ' + date) + ' — ' + r.message;
}

// ══════════════════════════════════════════════════════════════
// GOOGLE SHEETS
// ══════════════════════════════════════════════════════════════

/** @return {Object} bilan du tri */
function ranger_() {
  var verrou = LockService.getScriptLock(); // même verrou que l'extracteur : pas d'écriture croisée
  verrou.waitLock(120000);
  try {
    preparerOngletsPanora_();
    var lus = {};
    Object.keys(PNR_ENTETES).forEach(function (n) { lus[n] = lireOnglet_(n); });
    var t = {
      ref: lus[PNR.REFERENCE].objets, aval: lus[PNR.A_VALIDER].objets, ign: lus[PNR.IGNORES].objets,
      eca: lus[PNR.ECARTES].objets, mem: lus[PNR.MEMOIRE].objets,
    };
    var b = rangerOnglets(t, lireOnglet_(PNR.CONTACTS).objets, optionsTri_());
    ecrireOnglet_(lus[PNR.REFERENCE], t.ref);
    ecrireOnglet_(lus[PNR.A_VALIDER], t.aval);
    ecrireOnglet_(lus[PNR.IGNORES], t.ign);
    ecrireOnglet_(lus[PNR.ECARTES], t.eca);
    ecrireOnglet_(lus[PNR.MEMOIRE], t.mem);
    journal_('Panora · tri', texteBilan(b));
    return b;
  } finally {
    verrou.releaseLock();
  }
}

function optionsTri_() {
  var listes = listesPanora_();
  var reglages = lireReglages_(); // réglages de l'extracteur
  var le = new Date();
  le.setHours(0, 0, 0, 0);
  return {
    le: le,
    perso: (listes.messageries_personnelles || []).concat(reglages['Messageries personnelles'] || []),
    internes: PNR.INTERNES.concat(listes.domaines_internes || [], reglages['Domaines internes (exclus)'] || []),
  };
}

function envoyerPanora_(simulation) {
  var verrou = LockService.getScriptLock();
  if (!verrou.tryLock(30000)) { alerte_('Un tri ou un envoi est déjà en cours. Réessayez dans une minute.'); return; }
  try {
    preparerOngletsPanora_();
    var listes = appelPanora_('get', '/api/contacts-gmail/listes');
    if (listes.code !== 200 || !listes.json) {
      alerte_(listes.code === 404 ? 'Panora refuse le jeton : menu 📇 Panora → Connecter à Panora.' : 'Panora ne répond pas (code ' + listes.code + '). Réessayez plus tard.');
      return;
    }
    memoriserListes_(listes.json);
    appliquerListes_();

    var lu = lireOnglet_(PNR.REFERENCE);
    var choix = lignesAEnvoyer(lu.objets);
    if (!choix.length) { alerte_('Rien à envoyer : chaque ligne avec un statut est déjà dans Panora, telle quelle.'); return; }

    // Par lots ; une vérification joue chaque lot à part (une entreprise
    // créée dans un lot vérifié n'existe pas encore pour le suivant).
    var max = listes.json.max_lignes || 500, le = new Date(), debut = Date.now();
    var verdicts = {}, total = { cree: 0, complete: 0, a_jour: 0, refuse: 0 }, erreur = null;
    for (var i = 0; i < choix.length; i += max) {
      if (Date.now() - debut > PNR.DUREE_MAX_MS) { erreur = 'temps écoulé'; break; }
      var rep = appelPanora_('post', '/api/contacts-gmail/envoyer', {
        envoye_par: maBoite_(), simulation: simulation, contacts: choix.slice(i, i + max).map(versPanora),
      });
      if (rep.code !== 200 || !rep.json || !rep.json.ok) { erreur = (rep.json && rep.json.erreur) || 'réponse ' + rep.code; break; }
      rep.json.lignes.forEach(function (r) { verdicts[r.email] = r; total[r.resultat] = (total[r.resultat] || 0) + 1; });
    }
    noterVerdicts_(lu, choix, verdicts, simulation, le);

    var texte = (simulation ? 'Vérification (rien n\'a été écrit dans Panora) : ' : 'Envoi terminé : ')
      + total.cree + ' créé(s), ' + total.complete + ' complété(s), ' + total.a_jour + ' déjà à jour, ' + total.refuse + ' refusé(s).'
      + ' Le détail est dans la colonne « Panora ».'
      + (erreur ? '\n\n⚠ Arrêté avant la fin (' + erreur + '). Les lignes traitées sont notées : relancez pour la suite.' : '');
    journal_('Panora · ' + (simulation ? 'vérification' : 'envoi'), texte.replace(/\n+/g, ' '));
    alerte_(texte);
  } catch (e) {
    alerte_(e.message);
  } finally {
    verrou.releaseLock();
  }
}

/** Écrit le verdict (colonne Panora) et, après un vrai envoi, l'empreinte ; lignes retrouvées par adresse. */
function noterVerdicts_(lu, choix, verdicts, simulation, le) {
  var f = lu.feuille, ent = lu.entetes;
  var cE = ent.indexOf('E-mail') + 1, cP = ent.indexOf('Panora') + 1, cX = ent.indexOf('Empreinte') + 1;
  var n = f.getLastRow() - 1;
  if (n < 1 || !cE || !cP || !cX) return;
  var empreintes = {};
  choix.forEach(function (x) { empreintes[email_(x['E-mail'])] = empreinte(x); });
  var emails = f.getRange(2, cE, n, 1).getValues();
  var colP = f.getRange(2, cP, n, 1).getValues();
  var colX = f.getRange(2, cX, n, 1).getValues();
  emails.forEach(function (l, i) {
    var e = email_(l[0]), r = verdicts[e];
    if (!r) return;
    colP[i][0] = texteVerdict(r, simulation, le);
    if (!simulation) colX[i][0] = empreintes[e];
  });
  f.getRange(2, cP, n, 1).setValues(colP);
  if (!simulation) f.getRange(2, cX, n, 1).setValues(colX);
}

function connecterPanora() {
  var ui = SpreadsheetApp.getUi();
  var props = PropertiesService.getUserProperties();
  var url = props.getProperty('PANORA_URL') || PNR.URL_DEFAUT;

  var r1 = ui.prompt('Connecter à Panora (1/2)', 'Adresse de Panora. Laisser vide pour : ' + url, ui.ButtonSet.OK_CANCEL);
  if (r1.getSelectedButton() !== ui.Button.OK) return;
  url = (r1.getResponseText().trim() || url).replace(/\/+$/, '');
  var r2 = ui.prompt('Connecter à Panora (2/2)', 'Jeton secret (donné par l\'administrateur de Panora) :', ui.ButtonSet.OK_CANCEL);
  if (r2.getSelectedButton() !== ui.Button.OK) return;
  var jeton = r2.getResponseText().trim();

  var rep = appelPanora_('get', '/api/contacts-gmail/listes', null, url, jeton);
  if (rep.code !== 200 || !rep.json) {
    alerte_(rep.code === 404 ? 'Panora refuse ce jeton (ou l\'adresse est fausse). Rien n\'a été enregistré.' : 'Panora ne répond pas (code ' + rep.code + ').');
    return;
  }
  props.setProperty('PANORA_URL', url);
  props.setProperty('PANORA_JETON', jeton);
  memoriserListes_(rep.json);
  preparerOngletsPanora_();

  var moi = maBoite_();
  alerte_('Connecté à ' + url + ' : ' + rep.json.secteurs.length + ' secteurs repris de Panora.'
    + ((rep.json.autorises || []).indexOf(moi) === -1
      ? '\n\n⚠ ' + moi + " n'est pas dans la liste des personnes autorisées à envoyer. Demandez à l'administrateur de l'ajouter (GMAIL_SYNC_AUTORISES)."
      : ''));
}

function appelPanora_(methode, chemin, corps, url, jeton) {
  var props = PropertiesService.getUserProperties();
  url = url || props.getProperty('PANORA_URL');
  jeton = jeton || props.getProperty('PANORA_JETON');
  if (!url || !jeton) throw new Error('Panora n\'est pas connecté pour ' + maBoite_() + ' : menu 📇 Panora → Connecter à Panora.');
  var options = {
    method: methode,
    muteHttpExceptions: true,
    headers: { Authorization: 'Bearer ' + jeton, Accept: 'application/json' }, // jamais le jeton dans l'adresse
  };
  if (corps) { options.contentType = 'application/json'; options.payload = JSON.stringify(corps); }
  var r = UrlFetchApp.fetch(url + chemin, options);
  var json = null;
  try { json = JSON.parse(r.getContentText()); } catch (e) { /* page d'erreur HTML */ }
  return { code: r.getResponseCode(), json: json };
}

/** Listes reprises de Panora (secteurs, messageries personnelles) : partagées par tout le fichier. */
function memoriserListes_(j) {
  PropertiesService.getScriptProperties().setProperty('PANORA_LISTES', JSON.stringify({
    secteurs: j.secteurs || [], messageries_personnelles: j.messageries_personnelles || [],
    domaines_internes: j.domaines_internes || [], max_lignes: j.max_lignes || 500,
  }));
}

function listesPanora_() {
  var s = PropertiesService.getScriptProperties().getProperty('PANORA_LISTES');
  return s ? JSON.parse(s) : {};
}

function preparerOngletsPanora_() {
  Object.keys(PNR_ENTETES).forEach(function (nom) {
    var f = feuille_(nom), ent = PNR_ENTETES[nom];
    if (f.getLastRow() === 0) {
      if (f.getMaxColumns() < ent.length) f.insertColumnsAfter(f.getMaxColumns(), ent.length - f.getMaxColumns());
      f.getRange(1, 1, 1, ent.length).setValues([ent]).setFontWeight('bold').setBackground('#E6E6E6');
      f.setFrozenRows(1);
      return;
    }
    // Colonne d'une nouvelle version : ajoutée à droite, sans rien décaler.
    var lues = f.getRange(1, 1, 1, f.getLastColumn()).getValues()[0].map(function (v) { return String(v).trim(); });
    ent.forEach(function (titre) {
      if (lues.indexOf(titre) !== -1) return;
      var col = lues.length + 1;
      if (f.getMaxColumns() < col) f.insertColumnsAfter(f.getMaxColumns(), 1);
      f.getRange(1, col).setValue(titre).setFontWeight('bold').setBackground('#E6E6E6');
      lues.push(titre);
    });
  });

  var couleurs = {};
  couleurs[PNR.REFERENCE] = '#34A853'; couleurs[PNR.A_VALIDER] = '#FBBC04'; couleurs[PNR.ECARTES] = '#9AA0A6'; couleurs[PNR.IGNORES] = '#DADCE0';
  Object.keys(couleurs).forEach(function (n) { var f = feuille_(n); if (f.getTabColor() !== couleurs[n]) f.setTabColor(couleurs[n]); });

  var mem = feuille_(PNR.MEMOIRE);
  if (!mem.isSheetHidden()) mem.hideSheet();
  var ref = feuille_(PNR.REFERENCE);
  var cX = colonne_(ref, 'Empreinte');
  if (cX && !ref.isColumnHiddenByUser(cX)) ref.hideColumns(cX);
  appliquerListes_();
}

/** Listes déroulantes : statut, type, secteur (de Panora), décisions. */
function appliquerListes_() {
  var secteurs = listesPanora_().secteurs || [];
  var liste = function (valeurs, libre) {
    return SpreadsheetApp.newDataValidation().requireValueInList(valeurs, true).setAllowInvalid(!!libre).build();
  };
  var poser = function (nom, titre, regle) {
    var f = feuille_(nom), c = colonne_(f, titre);
    if (c && f.getMaxRows() > 1) f.getRange(2, c, f.getMaxRows() - 1, 1).setDataValidation(regle);
  };
  [PNR.REFERENCE, PNR.A_VALIDER].forEach(function (n) {
    poser(n, 'Statut', liste(PNR_LISTES.STATUTS));
    poser(n, 'Type', liste(PNR_LISTES.TYPES, true));
    if (secteurs.length) poser(n, 'Secteur', liste(secteurs, true)); // hors liste : signalé, pas bloqué
  });
  Object.keys(PNR_LISTES.DECISIONS).forEach(function (n) { poser(n, 'Décision', liste(PNR_LISTES.DECISIONS[n])); });
  poser(PNR.CORRESPONDANCES, 'Appliquer', liste(['Oui', 'Non'], true)); // « Fait » après application
}

/** @return {{feuille: Sheet, entetes: string[], objets: Object[]}} */
function lireOnglet_(nom) {
  var f = feuille_(nom);
  var v = f.getDataRange().getValues();
  var ent = (v[0] || []).map(function (x) { return String(x).trim(); });
  var objets = [];
  for (var i = 1; i < v.length; i++) {
    if (v[i].every(function (c) { return c === '' || c === null; })) continue;
    var o = { _ligne: i + 1 };
    ent.forEach(function (titre, j) { if (titre) o[titre] = v[i][j]; });
    objets.push(o);
  }
  return { feuille: f, entetes: ent, objets: objets };
}

/** Réécrit l'onglet dans l'ordre de ses colonnes : une colonne ajoutée à la main est gardée. */
function ecrireOnglet_(lu, objets) {
  var f = lu.feuille, ent = lu.entetes, n = objets.length;
  if (!ent.length) return;
  if (f.getMaxRows() > 1) f.getRange(2, 1, f.getMaxRows() - 1, ent.length).clearContent();
  if (!n) return;
  if (f.getMaxRows() < n + 1) f.insertRowsAfter(f.getMaxRows(), n + 1 - f.getMaxRows());
  var lignes = objets.map(function (o) {
    return ent.map(function (titre) { return titre && o[titre] !== undefined && o[titre] !== null ? o[titre] : ''; });
  });
  var tel = ent.indexOf('Téléphones');
  if (tel !== -1) f.getRange(2, tel + 1, n, 1).setNumberFormat('@'); // « +225… » reste un texte
  f.getRange(2, 1, n, ent.length).setValues(lignes);
  ['Nouveau', 'Le', 'Vu le', 'Premier échange', 'Dernier échange'].forEach(function (titre) {
    var j = ent.indexOf(titre);
    if (j !== -1) f.getRange(2, j + 1, n, 1).setNumberFormat('dd/mm/yyyy');
  });
}

function colonne_(f, titre) {
  if (f.getLastColumn() === 0) return 0;
  return f.getRange(1, 1, 1, f.getLastColumn()).getValues()[0].map(function (v) { return String(v).trim(); }).indexOf(titre) + 1;
}

// ══════════════════════════════════════════════════════════════
// PETITS OUTILS
// ══════════════════════════════════════════════════════════════

function texte_(v) {
  if (v === null || v === undefined) return '';
  return String(v).replace(/\s+/g, ' ').trim();
}

function vide_(v) { return texte_(v) === ''; }

function cle_(v) { return texte_(v).toLowerCase().replace(/^@/, ''); }

function email_(v) {
  var e = cle_(v);
  return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e) ? e : '';
}

function domaine_(e) { return e ? e.split('@')[1] || '' : ''; }

function statut_(v) {
  var s = texte_(v).toLowerCase();
  if (s.indexOf('client') === 0) return 'Client';
  if (s.indexOf('prospect') === 0) return 'Prospect';
  return '';
}

function ensemble_(liste) {
  var s = {};
  (liste || []).forEach(function (x) { var k = cle_(x); if (k) s[k] = true; });
  return s;
}

/** « mail.cible-ci.com » appartient à « cible-ci.com ». */
function dansDomaines_(dom, liste) {
  return (liste || []).some(function (x) { x = cle_(x); return dom === x || dom.slice(-(x.length + 1)) === '.' + x; });
}

/** Copie d'une ligne vers un autre onglet : les colonnes de même nom suivent, la décision est vidée. */
function copie_(x) {
  var o = {};
  Object.keys(x).forEach(function (k) { if (k !== '_ligne') o[k] = x[k]; });
  o['Décision'] = '';
  return o;
}

function depuisContact_(e, c) {
  var o = { 'E-mail': e, 'Domaine': domaine_(e), 'Nom': c['Nom'] || '', 'Poste': c['Poste (signature)'] || '', 'Téléphones': c['Téléphones (signature)'] || '',
    'NCC': c['NCC (mails)'] || '' };
  PNR_ACTIVITE.forEach(function (k) { o[k] = c[k] === undefined ? '' : c[k]; });
  return o;
}

function ecarte_(valeur, portee, raison, le, x) {
  return { 'Décision': '', 'E-mail ou domaine': valeur, 'Portée': portee, 'Raison': raison, 'Le': le, 'Nom': (x && x['Nom']) || '', 'Entreprise': (x && x['Entreprise']) || '' };
}

/** Menu : applique la table « Correspondances » à la Référence et à « À valider ». */
function appliquerCorrespondances() {
  var verrou = LockService.getScriptLock();
  verrou.waitLock(60000);
  try {
    preparerOngletsPanora_();
    var corr = lireOnglet_(PNR.CORRESPONDANCES);
    var ref = lireOnglet_(PNR.REFERENCE);
    var aval = lireOnglet_(PNR.A_VALIDER);
    var actives = appliquerTable(ref.objets.concat(aval.objets), corr.objets);
    var cles = Object.keys(actives);
    if (!cles.length) { alerte_('Aucune correspondance à appliquer : mettez « Oui » dans la colonne Appliquer.'); return; }

    ecrireOnglet_(ref, ref.objets);
    ecrireOnglet_(aval, aval.objets);
    var le = new Date(), total = 0;
    var date = ('0' + le.getDate()).slice(-2) + '/' + ('0' + (le.getMonth() + 1)).slice(-2);
    cles.forEach(function (k) {
      var a = actives[k], ligne = corr.objets[a.index];
      ligne['Appliquer'] = 'Fait';
      ligne['Résultat'] = a.n + ' ligne' + (a.n > 1 ? 's' : '') + ' corrigée' + (a.n > 1 ? 's' : '') + ' le ' + date;
      total += a.n;
    });
    ecrireOnglet_(corr, corr.objets);

    var texte = 'Correspondances appliquées : ' + cles.length + ' nom' + (cles.length > 1 ? 's' : '') + ', ' + total
      + ' ligne' + (total > 1 ? 's' : '') + ' corrigée' + (total > 1 ? 's' : '') + '. Lancez « Vérifier » pour contrôler avant l\'envoi.';
    journal_('Panora · correspondances', texte);
    alerte_(texte);
  } finally {
    verrou.releaseLock();
  }
}

// Pour les tests hors Google (Node) — ignoré par Apps Script.
if (typeof module !== 'undefined') {
  module.exports = { rangerOnglets: rangerOnglets, texteBilan: texteBilan, empreinte: empreinte, lignesAEnvoyer: lignesAEnvoyer,
    versPanora: versPanora, texteVerdict: texteVerdict, appliquerTable: appliquerTable, PNR_ENTETES: PNR_ENTETES };
}
