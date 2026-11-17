/**
 * BAROMÈTRE CLIENTS → PANORA (2026-11-17)
 *
 * Relie le questionnaire Google du baromètre à Panora :
 *   · à chaque réponse, Panora est prévenu — le NOM DE LA STRUCTURE et
 *     l'HEURE seulement : les réponses restent dans la feuille Google.
 *     Panora sait ainsi qui a répondu et ne relance que les autres ;
 *   · chaque heure, le script lit la date limite dans Panora et FERME LE
 *     FORMULAIRE tout seul quand elle est passée (et le rouvre si la date
 *     a été prolongée dans Panora, seulement s'il l'avait fermé lui-même) ;
 *   · une réponse que Panora n'a pas reçue (serveur injoignable) est
 *     renvoyée à l'heure suivante ;
 *   · la « Durée estimée » de la description du formulaire est alignée
 *     sur celle annoncée dans le mail (Panora, BAROMETRE_DUREE).
 *
 * INSTALLATION (une fois, avec le compte Google qui possède le formulaire)
 *   1. Ouvrir le projet Apps Script qui a créé le questionnaire
 *      (script.google.com). Fichier → « + » → Script, le nommer « panora »,
 *      y coller TOUT ce fichier, enregistrer. Ne rien effacer d'autre.
 *   2. ⚙ Paramètres du projet → Propriétés du script → ajouter :
 *        PANORA_URL    https://panora-cible.com
 *        PANORA_JETON  le jeton BAROMETRE_JETON_GOOGLE (donné par l'admin)
 *      Le jeton n'est JAMAIS écrit dans ce fichier ni dans une cellule.
 *   3. En haut, choisir « bpInstaller » → Exécuter, autoriser si demandé.
 *      Le journal d'exécution dit : Panora joint, date limite, déclencheurs.
 *   4. Si des réponses sont arrivées avant l'installation : « bpRattraper »
 *      → Exécuter (sans risque de doublon : Panora reconnaît une réponse
 *      déjà reçue).
 *
 * Côté Panora : BAROMETRE_JETON_GOOGLE (même jeton) sur le serveur.
 * Guide complet : docs/A_TESTER.md § 35.
 *
 * Tous les noms commencent par « bp » / « BP_ » : les autres fichiers du
 * projet (création du questionnaire, liens) ne sont pas touchés.
 */

var BP = {
  // Feuille de réponses du questionnaire (journal de creerBarometre, 13/11).
  ID_FEUILLE: '1_2p91Hk936xZ4Zf-dh1rGIFrbLiEi-bB8ksBGN3k-LE',
  QUESTION_STRUCTURE: 'Votre structure',
  // bpRattraper ignore les réponses plus anciennes (essais du 13 au 16/11).
  DEPUIS: '2026-11-17',
  MESSAGE_CLOS: 'Le baromètre clients est clos. Merci de l’intérêt que vous nous portez.',
  ATTENTE_MS: 1100, // Panora accepte 60 appels par minute
};

/** Pose les deux déclencheurs (une fois). Peut être relancé sans doublon. */
function bpInstaller() {
  var etat = bpAppel_('get', '/api/barometre/etat');
  if (etat.code !== 200 || !etat.json) {
    throw new Error(etat.code === 404
      ? 'Panora refuse le jeton (ou PANORA_URL est fausse). Vérifiez les propriétés du script.'
      : 'Panora ne répond pas (code ' + etat.code + ').');
  }

  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (['bpAChaqueReponse', 'bpChaqueHeure'].indexOf(t.getHandlerFunction()) !== -1) ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('bpAChaqueReponse').forSpreadsheet(BP.ID_FEUILLE).onFormSubmit().create();
  ScriptApp.newTrigger('bpChaqueHeure').timeBased().everyHours(1).create();

  bpChaqueHeure();
  console.log('Panora joint : baromètre ' + etat.json.edition + ', réponses jusqu’au ' + etat.json.date_limite
    + (etat.json.clos ? ' (CLOS)' : '') + '. Déclencheurs posés : à chaque réponse, et chaque heure.');
}

/** Déclencheur « à l'envoi du formulaire » : prévient Panora. */
function bpAChaqueReponse(e) {
  var structure = ((e && e.namedValues && e.namedValues[BP.QUESTION_STRUCTURE]) || [''])[0];
  var quand = e && e.range ? e.range.getValues()[0][0] : new Date();
  var reponse = bpReponse_(structure, quand instanceof Date ? quand : new Date());

  if (!bpEnvoyer_(reponse)) bpMettreDeCote_(reponse);
}

/** Déclencheur horaire : renvoie ce qui a échoué, ferme (ou rouvre) le formulaire. */
function bpChaqueHeure() {
  bpRenvoyerEnAttente_();

  var props = PropertiesService.getScriptProperties();
  var etat = bpAppel_('get', '/api/barometre/etat');
  var fin;
  if (etat.code === 200 && etat.json) {
    fin = etat.json.fin;
    props.setProperty('BP_FIN', fin); // mémorisée : sert si Panora ne répond pas
  } else {
    fin = props.getProperty('BP_FIN');
    console.warn('Panora ne répond pas (code ' + etat.code + ') : date limite mémorisée ' + (fin || 'inconnue') + '.');
  }
  if (!fin) return;

  var form = FormApp.openByUrl(SpreadsheetApp.openById(BP.ID_FEUILLE).getFormUrl());
  if (etat.json && etat.json.duree) bpAlignerDuree_(form, etat.json.duree);
  var clos = new Date() > new Date(fin);

  if (clos && form.isAcceptingResponses()) {
    form.setCustomClosedFormMessage(BP.MESSAGE_CLOS);
    form.setAcceptingResponses(false);
    props.setProperty('BP_FERME_PAR_SCRIPT', '1');
    console.log('Date limite passée : formulaire fermé.');
  } else if (!clos && !form.isAcceptingResponses() && props.getProperty('BP_FERME_PAR_SCRIPT') === '1') {
    form.setAcceptingResponses(true);
    props.deleteProperty('BP_FERME_PAR_SCRIPT');
    console.log('Date limite prolongée dans Panora : formulaire rouvert.');
  }
}

