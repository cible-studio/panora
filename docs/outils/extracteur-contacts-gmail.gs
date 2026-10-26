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
 * Dans les mails reçus, il relève aussi le téléphone et le poste indiqués
 * dans la signature de l'expéditeur : rien d'autre n'est gardé du texte.
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
  // Version du relevé des signatures : une boîte lue avec une version plus
  // ancienne est relue une fois, pour ses signatures seulement (téléphones, poste).
  SIGNATURES: 2,
};

var ENTETES = [
  'E-mail', 'Nom', 'Entreprise (déduite)', 'Domaine', "Type d'adresse",
  'Premier échange', 'Dernier échange', 'Échanges', 'Envoyés par CIBLE', 'Reçus de ce contact',
  'Rôles', 'Boîtes CIBLE', 'Objets récents', 'Statut CRM', 'Notes',
  'Téléphones (signature)', 'Poste (signature)', // ajoutées en dernier : les fichiers déjà remplis gardent leurs colonnes
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
  // Standard et portables de CIBLE : jamais prêtés à un contact. Les numéros des
  // signatures CIBLE sont aussi appris seuls, dans les mails envoyés aux clients.
  'Numéros internes (exclus)': [],
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
    : e.rattrapage
      ? 'Historique terminé. Relecture des signatures (téléphones, postes) en cours : ' + e.rattrapage.curseur + ' conversations relues.'
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
  try {
    var debut = Date.now();
    var termine = traiterLot_(debut);
    // Boîte lue avant le relevé des postes : ses signatures sont relues, une fois.
    return termine ? rattraperSignatures_(debut) : false;
  } finally { verrou.releaseLock(); }
}

function traiterLot_(debut) {
  var etat = lireEtat_() || { phase: 'complet', borne: Date.now(), curseur: 0, sig: CONFIG.SIGNATURES };
  if (etat.phase === 'quotidien' && etat.curseur === 0) etat.borneQuotidienne = Date.now();

  var moi = adressesPersonnelles_();
  var reglages = lireReglages_();
  var boite = maBoite_();
  var agg = {};
  var internes = { cles: {}, envoisLus: 0 }; // numéros de CIBLE vus dans nos signatures
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
          if (garder(m.getDate().getTime())) analyserMessage_(m, moi, page, boite, reglages, internes);
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
  var nb = fusionner_(agg, reglages, internes.cles);

  if (termine) {
    // Le même objet est gardé : il porte aussi la version des signatures et leur rattrapage.
    etat.depuis = etat.phase === 'complet' ? etat.borne : etat.borneQuotidienne;
    etat.phase = 'quotidien';
    etat.curseur = 0;
    delete etat.borne; delete etat.borneQuotidienne;
    supprimerDeclencheurs_('traiterLot');
  } else {
    // Quota Google atteint ou incident : on réessaie dans une heure.
    planifierSuite_(erreur ? 60 : 1);
  }
  ecrireEtat_(etat);
  if (lus || !termine) { // rien de nouveau : pas de ligne au Journal
    journal_(boite, lus + ' conversation(s) lue(s), ' + nb + ' contact(s) mis à jour'
      + (termine ? ' — terminé.' : erreur ? ' — pause (' + erreur.message + '), reprise dans 1 heure.' : ' — suite dans 1 minute.'));
  }
  return termine;
}

/**
 * Boîte lue avant le relevé des postes : on relit son historique pour les
 * signatures seulement (téléphones, poste). Aucun compteur n'est touché et
 * aucun contact n'est créé : les lignes existantes sont complétées.
 * @return {boolean} true s'il ne reste rien à relire.
 */
