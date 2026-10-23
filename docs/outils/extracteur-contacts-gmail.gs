/**
 * EXTRACTEUR DE CONTACTS GMAIL — CIBLE (2026-10-23)
 *
 * À coller dans un Google Sheets partagé : Extensions → Apps Script.
 * Guide : docs/outils/EXTRACTEUR_CONTACTS_GMAIL.md
 *
 * Chaque collaborateur ouvre le Sheets et clique sur
 * « 📇 Contacts Gmail → Analyser ma boîte ». Le script lit SA boîte
 * (expéditeurs, destinataires, copies, copies cachées de ses envois) et
 * ajoute les contacts à la feuille commune « Contacts », sans doublon.
 * Une même adresse vue dans plusieurs boîtes n'apparaît qu'une fois, avec
 * la liste des boîtes CIBLE qui ont échangé avec elle.
 *
 * Rien ne quitte votre compte Google : pas de service tiers.
 * Les colonnes « Statut CRM » et « Notes » sont à vous : jamais écrasées.
 *
 * ⚠ Une adresse présente dans Gmail n'a pas consenti à recevoir des
 * campagnes. Qualifier avant d'importer dans Brevo (cf. le guide).
 */

var CONFIG = {
  FEUILLE_CONTACTS: 'Contacts',
  FEUILLE_REGLAGES: 'Réglages',
  FEUILLE_JOURNAL: 'Journal',
  FILS_PAR_PAGE: 50,              // conversations lues par recherche Gmail
  DUREE_MAX_MS: 4.5 * 60 * 1000,  // Google coupe une exécution à 6 minutes
  HEURE_QUOTIDIENNE: 6,
};

var ENTETES = [
  'E-mail', 'Nom', 'Entreprise (déduite)', 'Domaine', "Type d'adresse",
  'Premier échange', 'Dernier échange', 'Échanges', 'Envoyés par CIBLE', 'Reçus de ce contact',
  'Rôles', 'Boîtes CIBLE', 'Objets récents', 'Statut CRM', 'Notes',
];
var COL = {}; ENTETES.forEach(function (e, i) { COL[e] = i; });

var REGLAGES_DEFAUT = {
  'Domaines internes (exclus)': ['cible-ci.com'],
  "Mots exclus dans l'adresse": ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'mailer-daemon', 'postmaster',
    'notification', 'notifications', 'newsletter', 'bounce', 'bounces', 'calendar', 'invitations', 'automated'],
  'Domaines exclus': ['google.com', 'googlemail.com', 'facebookmail.com', 'linkedin.com', 'brevo.com', 'sendinblue.com',
    'amazonses.com', 'mailgun.org', 'sendgrid.net', 'mailchimp.com', 'docs.google.com'],
  'Messageries personnelles': ['gmail.com', 'yahoo.fr', 'yahoo.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr',
    'live.fr', 'live.com', 'icloud.com', 'aol.com', 'ymail.com', 'msn.com'],
  'Domaines clients (statut Client)': [],
};

// ══════════════════════════════════════════════════════════════
// MENU
// ══════════════════════════════════════════════════════════════

function onOpen() {
  SpreadsheetApp.getUi().createMenu('📇 Contacts Gmail')
    .addItem('Analyser ma boîte', 'analyserMaBoite')
    .addItem('Activer la mise à jour quotidienne', 'activerMiseAJourQuotidienne')
    .addItem('Arrêter la mise à jour quotidienne', 'arreterMiseAJourQuotidienne')
    .addSeparator()
    .addItem("Où en est l'analyse de ma boîte ?", 'afficherEtat')
    .addToUi();
}

function analyserMaBoite() {
  preparerFeuilles_();
  var termine = traiterLot();
  alerte_(termine
    ? 'Analyse terminée pour ' + maBoite_() + '. Les contacts sont dans la feuille « Contacts ».'
    : "Analyse en cours : une grosse boîte se traite en plusieurs fois. La suite repart toute seule chaque minute, vous pouvez fermer le fichier. Suivi : feuille « Journal ».");
}