/** Renvoie à Panora toutes les réponses depuis BP.DEPUIS (sans doublon côté Panora). */
function bpRattraper() {
  var feuille = bpFeuilleReponses_();
  var lignes = feuille.getDataRange().getValues();
  var entetes = lignes.shift();
  var col = entetes.indexOf(BP.QUESTION_STRUCTURE);
  if (col === -1) throw new Error('Colonne « ' + BP.QUESTION_STRUCTURE + ' » introuvable dans ' + feuille.getName() + '.');

  var depuis = new Date(BP.DEPUIS + 'T00:00:00Z');
  var bilan = { envoyees: 0, reconnues: 0, echecs: 0, ignorees: 0 };
  lignes.forEach(function (l) {
    if (!(l[0] instanceof Date) || l[0] < depuis) { bilan.ignorees++; return; }
    var r = bpReponse_(l[col], l[0]);
    var rep = bpAppel_('post', '/api/barometre/reponse', r);
    if (rep.code === 200 && rep.json) {
      bilan.envoyees++;
      if (rep.json.reconnu) bilan.reconnues++;
    } else {
      bilan.echecs++;
    }
    Utilities.sleep(BP.ATTENTE_MS);
  });
  console.log('Rattrapage : ' + bilan.envoyees + ' envoyée(s) dont ' + bilan.reconnues + ' reconnue(s), '
    + bilan.echecs + ' échec(s), ' + bilan.ignorees + ' ignorée(s) (avant le ' + BP.DEPUIS + ').');
}

// ── Outils ──────────────────────────────────────────────────────────

/** « Durée estimée : 7 à 9 minutes. » → « Durée estimée : 5 minutes. » (mail et formulaire disent la même chose). */
function bpAlignerDuree_(form, duree) {
  var description = form.getDescription();
  var nouvelle = description.replace(/Durée estimée : [^.\n]*\./, 'Durée estimée : ' + duree + '.');
  if (nouvelle !== description) {
    form.setDescription(nouvelle);
    console.log('Description du formulaire : durée estimée = ' + duree + '.');
  }
}

/** Référence stable : la même réponse donne toujours la même (renvoi sans doublon). */
function bpReponse_(structure, quand) {
  structure = String(structure || '').trim();
  var iso = quand.toISOString();
  var empreinte = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, iso + '|' + structure, Utilities.Charset.UTF_8)
    .map(function (b) { return ('0' + (b & 0xff).toString(16)).slice(-2); }).join('');
  return { reference: empreinte, structure: structure, repondu_le: iso };
}

function bpEnvoyer_(reponse) {
  try {
    var rep = bpAppel_('post', '/api/barometre/reponse', reponse);
    if (rep.code === 200) return true;
    console.warn('Panora a refusé la réponse « ' + reponse.structure + ' » (code ' + rep.code + ').');
  } catch (err) {
    console.warn('Panora injoignable : ' + err);
  }
  return false;
}

function bpMettreDeCote_(reponse) {
  var props = PropertiesService.getScriptProperties();
  var liste = JSON.parse(props.getProperty('BP_EN_ATTENTE') || '[]');
  liste.push(reponse);
  props.setProperty('BP_EN_ATTENTE', JSON.stringify(liste.slice(-200)));
}

function bpRenvoyerEnAttente_() {
  var props = PropertiesService.getScriptProperties();
  var liste = JSON.parse(props.getProperty('BP_EN_ATTENTE') || '[]');
  if (!liste.length) return;
  var reste = liste.filter(function (r) {
    var ok = bpEnvoyer_(r);
    Utilities.sleep(BP.ATTENTE_MS);
    return !ok;
  });
  props.setProperty('BP_EN_ATTENTE', JSON.stringify(reste));
  console.log((liste.length - reste.length) + ' réponse(s) renvoyée(s) à Panora, ' + reste.length + ' encore en attente.');
}

function bpFeuilleReponses_() {
  var feuilles = SpreadsheetApp.openById(BP.ID_FEUILLE).getSheets().filter(function (f) { return f.getFormUrl(); });
  if (!feuilles.length) throw new Error('Aucun onglet de réponses relié au formulaire dans la feuille.');
  return feuilles[0];
}

function bpAppel_(methode, chemin, corps) {
  var props = PropertiesService.getScriptProperties();
  var url = (props.getProperty('PANORA_URL') || '').replace(/\/+$/, '');
  var jeton = props.getProperty('PANORA_JETON');
  if (!url || !jeton) throw new Error('Propriétés du script manquantes : PANORA_URL et PANORA_JETON (⚙ Paramètres du projet).');

  var options = {
    method: methode,
    muteHttpExceptions: true,
    headers: { Authorization: 'Bearer ' + jeton, Accept: 'application/json' }, // jamais le jeton dans l'adresse
  };
  if (corps) { options.contentType = 'application/json'; options.payload = JSON.stringify(corps); }
  var r = UrlFetchApp.fetch(url + chemin, options);
  var json = null;
  try { json = JSON.parse(r.getContentText()); } catch (err) { /* page d'erreur HTML */ }
  return { code: r.getResponseCode(), json: json };
}