function rattraperSignatures_(debut) {
  var etat = lireEtat_();
  if (!etat || etat.phase !== 'quotidien' || etat.sig === CONFIG.SIGNATURES) return true;
  if (Date.now() - debut > CONFIG.DUREE_MAX_MS - 30000) { planifierSuite_(1); return false; }
  var r = etat.rattrapage || (etat.rattrapage = { borne: Date.now(), curseur: 0 });
  var moi = adressesPersonnelles_();
  var reglages = lireReglages_();
  var boite = maBoite_();
  var agg = {};
  var internes = { cles: {}, envoisLus: 0 };
  var fini = false;
  var erreur = null;
  var lus = 0;

  while (Date.now() - debut < CONFIG.DUREE_MAX_MS) {
    try {
      var fils = GmailApp.search('before:' + Math.floor(r.borne / 1000), r.curseur, CONFIG.FILS_PAR_PAGE);
      if (!fils.length) { fini = true; break; }
      var page = {};
      fils.forEach(function (fil) {
        fil.getMessages().forEach(function (m) {
          if (m.getDate().getTime() < r.borne) analyserSignature_(m, moi, page, reglages, internes);
        });
      });
      cumulerSignatures(agg, page);
      r.curseur += fils.length;
      lus += fils.length;
    } catch (e) {
      erreur = e;
      break;
    }
  }

  var nb = fusionner_(agg, reglages, internes.cles, true);
  if (fini) { etat.sig = CONFIG.SIGNATURES; delete etat.rattrapage; }
  else { planifierSuite_(erreur ? 60 : 1); }
  ecrireEtat_(etat);
  journal_(boite, 'Signatures : ' + lus + ' conversation(s) relue(s), ' + nb + ' contact(s) complété(s)'
    + (fini ? ' — terminé.' : erreur ? ' — pause (' + erreur.message + '), reprise dans 1 heure.' : ' — suite dans 1 minute.'));
  return fini;
}

function analyserMessage_(m, moi, agg, boite, reglages, internes) {
  var date = m.getDate();
  var objet = m.getSubject() || '';
  var exp = parserAdresses(m.getFrom())[0];
  var envoyeParMoi = !!(exp && moi[exp.email]);
  var vus = {};
  var versExterieur = false;

  if (exp && !envoyeParMoi) noterContact(agg, exp, date, objet, 'De', boite, 'recu', vus);

  var champs = [['À', m.getTo()], ['CC', m.getCc()], ['CCI', envoyeParMoi ? m.getBcc() : '']];
  champs.forEach(function (c) {
    parserAdresses(c[1]).forEach(function (a) {
      if (moi[a.email]) return;
      noterContact(agg, a, date, objet, c[0], boite, envoyeParMoi ? 'envoye' : 'copie', vus);
      if (reglages && !estExclue(a.email, reglages)) versExterieur = true;
    });
  });

  // Signatures (l'analyse fournit toujours les réglages).
  if (reglages && exp) noterSignature_(m, exp, envoyeParMoi, versExterieur, date, agg, reglages, internes);
}

/** Rattrapage : seulement la signature (téléphones, poste), sans rien compter. */
function analyserSignature_(m, moi, agg, reglages, internes) {
  var exp = parserAdresses(m.getFrom())[0];
  if (!exp) return;
  var date = m.getDate();
  var envoyeParMoi = !!moi[exp.email];
  var versExterieur = envoyeParMoi && parserAdresses(m.getTo() + ', ' + m.getCc()).some(function (a) {
    return !moi[a.email] && !estExclue(a.email, reglages);
  });
  if (!envoyeParMoi && !estExclue(exp.email, reglages)) {
    var e = agg[exp.email] || (agg[exp.email] = { email: exp.email, nom: exp.nom, dernier: date, telephones: {}, postes: {} });
    if (date > e.dernier) e.dernier = date;
  }
  noterSignature_(m, exp, envoyeParMoi, versExterieur, date, agg, reglages, internes);
}

/**
 * Téléphones et poste de la signature. Mail reçu : ceux de l'expéditeur.
 * Mail envoyé à l'extérieur (pas un récap interne) : notre propre signature,
 * dont les numéros sont ceux de CIBLE, jamais prêtés à un contact.
 */
function noterSignature_(m, exp, envoyeParMoi, versExterieur, date, agg, reglages, internes) {
  if (envoyeParMoi) {
    if (!versExterieur || internes.envoisLus >= 20) return;
    internes.envoisLus++;
    telephonesDuTexte(corpsDuMessage_(m)).forEach(function (n) { internes.cles[cleTelephone(n)] = true; });
    return;
  }
  var e = agg[exp.email];
  if (!e || estExclue(exp.email, reglages)) return;
  if (Object.keys(e.telephones).length >= 3 && Object.keys(e.postes).length) return;
  var corps = corpsDuMessage_(m);
  telephonesDuTexte(corps).forEach(function (n) { if (!e.telephones[n] || date > e.telephones[n]) e.telephones[n] = date; });
  var poste = posteDuTexte(corps, exp.nom, exp.email);
  if (poste && (!e.postes[poste] || date > e.postes[poste])) e.postes[poste] = date;
}