function activerMiseAJourQuotidienne() {
  arreterMiseAJourQuotidienne_(true);
  ScriptApp.newTrigger('miseAJourQuotidienne').timeBased().everyDays(1).atHour(CONFIG.HEURE_QUOTIDIENNE).create();
  alerte_('Mise à jour quotidienne activée pour ' + maBoite_() + ' (chaque jour vers ' + CONFIG.HEURE_QUOTIDIENNE + ' h).');
}

function arreterMiseAJourQuotidienne() {
  arreterMiseAJourQuotidienne_(false);
}

function afficherEtat() {
  var e = lireEtat_();
  if (!e) { alerte_("Votre boîte n'a pas encore été analysée."); return; }
  alerte_(e.phase === 'complet'
    ? "Analyse de l'historique en cours : " + e.curseur + ' conversations lues.'
    : "Historique terminé. Nouveaux mails pris en compte jusqu'au " + new Date(e.depuis).toLocaleString('fr-FR') + '.');
}

/** Déclencheur quotidien : reprend l'historique s'il n'est pas fini, sinon ajoute les nouveaux mails. */
function miseAJourQuotidienne() {
  traiterLot();
}

// ══════════════════════════════════════════════════════════════
// ANALYSE PAR LOTS (reprise automatique)
// ══════════════════════════════════════════════════════════════

/** @return {boolean} true si la phase en cours est terminée. */
function traiterLot() {
  // Un seul lot à la fois par boîte : un double clic, ou la mise à jour de
  // 6 h pendant l'historique, feraient lire et compter deux fois les mêmes mails.
  var verrou = LockService.getUserLock();
  if (!verrou.tryLock(1000)) {
    console.log('Un lot est déjà en cours pour ' + maBoite_() + ' : celui-ci est ignoré.');
    return false;
  }
  try { return traiterLot_(); } finally { verrou.releaseLock(); }
}

function traiterLot_() {
  var debut = Date.now();
  var etat = lireEtat_() || { phase: 'complet', borne: Date.now(), curseur: 0 };
  if (etat.phase === 'quotidien' && etat.curseur === 0) etat.borneQuotidienne = Date.now();

  var moi = adressesPersonnelles_();
  var reglages = lireReglages_();
  var boite = maBoite_();
  var agg = {};
  var termine = false;
  var erreur = null;
  var lus = 0;

  while (Date.now() - debut < CONFIG.DUREE_MAX_MS) {
    var requete, garder;
    if (etat.phase === 'complet') {
      requete = 'before:' + Math.floor(etat.borne / 1000);
      garder = function (t) { return t < etat.borne; };
    } else {
      requete = 'after:' + Math.floor(etat.depuis / 1000);
      garder = function (t) { return t >= etat.depuis && t < etat.borneQuotidienne; };
    }

    try {
      var fils = GmailApp.search(requete, etat.curseur, CONFIG.FILS_PAR_PAGE);
      if (!fils.length) { termine = true; break; }

      // Page entière ou rien : si Google coupe (quota de lecture), la page
      // en cours est refaite plus tard, sans compter deux fois.
      var page = {};
      fils.forEach(function (fil) {
        fil.getMessages().forEach(function (m) {
          // Une conversation contient aussi des messages hors période : on ne
          // compte que ceux de la période, pour ne jamais compter deux fois.
          if (garder(m.getDate().getTime())) analyserMessage_(m, moi, page, boite);
        });
      });
      cumuler(agg, page);
      etat.curseur += fils.length;
      lus += fils.length;
    } catch (e) {
      erreur = e;
      break;
    }
  }

  // Fusion puis sauvegarde de l'avancement : si Google coupe avant, rien
  // n'est perdu ni compté deux fois (le lot est simplement refait).
  var nb = fusionner_(agg, reglages);

  if (termine) {
    if (etat.phase === 'complet') { etat = { phase: 'quotidien', depuis: etat.borne, curseur: 0 }; }
    else { etat = { phase: 'quotidien', depuis: etat.borneQuotidienne, curseur: 0 }; }
    supprimerDeclencheurs_('traiterLot');
  } else {
    // Quota Google atteint ou incident : on réessaie dans une heure.
    planifierSuite_(erreur ? 60 : 1);
  }
  ecrireEtat_(etat);
  journal_(boite, lus + ' conversation(s) lue(s), ' + nb + ' contact(s) mis à jour'
    + (termine ? ' — terminé.' : erreur ? ' — pause (' + erreur.message + '), reprise dans 1 heure.' : ' — suite dans 1 minute.'));
  return termine;
}

function analyserMessage_(m, moi, agg, boite) {
  var date = m.getDate();
  var objet = m.getSubject() || '';
  var exp = parserAdresses(m.getFrom())[0];
  var envoyeParMoi = !!(exp && moi[exp.email]);
  var vus = {};

  if (exp && !envoyeParMoi) noterContact(agg, exp, date, objet, 'De', boite, 'recu', vus);

  var champs = [['À', m.getTo()], ['CC', m.getCc()], ['CCI', envoyeParMoi ? m.getBcc() : '']];
  champs.forEach(function (c) {
    parserAdresses(c[1]).forEach(function (a) {
      if (!moi[a.email]) noterContact(agg, a, date, objet, c[0], boite, envoyeParMoi ? 'envoye' : 'copie', vus);
    });
  });
}

// ══════════════════════════════════════════════════════════════
// FONCTIONS PURES (testées hors Google)
// ══════════════════════════════════════════════════════════════

/** « "Koné, Awa" <awa@x.ci>, b@y.ci » → [{nom:'Koné, Awa', email:'awa@x.ci'}, {nom:'', email:'b@y.ci'}] */
function parserAdresses(liste) {
  var resultat = [];
  if (!liste) return resultat;
  var re = /(?:"([^"]*)"|([^,<"]*?))\s*<([^<>\s]+@[^<>\s]+)>|([^\s,<>";]+@[^\s,<>";]+)/g;
  var m;
  while ((m = re.exec(liste)) !== null) {
    var email = (m[3] || m[4] || '').trim().toLowerCase().replace(/^mailto:/, '');
    if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/.test(email)) continue;
    var nom = (m[1] !== undefined ? m[1] : (m[2] || '')).trim().replace(/^'|'$/g, '');
    if (nom.toLowerCase() === email) nom = '';
    resultat.push({ nom: nom, email: email });
  }
  return resultat;
}

function noterContact(agg, adresse, date, objet, role, boite, sens, vus) {
  var e = agg[adresse.email];
  if (!e) {
    e = agg[adresse.email] = { email: adresse.email, nom: '', premier: date, dernier: date,
      echanges: 0, envoyes: 0, recus: 0, roles: {}, boites: {}, objets: {} };
  }
  if (adresse.nom && (!e.nom || date >= e.dernier)) e.nom = adresse.nom;
  if (date < e.premier) e.premier = date;
  if (date > e.dernier) e.dernier = date;
  e.roles[role] = true;
  e.boites[boite] = true;
  var sujet = nettoyerObjet(objet);
  if (sujet && (!e.objets[sujet] || date > e.objets[sujet])) e.objets[sujet] = date;

  // Un message compte une fois par contact, même s'il y figure deux fois.
  if (vus[adresse.email]) return;
  vus[adresse.email] = true;
  e.echanges++;
  if (sens === 'envoye') e.envoyes++;
  if (sens === 'recu') e.recus++;
}

/** Ajoute les contacts d'une page au cumul de l'exécution. */
function cumuler(agg, page) {
  Object.keys(page).forEach(function (email) {
    var p = page[email], e = agg[email];
    if (!e) { agg[email] = p; return; }
    if (p.nom && (!e.nom || p.dernier >= e.dernier)) e.nom = p.nom;
    if (p.premier < e.premier) e.premier = p.premier;
    if (p.dernier > e.dernier) e.dernier = p.dernier;
    e.echanges += p.echanges; e.envoyes += p.envoyes; e.recus += p.recus;
    Object.keys(p.roles).forEach(function (r) { e.roles[r] = true; });
    Object.keys(p.boites).forEach(function (b) { e.boites[b] = true; });
    Object.keys(p.objets).forEach(function (o) { if (!e.objets[o] || p.objets[o] > e.objets[o]) e.objets[o] = p.objets[o]; });
  });
}

/** « RE: TR: Devis campagne ORANGE » → « Devis campagne ORANGE ». */
function nettoyerObjet(objet) {
  var s = String(objet || '').trim();
  var avant;
  do { avant = s; s = s.replace(/^(re|tr|fw|fwd|réf|ref)\s*(\[\d+\])?\s*:\s*/i, '').trim(); } while (s !== avant);
  return s.slice(0, 120);
}