function corpsDuMessage_(m) {
  try { return m.getPlainBody() || ''; } catch (e) { return ''; }
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
      echanges: 0, envoyes: 0, recus: 0, roles: {}, boites: {}, objets: {}, telephones: {}, postes: {} };
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
    fusionnerDates_(e, p, 'telephones');
    fusionnerDates_(e, p, 'postes');
  });
}

/** Cumul du rattrapage des signatures : seulement les téléphones et le poste. */
function cumulerSignatures(agg, page) {
  Object.keys(page).forEach(function (email) {
    var p = page[email], e = agg[email];
    if (!e) { agg[email] = p; return; }
    if (p.dernier > e.dernier) e.dernier = p.dernier;
    fusionnerDates_(e, p, 'telephones');
    fusionnerDates_(e, p, 'postes');
  });
}

/** { valeur: date } : garde la date la plus récente de chaque valeur. */
function fusionnerDates_(e, p, cle) {
  Object.keys(p[cle] || {}).forEach(function (k) { if (!e[cle][k] || p[cle][k] > e[cle][k]) e[cle][k] = p[cle][k]; });
}

/** « RE: TR: Devis campagne ORANGE » → « Devis campagne ORANGE ». */
function nettoyerObjet(objet) {
  var s = String(objet || '').trim();
  var avant;
  do { avant = s; s = s.replace(/^(re|tr|fw|fwd|réf|ref)\s*(\[\d+\])?\s*:\s*/i, '').trim(); } while (s !== avant);
  return s.slice(0, 120);
}

/** Les 5 (ou max) objets les plus récents, sans doublon, séparés par « | ». */
function objetsRecents(nouveaux, existants, nouveauxDabord, max) {
  var neufs = Object.keys(nouveaux).sort(function (a, b) { return nouveaux[b] - nouveaux[a]; });
  var anciens = String(existants || '').split(' | ').map(function (o) { return o.trim(); }).filter(String);
  var tous = nouveauxDabord ? neufs.concat(anciens) : anciens.concat(neufs);
  var vus = {};
  return tous.filter(function (o) { var k = o.toLowerCase(); if (vus[k]) return false; vus[k] = true; return true; }).slice(0, max || 5).join(' | ');
}

/**
 * Fin du texte écrit par l'expéditeur, là où est sa signature : sans
 * l'historique cité (il contient les signatures des autres, dont CIBLE).
 * @return {string[]} les 20 dernières lignes non vides.
 */