/** Les 5 objets les plus récents, sans doublon, séparés par « | ». */
function objetsRecents(nouveaux, existants, nouveauxDabord) {
  var neufs = Object.keys(nouveaux).sort(function (a, b) { return nouveaux[b] - nouveaux[a]; });
  var anciens = String(existants || '').split(' | ').map(function (o) { return o.trim(); }).filter(String);
  var tous = nouveauxDabord ? neufs.concat(anciens) : anciens.concat(neufs);
  var vus = {};
  return tous.filter(function (o) { var k = o.toLowerCase(); if (vus[k]) return false; vus[k] = true; return true; }).slice(0, 5).join(' | ');
}

function domaineDe(email) { return email.split('@')[1] || ''; }

function estExclue(email, reglages) {
  var dom = domaineDe(email);
  var local = email.split('@')[0];
  var dansListe = function (liste, d) {
    return liste.some(function (x) { return d === x || d.slice(-(x.length + 1)) === '.' + x; });
  };
  if (dansListe(reglages['Domaines internes (exclus)'], dom)) return true;
  if (dansListe(reglages['Domaines exclus'], dom)) return true;
  return reglages["Mots exclus dans l'adresse"].some(function (mot) { return local.indexOf(mot) !== -1; });
}

/** « mail.orange.ci » → « Orange », « groupe-sifca.com » → « Groupe-sifca ». Vide pour une messagerie personnelle. */
function entrepriseDuDomaine(dom, reglages) {
  if (!dom || reglages['Messageries personnelles'].indexOf(dom) !== -1) return '';
  var parts = dom.split('.');
  parts.pop();
  if (parts.length > 1 && ['co', 'com', 'gouv', 'gov', 'net', 'org', 'ac'].indexOf(parts[parts.length - 1]) !== -1) parts.pop();
  var nom = parts[parts.length - 1] || '';
  return nom.charAt(0).toUpperCase() + nom.slice(1);
}

/** Fusionne les contacts lus avec les lignes existantes. Ne touche jamais à « Statut CRM » ni « Notes ». */
function fusionnerLignes(lignes, agg, reglages) {
  var index = {};
  lignes.forEach(function (l, i) { index[String(l[COL['E-mail']]).toLowerCase()] = i; });
  var nb = 0;

  Object.keys(agg).forEach(function (email) {
    if (estExclue(email, reglages)) return;
    var a = agg[email];
    var dom = domaineDe(email);
    var i = index[email];
    nb++;

    if (i === undefined) {
      var l = new Array(ENTETES.length).fill('');
      l[COL['E-mail']] = email;
      l[COL['Nom']] = a.nom;
      l[COL['Entreprise (déduite)']] = entrepriseDuDomaine(dom, reglages);
      l[COL['Domaine']] = dom;
      l[COL["Type d'adresse"]] = reglages['Messageries personnelles'].indexOf(dom) !== -1 ? 'Personnelle' : 'Professionnelle';
      l[COL['Premier échange']] = a.premier;
      l[COL['Dernier échange']] = a.dernier;
      l[COL['Échanges']] = a.echanges;
      l[COL['Envoyés par CIBLE']] = a.envoyes;
      l[COL['Reçus de ce contact']] = a.recus;
      l[COL['Rôles']] = Object.keys(a.roles).sort().join(', ');
      l[COL['Boîtes CIBLE']] = Object.keys(a.boites).sort().join(', ');
      l[COL['Objets récents']] = objetsRecents(a.objets, '', true);
      l[COL['Statut CRM']] = reglages['Domaines clients (statut Client)'].indexOf(dom) !== -1 ? 'Client' : 'À qualifier';
      index[email] = lignes.length;
      lignes.push(l);
      return;
    }

    var x = lignes[i];
    var premier = x[COL['Premier échange']] instanceof Date ? x[COL['Premier échange']] : a.premier;
    var dernier = x[COL['Dernier échange']] instanceof Date ? x[COL['Dernier échange']] : a.dernier;
    if (!x[COL['Nom']] || (a.nom && a.dernier > dernier)) x[COL['Nom']] = a.nom || x[COL['Nom']];
    // Les mails lus maintenant sont plus récents (mise à jour quotidienne) ou plus
    // anciens (historique, lu du plus récent au plus ancien) que ceux déjà notés.
    x[COL['Objets récents']] = objetsRecents(a.objets, x[COL['Objets récents']], a.dernier > dernier);
    x[COL['Premier échange']] = a.premier < premier ? a.premier : premier;
    x[COL['Dernier échange']] = a.dernier > dernier ? a.dernier : dernier;
    x[COL['Échanges']] = (Number(x[COL['Échanges']]) || 0) + a.echanges;
    x[COL['Envoyés par CIBLE']] = (Number(x[COL['Envoyés par CIBLE']]) || 0) + a.envoyes;
    x[COL['Reçus de ce contact']] = (Number(x[COL['Reçus de ce contact']]) || 0) + a.recus;
    x[COL['Rôles']] = union_(x[COL['Rôles']], Object.keys(a.roles));
    x[COL['Boîtes CIBLE']] = union_(x[COL['Boîtes CIBLE']], Object.keys(a.boites));
    if (!x[COL['Statut CRM']] && reglages['Domaines clients (statut Client)'].indexOf(dom) !== -1) x[COL['Statut CRM']] = 'Client';
  });

  lignes.sort(function (p, q) { return (q[COL['Dernier échange']] || 0) - (p[COL['Dernier échange']] || 0); });
  return nb;
}

function union_(texte, valeurs) {
  var s = {};
  String(texte || '').split(',').map(function (v) { return v.trim(); }).filter(String).forEach(function (v) { s[v] = true; });
  valeurs.forEach(function (v) { s[v] = true; });
  return Object.keys(s).sort().join(', ');
}

// ══════════════════════════════════════════════════════════════
// GOOGLE SHEETS, ÉTAT ET DÉCLENCHEURS
// ══════════════════════════════════════════════════════════════

function fusionner_(agg, reglages) {
  if (!Object.keys(agg).length) return 0;
  // Verrou du script (et non du document) : il existe aussi pour un projet
  // créé hors du Sheets. Plusieurs collaborateurs peuvent écrire en même temps.
  var verrou = LockService.getScriptLock();
  verrou.waitLock(60000);
  try {
    var f = feuille_(CONFIG.FEUILLE_CONTACTS);
    var valeurs = f.getDataRange().getValues();
    var lignes = valeurs.length > 1 ? valeurs.slice(1) : [];
    var nb = fusionnerLignes(lignes, agg, reglages);
    f.getRange(2, 1, Math.max(f.getMaxRows() - 1, 1), ENTETES.length).clearContent();
    if (f.getMaxRows() < lignes.length + 1) f.insertRowsAfter(f.getMaxRows(), lignes.length + 1 - f.getMaxRows());
    if (lignes.length) {
      f.getRange(2, 1, lignes.length, ENTETES.length).setValues(lignes);
      f.getRange(2, COL['Premier échange'] + 1, lignes.length, 2).setNumberFormat('dd/mm/yyyy');
    }
    return nb;
  } finally {
    verrou.releaseLock();
  }
}

function preparerFeuilles_() {
  var c = feuille_(CONFIG.FEUILLE_CONTACTS);
  if (c.getLastRow() === 0) {
    c.getRange(1, 1, 1, ENTETES.length).setValues([ENTETES]).setFontWeight('bold').setBackground('#E6E6E6');
    c.setFrozenRows(1);
    c.getRange(1, COL['Statut CRM'] + 1, 1, 2).setBackground('#FFF3CD');
  }
  var r = feuille_(CONFIG.FEUILLE_REGLAGES);
  if (r.getLastRow() === 0) {
    var titres = Object.keys(REGLAGES_DEFAUT);
    r.getRange(1, 1, 1, titres.length).setValues([titres]).setFontWeight('bold').setBackground('#E6E6E6');
    titres.forEach(function (t, i) {
      var v = REGLAGES_DEFAUT[t];
      if (v.length) r.getRange(2, i + 1, v.length, 1).setValues(v.map(function (x) { return [x]; }));
    });
    r.setFrozenRows(1);
  }
  var j = feuille_(CONFIG.FEUILLE_JOURNAL);
  if (j.getLastRow() === 0) j.getRange(1, 1, 1, 3).setValues([['Date', 'Boîte', 'Résultat']]).setFontWeight('bold');
}