function zoneSignature_(texte) {
  var t = '\n' + String(texte || '').replace(/\r/g, '');
  [
    /\n[ \t]*(Le|On)\s[^\n]{0,250}(\n[^\n]{0,250})?\s(a\s+écrit|wrote)\s*:/i,               // Gmail, Apple Mail
    /\n[ \t]*\*?(De|From)\s?:[^\n]*\n[^\n]*(Envoyé|Sent|Date|À|To|Objet|Subject)\s?:/i,  // Outlook
    /\n[ \t]*-{2,}[ \t]*(Original Message|Message d'origine|Forwarded message|Message transféré)/i,
    /\n[ \t]*_{10,}/,
  ].forEach(function (re) { var m = re.exec(t); if (m) t = t.slice(0, m.index); });
  return t.split('\n').filter(function (l) { return l.trim() && !/^\s*>/.test(l); }).slice(-20);
}

/**
 * Numéros de la signature.
 * Côte d'Ivoire : 10 chiffres (01, 05, 07, 21, 25, 27), rendus « +225 07 07 12 34 56 ».
 * Étranger : seulement avec l'indicatif (+33…, 0033…). Les numéros ivoiriens
 * à 8 chiffres sont ignorés : ils n'existent plus depuis 2021.
 */
function telephonesDuTexte(texte) {
  var fin = zoneSignature_(texte).join('\n');

  var vus = {}, numeros = [];
  var garder = function (n) { if (!vus[n]) { vus[n] = true; numeros.push(n); } };
  var m;
  var ci = /(?:(?:\+|\b00)\s?\(?225\)?|\(\+?225\))[ .\-]*((?:0[157]|2[157])(?:[ .\-]?\d){8})(?!\d)|(?<![\d+])((?:0[157]|2[157])(?:[ .\-]?\d){8})(?!\d)/g;
  while ((m = ci.exec(fin)) !== null) {
    var brut = m[1] || m[2];
    // Un seul type de séparateur : « 25.10.2026 10:15 » est une date, pas un numéro.
    if (brut.replace(/\d/g, '').split('').filter(function (c, i, a) { return a.indexOf(c) === i; }).length > 1) continue;
    garder('+225 ' + brut.replace(/\D/g, '').replace(/(\d{2})(?=\d)/g, '$1 '));
  }
  var etranger = /(?:\+|\b00)(?!\s?\(?225)\d[\d .\-()]{6,16}\d(?!\d)/g;
  while ((m = etranger.exec(fin)) !== null) {
    var chiffres = m[0].replace(/^(\+|00)/, '').replace(/\D/g, '');
    if (chiffres.length >= 8 && chiffres.length <= 15 && chiffres.charAt(0) !== '0') garder('+' + chiffres);
  }
  return numeros;
}

/** Clé de comparaison : les 8 derniers chiffres (Sheets retire parfois le 0 de tête). */
function cleTelephone(n) { return String(n).replace(/\D/g, '').slice(-8); }

// Formule de politesse : la signature commence juste après (« Cordialement, », « Best regards »…).
var POLITESSE = /^(bien |tres )?(cordialement|cordiales salutations|salutations|sinceres salutations|bien a vous|bonne (journee|soiree|reception)|merci|regards|best|kind regards|warm regards|thanks|thank you|sincerely|respectueusement|a (tres )?bientot)\b/;
// Mots de métier d'une ligne de poste (sans accents).
var MOTS_POSTE = /\b(directeur|directrice|director|dg|dga|pdg|ceo|coo|cfo|cmo|cto|cco|president|presidente|vice-?president|vp|gerant|gerante|manager|responsable|head of|chef|cheffe|charge|chargee|assistant|assistante|attache|attachee|conseiller|conseillere|consultant|consultante|coordinateur|coordinatrice|coordinator|superviseur|superviseure|supervisor|account|commercial|commerciale|comptable|controleur|controleuse|juriste|avocat|avocate|planner|planneur|buyer|acheteur|acheteuse|officer|specialist|specialiste|secretaire|secretary|administrateur|administratrice|analyste|analyst|ingenieur|engineer|designer|graphiste|fondateur|fondatrice|founder|cofondateur|co-fondateur|owner|associe|associee|delegue|deleguee|agent|chief|lead|tresorier|tresoriere|auditeur|auditrice|caissier|caissiere|stagiaire|intern)\b/;
// Parties d'adresse trop générales pour reconnaître le nom de quelqu'un.
var ADRESSES_GENERIQUES = ['info', 'infos', 'contact', 'contacts', 'commercial', 'marketing', 'direction', 'admin', 'service', 'sales', 'hello',
  'team', 'secretariat', 'compta', 'comptabilite', 'support', 'accueil', 'communication', 'office', 'mail', 'agence', 'groupe', 'group'];

/** « Koné » → « kone » : pour comparer sans accents ni majuscules. */
function simplifier_(s) {
  return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

/**
 * Poste lu dans la signature : sur la ligne du nom ou les deux suivantes,
 * sinon la première ligne courte de la signature qui contient un mot de métier.
 * Vide si rien de sûr : une phrase du message n'est jamais prise pour un poste.
 */
function posteDuTexte(texte, nom, email) {
  var lignes = zoneSignature_(texte);
  var debut = 0;
  lignes.forEach(function (l, i) { var s = simplifier_(l).trim(); if (s.length < 60 && POLITESSE.test(s)) debut = i + 1; });
  var zone = lignes.slice(debut);
  var mots = (simplifier_(nom) + ' ' + simplifier_(String(email || '').split('@')[0])).split(/[^a-z]+/)
    .filter(function (m) { return m.length >= 3 && ADRESSES_GENERIQUES.indexOf(m) === -1; });
  var iNom = -1;
  zone.forEach(function (l, i) {
    if (iNom < 0 && mots.some(function (m) { return new RegExp('\\b' + m + '\\b').test(simplifier_(l)); })) iNom = i;
  });
  var candidats = iNom >= 0 ? zone.slice(iNom, iNom + 3) : zone;
  for (var i = 0; i < candidats.length; i++) {
    var morceaux = candidats[i].split(/\s+[|•–—-]\s+|\s*\|\s*|\t|\s{3,}/);
    for (var j = 0; j < morceaux.length; j++) {
      var p = morceaux[j].replace(/^[\s*•·|\-–—]+|[\s*•·|\-–—,]+$/g, '').replace(/\s+/g, ' ');
      if (estPoste_(p)) return p;
    }
  }
  return '';
}

function estPoste_(p) {
  var s = simplifier_(p);
  if (s.length < 3 || s.length > 60 || p.split(' ').length > 8) return false;
  if (/@|https?:|www\.|\d{3}/.test(s)) return false;
  if (/^(tel|cel|cell|mob|mobile|fax|e-?mail|site|web|adresse|address|bp|b\.p|rccm|ncc|cc |boite|whatsapp)\b/.test(s)) return false;
  if (/[.?!:;]$/.test(s) || POLITESSE.test(s)) return false;
  return MOTS_POSTE.test(s);
}

function sansInternes_(texte, internes) {
  return String(texte || '').split(' | ').filter(function (n) { return n.trim() && !internes[cleTelephone(n)]; }).join(' | ');
}

/** Les 3 numéros les plus récents, sans ceux de CIBLE. */
function telephonesRecents_(nouveaux, existants, nouveauxDabord, internes) {
  var n = {};
  Object.keys(nouveaux || {}).forEach(function (k) { if (!internes[cleTelephone(k)]) n[k] = nouveaux[k]; });
  return objetsRecents(n, sansInternes_(existants, internes), nouveauxDabord, 3);
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
function fusionnerLignes(lignes, agg, reglages, internes, signaturesSeules) {
  internes = internes || {};
  var TEL = COL['Téléphones (signature)'], POSTE = COL['Poste (signature)'];
  var index = {};
  lignes.forEach(function (l, i) { index[String(l[COL['E-mail']]).toLowerCase()] = i; });
  var nb = 0;

  Object.keys(agg).forEach(function (email) {
    if (estExclue(email, reglages)) return;
    var a = agg[email];
    var dom = domaineDe(email);
    var i = index[email];

    if (signaturesSeules) {
      // Rattrapage : on complète les lignes existantes, sans rien compter ni créer.
      if (i === undefined) return;
      var y = lignes[i];
      var d = y[COL['Dernier échange']] instanceof Date ? y[COL['Dernier échange']] : a.dernier;
      var avant = y[TEL] + '§' + y[POSTE];
      y[TEL] = telephonesRecents_(a.telephones, y[TEL], a.dernier > d, internes);
      y[POSTE] = objetsRecents(a.postes, y[POSTE], a.dernier > d, 1);
      if (y[TEL] + '§' + y[POSTE] !== avant) nb++;
      return;
    }
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
      l[TEL] = telephonesRecents_(a.telephones, '', true, internes);
      l[POSTE] = objetsRecents(a.postes, '', true, 1);
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
    x[TEL] = telephonesRecents_(a.telephones, x[TEL], a.dernier > dernier, internes);
    x[POSTE] = objetsRecents(a.postes, x[POSTE], a.dernier > dernier, 1);
    x[COL['Premier échange']] = a.premier < premier ? a.premier : premier;
    x[COL['Dernier échange']] = a.dernier > dernier ? a.dernier : dernier;
    x[COL['Échanges']] = (Number(x[COL['Échanges']]) || 0) + a.echanges;
    x[COL['Envoyés par CIBLE']] = (Number(x[COL['Envoyés par CIBLE']]) || 0) + a.envoyes;
    x[COL['Reçus de ce contact']] = (Number(x[COL['Reçus de ce contact']]) || 0) + a.recus;
    x[COL['Rôles']] = union_(x[COL['Rôles']], Object.keys(a.roles));
    x[COL['Boîtes CIBLE']] = union_(x[COL['Boîtes CIBLE']], Object.keys(a.boites));
    if (!x[COL['Statut CRM']] && reglages['Domaines clients (statut Client)'].indexOf(dom) !== -1) x[COL['Statut CRM']] = 'Client';
  });

  // Un numéro reconnu plus tard comme celui de CIBLE disparaît aussi des lignes déjà écrites.
  lignes.forEach(function (l) { if (l[TEL]) l[TEL] = sansInternes_(l[TEL], internes); });
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

function fusionner_(agg, reglages, internesLus, signaturesSeules) {
  if (!Object.keys(agg).length) return 0;
  // Verrou du script (et non du document) : il existe aussi pour un projet
  // créé hors du Sheets. Plusieurs collaborateurs peuvent écrire en même temps.
  var verrou = LockService.getScriptLock();
  verrou.waitLock(60000);
  try {
    // Numéros de CIBLE : appris par toutes les boîtes (propriétés du script) + « Réglages ».
    var props = PropertiesService.getScriptProperties();
    var internes = JSON.parse(props.getProperty('NUMEROS_INTERNES') || '{}');
    Object.keys(internesLus || {}).forEach(function (k) { internes[k] = true; });
    props.setProperty('NUMEROS_INTERNES', JSON.stringify(internes));
    reglages['Numéros internes (exclus)'].forEach(function (n) { var k = cleTelephone(n); if (k.length === 8) internes[k] = true; });

    var f = feuille_(CONFIG.FEUILLE_CONTACTS);
    var valeurs = f.getDataRange().getValues();
    // Une colonne ajoutée par une nouvelle version du script : les anciennes lignes sont plus courtes.
    var lignes = (valeurs.length > 1 ? valeurs.slice(1) : []).map(function (l) {
      while (l.length < ENTETES.length) l.push('');
      return l.slice(0, ENTETES.length);
    });
    var nb = fusionnerLignes(lignes, agg, reglages, internes, signaturesSeules);
    f.getRange(2, 1, Math.max(f.getMaxRows() - 1, 1), ENTETES.length).clearContent();
    if (f.getMaxRows() < lignes.length + 1) f.insertRowsAfter(f.getMaxRows(), lignes.length + 1 - f.getMaxRows());
    if (lignes.length) {
      f.getRange(2, COL['Téléphones (signature)'] + 1, lignes.length, 1).setNumberFormat('@'); // « +33… » ne doit pas devenir un nombre
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
  } else {
    // Une nouvelle version du script peut ajouter une colonne : on complète l'en-tête sans rien décaler.
    if (c.getMaxColumns() < ENTETES.length) c.insertColumnsAfter(c.getMaxColumns(), ENTETES.length - c.getMaxColumns());
    var entete = c.getRange(1, 1, 1, ENTETES.length).getValues()[0];
    ENTETES.forEach(function (t, i) {
      if (!entete[i]) c.getRange(1, i + 1).setValue(t).setFontWeight('bold').setBackground('#E6E6E6');
    });
  }
  var r = feuille_(CONFIG.FEUILLE_REGLAGES);
  var presents = r.getLastRow() === 0 ? [] : r.getRange(1, 1, 1, r.getLastColumn()).getValues()[0];
  Object.keys(REGLAGES_DEFAUT).forEach(function (t) {
    if (presents.indexOf(t) !== -1) return;
    var col = r.getLastColumn() + 1;
    r.getRange(1, col).setValue(t).setFontWeight('bold').setBackground('#E6E6E6');
    var v = REGLAGES_DEFAUT[t];
    if (v.length) r.getRange(2, col, v.length, 1).setValues(v.map(function (x) { return [x]; }));
  });
  r.setFrozenRows(1);
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
    nettoyerObjet: nettoyerObjet, objetsRecents: objetsRecents, telephonesDuTexte: telephonesDuTexte, cleTelephone: cleTelephone,
    posteDuTexte: posteDuTexte, analyserSignature_: analyserSignature_, cumulerSignatures: cumulerSignatures,
    ENTETES: ENTETES, COL: COL, REGLAGES_DEFAUT: REGLAGES_DEFAUT };
}