function lireReglages_() {
  preparerFeuilles_();
  var v = feuille_(CONFIG.FEUILLE_REGLAGES).getDataRange().getValues();
  var reglages = {};
  Object.keys(REGLAGES_DEFAUT).forEach(function (t) { reglages[t] = []; });
  var titres = v[0] || [];
  titres.forEach(function (t, col) {
    if (!(t in reglages)) return;
    for (var i = 1; i < v.length; i++) {
      var x = String(v[i][col] || '').trim().toLowerCase().replace(/^@/, '');
      if (x) reglages[t].push(x);
    }
  });
  return reglages;
}

function feuille_(nom) {
  var ss = classeur_();
  return ss.getSheetByName(nom) || ss.insertSheet(nom);
}

/**
 * Le Google Sheets du script. Si le projet a été créé depuis
 * script.google.com (et non depuis le Sheets : Extensions → Apps Script),
 * le script crée son propre fichier « Contacts CIBLE » et le réutilise.
 */
function classeur_() {
  var ss = SpreadsheetApp.getActive();
  if (ss) return ss;
  var props = PropertiesService.getScriptProperties();
  var id = props.getProperty('CLASSEUR_ID');
  if (id) return SpreadsheetApp.openById(id);
  ss = SpreadsheetApp.create('Contacts CIBLE');
  props.setProperty('CLASSEUR_ID', ss.getId());
  console.log('Fichier créé : ' + ss.getUrl());
  return ss;
}

function maBoite_() { return (Session.getActiveUser().getEmail() || 'boîte inconnue').toLowerCase(); }

function adressesPersonnelles_() {
  var moi = {};
  moi[maBoite_()] = true;
  GmailApp.getAliases().forEach(function (a) { moi[a.toLowerCase()] = true; });
  return moi;
}

function cleEtat_() { return 'etat_' + classeur_().getId(); }
function lireEtat_() { var s = PropertiesService.getUserProperties().getProperty(cleEtat_()); return s ? JSON.parse(s) : null; }
function ecrireEtat_(e) { PropertiesService.getUserProperties().setProperty(cleEtat_(), JSON.stringify(e)); }

function planifierSuite_(minutes) {
  supprimerDeclencheurs_('traiterLot');
  ScriptApp.newTrigger('traiterLot').timeBased().after(minutes * 60 * 1000).create();
}

function supprimerDeclencheurs_(fonction) {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === fonction) ScriptApp.deleteTrigger(t);
  });
}

function arreterMiseAJourQuotidienne_(silencieux) {
  supprimerDeclencheurs_('miseAJourQuotidienne');
  if (!silencieux) alerte_('Mise à jour quotidienne arrêtée pour ' + maBoite_() + '.');
}

function journal_(boite, texte) {
  feuille_(CONFIG.FEUILLE_JOURNAL).appendRow([new Date(), boite, texte]);
  console.log(boite + ' : ' + texte + ' — fichier : ' + classeur_().getUrl()); // visible dans le journal d'exécution
}

function alerte_(texte) {
  console.log(texte + ' — fichier : ' + classeur_().getUrl()); // lancé depuis l'éditeur : visible dans le journal d'exécution
  try { SpreadsheetApp.getUi().alert(texte); } catch (e) { /* exécution automatique ou projet hors Sheets : pas d'écran */ }
}

// Pour les tests hors Google (Node) — ignoré par Apps Script.
if (typeof module !== 'undefined') {
  module.exports = { parserAdresses: parserAdresses, noterContact: noterContact, cumuler: cumuler, estExclue: estExclue,
    entrepriseDuDomaine: entrepriseDuDomaine, fusionnerLignes: fusionnerLignes, analyserMessage_: analyserMessage_,
    nettoyerObjet: nettoyerObjet, objetsRecents: objetsRecents,
    ENTETES: ENTETES, COL: COL, REGLAGES_DEFAUT: REGLAGES_DEFAUT };
}
