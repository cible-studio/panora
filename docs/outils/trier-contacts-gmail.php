<?php
/**
 * TRI DES CONTACTS GMAIL — CIBLE (2026-10-26)
 *
 * Lit l'export Excel du Sheets « Contacts Gmail » (extracteur-contacts-gmail.gs)
 * et écrit, dans le même dossier, « Contacts triés.xlsx » :
 *   Clients probables / Prospects / À vérifier / Écartés (avec la raison).
 *
 *   php docs/outils/trier-contacts-gmail.php "docs/emails clients/CONTACTS GMAIL.xlsx"
 *
 * Les fichiers de contacts restent dans « docs/emails clients/ », ignoré par Git :
 * ce sont des données personnelles de clients.
 */
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ini_set('memory_limit', '1G');
$source = $argv[1] ?? __DIR__ . '/../emails clients/CONTACTS GMAIL.xlsx';
$sortie = $argv[2] ?? dirname($source) . '/Contacts triés.xlsx';
if (!is_file($source)) { fwrite(STDERR, "Fichier introuvable : $source\n"); exit(1); }

$feuille = IOFactory::load($source)->getSheetByName('Contacts');
if (!$feuille) { fwrite(STDERR, "Onglet « Contacts » absent de $source\n"); exit(1); }
$lignes = $feuille->toArray(null, true, false, false);
$H = array_flip(array_shift($lignes));
$v = fn ($r, $k) => trim((string) ($r[$H[$k]] ?? ''));

// Secteurs d'activité (2026-10-28), vérifiés en ligne : « secteurs.csv » à côté de l'export
// (domaine;nom;secteur;confiance;source), modifiable dans Excel. Mêmes libellés que les
// fiches clients de Panora (Client::SECTORS) : un libellé inconnu est ignoré et signalé.
$SECTEURS = [];
$fichierSecteurs = dirname($source) . '/secteurs.csv';
if (is_file($fichierSecteurs)) {
    $f = fopen($fichierSecteurs, 'r');
    $entete = array_map(fn ($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $c)), fgetcsv($f, 0, ';'));
    $inconnus = [];
    while (($l = fgetcsv($f, 0, ';')) !== false) {
        $l = array_combine($entete, array_pad($l, count($entete), ''));
        $dom = strtolower(trim($l['domaine'] ?? ''));
        if ($dom === '') continue;
        if (!in_array($l['secteur'], \App\Models\Client::SECTORS, true)) { $inconnus[] = "$dom ({$l['secteur']})"; continue; }
        $SECTEURS[$dom] = $l;
    }
    fclose($f);
    if ($inconnus) fwrite(STDERR, 'Secteurs inconnus de Panora, ignorés : ' . implode(', ', $inconnus) . "\n");
}

function sansAccent(string $s): string {
    return strtolower(strtr($s, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'e','È'=>'e','Ê'=>'e','à'=>'a','â'=>'a','À'=>'a','Â'=>'a',
        'î'=>'i','ï'=>'i','Î'=>'i','ô'=>'o','Ô'=>'o','ù'=>'u','û'=>'u','ü'=>'u','Û'=>'u','ç'=>'c','Ç'=>'c','²'=>'2']));
}
function domaineDans(string $dom, array $liste): bool {
    foreach ($liste as $x) if ($dom === $x || str_ends_with($dom, '.' . $x)) return true;
    return false;
}

// ══════════════════════════════════════════════════════════════
// RÈGLES
// ══════════════════════════════════════════════════════════════

$INTERNES = ['cible-ci.com', 'cible.ci'];
$PERSO = ['gmail.com','yahoo.fr','yahoo.com','hotmail.com','hotmail.fr','outlook.com','outlook.fr','live.fr','live.com','icloud.com',
    'aol.com','ymail.com','msn.com','me.com','gmx.fr','gmx.com','proton.me','protonmail.com','yahoo.co.uk','rocketmail.com',
    '163.com','126.com','qq.com','sina.com','yandex.ru','yandex.com','mail.ru','orange.fr','free.fr','laposte.net','sfr.fr','wanadoo.fr',
    'hotmail.be','live.be','outlook.be','yahoo.ca','yahoo.es','hotmail.es','yahoo.it','hotmail.it','aim.com','zoho.eu','mail.com'];
// Récaps internes de Panora (envoyés par studio@ à l'équipe) : leurs destinataires sont des collègues.
$MOTS_INTERNE = '/recap quotidien|synthese (hebdo|mensuelle|du|de la)|rapport quotidien|logiciel interne|progicia/';
// RH et administration du personnel.
$MOTS_RH = '/fin de contrat|contrat de travail|contrat d.?embauche|attestation de travail|certificat de travail|bulletins? de (paie|salaire)|salaire|'
    . 'demande de conge|\bcnps\b|\bcmu\b|mutuelle|assurance maladie/';
$MOTS_PANNEAU = '/panneau|affichage|campagne|dispo|devis|\bbat\b|\bpose\b|pige|reservation|cotation/';
// Outils et plateformes (abonnements, logiciels, réseaux sociaux) : jamais des clients.
$OUTILS = ['cake.com','elest.io','elestio.pro','asana.com','anthropic.com','cloudflare.com','zoho.com','zohodirectory.com','zohocorp.com',
    'pinterest.com','higgsfield.ai','stripe.com','amazon.com','aws.com','amazonaws.com','gamma.app','unsplash.com','hetzner.com','n8n.io',
    'oneflow.com','adobe.com','elementor.com','twilio.com','microsoft.com','office.com','outlook.office365.com','apple.com','facebook.com',
    'facebookmail.com','meta.com','instagram.com','whatsapp.com','youtube.com','google.com','canva.com','figma.com','notion.so','slack.com',
    'zoom.us','dropbox.com','wetransfer.com','docusign.net','docusign.com','paypal.com','github.com','gitlab.com','ovh.com','ovh.net','godaddy.com',
    'namecheap.com','hostinger.com','wix.com','wordpress.com','hubspot.com','mailchimp.com','sendgrid.net','brevo.com','sendinblue.com',
    'coolify.io','coollabs.io','vercel.com','netlify.com','openai.com','freepik.com','envato.com','shutterstock.com','fiverr.com','upwork.com',
    'linkedin.com','amadeus.com','booking.com','airbnb.com','tiktok.com','x.com','twitter.com','calendly.com','typeform.com','surveymonkey.com',
    'trello.com','atlassian.com','atlassian.net','jotform.com','squarespace.com','shopify.com','godaddy.net','mailjet.com','mautic.org','capcut.com',
    'midjourney.com','runwayml.com','elevenlabs.io','heygen.com','framer.com','webflow.com','grammarly.com','quora.com','medium.com','substack.com',
    'udemy.com','coursera.org','duolingo.com','spotify.com','netflix.com','uber.com','yango.com','glovoapp.com','hp.com','dell.com','lenovo.com',
    'samsung.com','xiaomi.com','huawei.com','avast.com','kaspersky.com','norton.com','mcafee.com','teamviewer.com','anydesk.com','anydesk.de',
    'pumble.com','clockify.me','mega.nz','wise.com','payoneer.com','binance.com','coinbase.com','tradingview.com','bolt.new','digitalocean.com',
    'lovable.dev','replit.com','cursor.com','cursor.sh','anthropic.ai','claude.ai','perplexity.ai','manus.im','genspark.ai','kling.ai','magnific.ai','freepik.es'];
$ROBOT_LOCAL = '/^(no-?reply|noreply|do-?not-?reply|ne-?pas-?repondre|mailer-daemon|postmaster|bounces?|notifications?|alerts?|alertes?|news|newsletters?|digest|updates?|'
    . 'receipts?|billing|security|verify|verification|reply|bonjour@|hello|team|learn|marketing-email|aws-|health|onboarding|recommendations|'
    . 'communications?@|customer-?service|support|service-?client|em|e|info@info)($|[._+-])/';
$ESP_SOUS_DOMAINE = '/^(email\d*|em\d+|e\d*|mail\d*|news|news-mail|newsletter|info|go|discover|inspire|team|growth|send|mkt|marketing|mailing|campaign|bounce|t|link|click|engage|hello)\./';

$MOTS_METIER = '/dispo|devis|pro ?-?forma|cotation|tarif|grille|campagne|affichage|affiche|panneau|12 ?m|54 ?m|4 ?x ?3|big ?size|bache|\bbat\b|maquette|visuel|'
    . '\bpose\b|pige|photos? de|rapport|bilan|bon de commande|\bbc\b|reservation|\boption\b|proposition|demande d.?offre|contrat|convention|agreement|'
    . 'renouvel|regie|outdoor|\booh\b|hors.?media|mobilier urbain|abribus|ecran|plan media|media ?plan|brief|emplacement|implantation|'
    . 'facture|reglement|paiement|relance|solde|mise en demeure|recouvrement|support de communication|partenariat|sponsoring|annonceur|'
    . 'achat d.?espace|espace publicitaire|presentation de|rendez-vous|\brdv\b|reunion|visite de terrain|compte rendu|\bcr\b|lancement|activation|'
    . 'billboard|poster|quotation|\bquote\b|inquiry|\brfq\b|plaquette|brochure|publicitaire|advertising|demande d.?information|catalogue de presentation|'
    . 'chevalet|kakemono|totem|\bcrea|appel d.?offre|\breseau\b|situation comptable|invoice|\bpub\b|\bchoix\b|fichier a imprimer|'
    . 'offre 360|offre commerciale|demande de cout|branding/';
// Signes d'une affaire conclue : la relation est sans doute un client.
$MOTS_CLIENT = '/\bbat\b|bon de commande|\bbc\b|bc ?\d|demande d.?achat|facture|reglement|paiement|\bpose\b|pige|photos? de|rapport de|contrat|'
    . 'renouvel|recouvrement|solde|releve|point financier|situation comptable|remboursement|mise en demeure|campagne n|ordre d.?insertion|validation de devis|'
    . 'devis valide|bon pour accord|choix (des )?supports|selection panneaux|plan de campagne|confirmation de (la )?(campagne|reservation|commande)/';
$MOTS_PUB = '/webinar|webinaire|inscription|inscrivez|newsletter|forum|salon|conference|masterclass|seminaire|formation|invitation|decouvr|nouveaute|'
    . 'offre speciale|promo|reduction|% off|discount|soldes|unsubscribe|desabonn|bienvenue|welcome|verification|code de|password|mot de passe|'
    . 'receipt|recu n|your account|votre compte|abonnement|subscription|trial|essai gratuit|last chance|derniere chance|ceremonie|gala|concours|'
    . 'magazine|edition|actualite|weekly|hebdo|mensuel|catalogue|black friday|cyber monday|nouvelle collection|vente flash/';
$MOTS_EMPLOI = '/candidature|\bcv\b|curriculum|stage|stagiaire|demande d.?emploi|offre d.?emploi|lettre de motivation|recrutement|alternance|job\b|emploi/';

// ── Type d'entreprise (2026-10-28) ────────────────────────────
// Correction à la main : la colonne « Statut CRM » du Sheets l'emporte sur tout.
$TYPES_CRM = ['agence' => 'Agence de communication', 'agence de communication' => 'Agence de communication',
    'regie' => 'Régie publicitaire', 'regie publicitaire' => 'Régie publicitaire', 'media' => 'Média', 'medias' => 'Média',
    'annonceur' => 'Annonceur', 'client' => 'Annonceur', 'fournisseur' => 'Fournisseur', 'institution' => 'Institution'];
// Noms connus, cherchés dans le domaine.
$CONNUS = [
    'Régie publicitaire'      => '/jcdecaux|clearchannel|unfold|pubregie/',
    'Agence de communication' => '/havas|mccann|dentsu|publicis|ogilvy|starcom|mindshare|wavemaker|carat|mediacom|groupm|tbwa|bbdo|leoburnett|saatchi|voodoo|orig[i]?n7|mediabrands|iprospect|zenithmedia|phdmedia|artdepartment/',
    'Fournisseur'             => '/kpmg|deloitte|(^|\.)pwc\.|mazars|grantthornton|forvis|(^|[-.])bdo([-.]|$)|bdo-/',
    'Média'                   => '/(^|\.)(rti|nci|groupefratmat|fratmat|abidjan\.net|linfodrome|lifetv|jeuneafrique|rfi|france24|canalplus|canal-plus)\./',
];
$INSTITUTION = '/(^|\.)gouv\.|\.gouv$|\.gov(\.|$)|\.edu(\.|$)|\.int$|ambassade|embassy|mairie|ministere|afdb\.org|(^|\.)un\.org|unicef|undp|worldbank|banquemondiale|bceao|uemoa/';
// Mots du domaine (sans l'extension) : ordre = priorité (une « régie média » est une régie).
$MOTS_DOMAINE = [
    'Régie publicitaire'      => '/regie|afficheur|affichage|outdoor|ooh|billboard|kikaffich/',
    'Média'                   => '/(^|[-.])(tv|radio|fm|presse|journal)([-.]|$)|tv$|magazine|onair|television/',
    // Cabinets et prestataires de CIBLE (avocats, audit, comptables, imprimeurs) : avant « agence ».
    'Fournisseur'             => '/avocat|notaire|audit|fiduciaire|comptab|imprim|print|serigraph|signaletique/',
    'Agence de communication' => '/agenc|agency|communic|(^|[-.])com([-.]|$)|^com|com$|media|(^|[-.])pub|pub$|advertis|marketing|creati|studio|design|event|production|prod$|brand|digital|graphi/',
];
// Nom affiché : seulement des mots d'entreprise (« 100TASH COMMUNICATION »), jamais un service
// d'annonceur (« Direction Communication »).
$MOTS_NOM = [
    'Régie publicitaire'      => '/\bregie\b|affichage|afficheur|outdoor/',
    'Agence de communication' => '/\bagence\b|agency|\bcommunications?\b|\bmedias?\b|advertising|\bpub\b|production|\bevents?\b/',
];
$NOM_DE_SERVICE = '/^(direction|service|departement|dept|pole|cellule|responsable|charge)\b/';
$POSTES = [
    'Régie publicitaire'      => '/regie/',
    'Agence de communication' => '/media ?plann|media ?buyer|chef de publicite|head of advertising|directeu?r(ice)? de clientele|directeu?r(ice)? conseil|account (manager|director|executive)|planneur|traffic manager|directeu?r(ice)? de (la )?creation|directeu?r(ice)? artistique|concepteur|conceptrice/',
    'Média'                   => '/journaliste|redacteur en chef|redactrice en chef/',
];
// Une agence achète pour le compte de ses annonceurs.
$OBJETS_AGENCE = '/notre client|votre client/';

// ══════════════════════════════════════════════════════════════
// CLASSEMENT
// ══════════════════════════════════════════════════════════════

$res = [];
foreach ($lignes as $r) {
    $email = strtolower($v($r, 'E-mail'));
    if ($email === '') continue;
    [$local, $dom] = array_pad(explode('@', $email, 2), 2, '');
    $env = (int) $v($r, 'Envoyés par CIBLE'); $rec = (int) $v($r, 'Reçus de ce contact'); $ech = (int) $v($r, 'Échanges');
    $objets = sansAccent($v($r, 'Objets récents') . ' ' . $v($r, 'Nom'));
    $perso = in_array($dom, $PERSO, true);
    $metier = (bool) preg_match($MOTS_METIER, $objets);
    $client = (bool) preg_match($MOTS_CLIENT, $objets);

    $crm = trim(sansAccent(isset($H['Statut CRM']) ? $v($r, 'Statut CRM') : ''));

    $cat = null; $raison = '';
    if ($crm === 'exclure') { $cat = 'ecarte'; $raison = 'Marqué « Exclure » dans le fichier'; }
    elseif (domaineDans($dom, $INTERNES) || str_contains($local, 'cible') && ($perso || $dom === '')
        || preg_match($MOTS_INTERNE, $objets)) { $cat = 'ecarte'; $raison = 'CIBLE (interne)'; }
    elseif (preg_match($MOTS_RH, $objets) && !preg_match($MOTS_PANNEAU, $objets)) { $cat = 'ecarte'; $raison = 'RH / personnel'; }
    elseif (domaineDans($dom, $OUTILS)) { $cat = 'ecarte'; $raison = 'Outil / plateforme'; }
    elseif ($env === 0 && (preg_match($ROBOT_LOCAL, $local . '@') || preg_match($ESP_SOUS_DOMAINE, $dom) || preg_match('/\+.*\d|[a-f0-9]{16,}/', $local))) { $cat = 'ecarte'; $raison = 'Envoi automatique'; }
    elseif (!$metier && preg_match($MOTS_EMPLOI, $objets)) { $cat = 'ecarte'; $raison = 'Candidature / stage'; }
    elseif (!$metier && $env === 0 && preg_match($MOTS_PUB, $objets)) { $cat = 'ecarte'; $raison = 'Publicité / événement'; }
    elseif ($metier) { $cat = 'garde'; $raison = 'Échanges commerciaux'; }

    preg_match_all($MOTS_CLIENT, $objets, $m1); preg_match_all($MOTS_METIER, $objets, $m2);
    $indices = implode(', ', array_slice(array_unique(array_map('trim', array_merge($m1[0], $m2[0]))), 0, 5));

    // Indices du type d'entreprise, du plus sûr (0) au moins sûr.
    $signaux = [];
    $ajouter = function (int $rang, string $type, string $source) use (&$signaux) { $signaux[] = compact('rang', 'type', 'source'); };
    if (isset($TYPES_CRM[$crm])) $ajouter(0, $TYPES_CRM[$crm], 'Statut CRM « ' . $v($r, 'Statut CRM') . ' »');
    if (!$perso && $dom !== '') {
        foreach ($CONNUS as $type => $re) if (preg_match($re, $dom)) { $ajouter(1, $type, 'nom connu'); break; }
        if (preg_match($INSTITUTION, $dom, $m)) $ajouter(2, 'Institution', 'domaine « ' . $m[0] . ' »');
        $etiquette = preg_replace('/\.[a-z]{2,}(\.[a-z]{2})?$/', '', $dom);
        if (!str_contains($etiquette, 'telecom')) {
            foreach ($MOTS_DOMAINE as $type => $re) if (preg_match($re, $etiquette, $m)) { $ajouter(3, $type, 'domaine « ' . trim($m[0], '-.') . ' »'); break; }
        }
    } elseif ($perso) {
        foreach ($MOTS_NOM as $type => $re) if (preg_match($re, str_replace(['.', '_', '-'], ' ', $local), $m)) { $ajouter(4, $type, 'adresse « ' . $m[0] . ' »'); break; }
    }
    // Nom affiché, sans ce qui est entre crochets ou parenthèses (« [ECI-Marketing & Communication] »).
    // Une boîte de service (communication@, agencecentrale@, events.abidjan@) décrit un service
    // de l'entreprise, pas son métier : son nom ne compte pas pour « agence ».
    $nom = trim(preg_replace('/\[[^\]]*\]|\([^)]*\)/', ' ', sansAccent($v($r, 'Nom'))));
    $boiteDeService = (bool) preg_match($MOTS_NOM['Agence de communication'], str_replace(['.', '_', '-'], ' ', $local))
        || (bool) preg_match('/^(agence|communication|events?)/', $local);
    if (!preg_match($NOM_DE_SERVICE, $nom)) {
        foreach ($MOTS_NOM as $type => $re) {
            if (!preg_match($re, $nom, $m)) continue;
            if (!($type === 'Agence de communication' && $boiteDeService)) $ajouter(4, $type, 'nom « ' . $m[0] . ' »');
            break;
        }
    }
    $poste = sansAccent(isset($H['Poste (signature)']) ? $v($r, 'Poste (signature)') : '');
    foreach ($POSTES as $type => $re) if (preg_match($re, $poste)) { $ajouter(5, $type, 'poste « ' . $v($r, 'Poste (signature)') . ' »'); break; }
    if (preg_match($OBJETS_AGENCE, $objets, $m)) $ajouter(6, 'Agence de communication', 'objets « ' . $m[0] . ' »');

    $res[] = compact('r', 'email', 'dom', 'env', 'rec', 'ech', 'perso', 'cat', 'raison', 'client', 'indices', 'crm', 'signaux');
}

// Les collègues d'une entreprise déjà en relation commerciale sont gardés aussi.
$domainesMetier = [];
foreach ($res as $x) if ($x['cat'] === 'garde' && !$x['perso']) $domainesMetier[$x['dom']] = true;
foreach ($res as &$x) {
    if ($x['cat'] !== null) continue;
    if (!$x['perso'] && isset($domainesMetier[$x['dom']])) { $x['cat'] = 'garde'; $x['raison'] = 'Même entreprise'; }
    elseif ($x['env'] === 0 && $x['rec'] === 0 && $x['ech'] <= 1) { $x['cat'] = 'ecarte'; $x['raison'] = 'Vu une seule fois en copie'; }
    else { $x['cat'] = 'verifier'; $x['raison'] = $x['perso'] ? 'Adresse personnelle, objet non commercial' : 'Objet non commercial'; }
}
unset($x);

// « Client probable » se décide à l'échelle de l'entreprise (même domaine).
$domainesClient = [];
foreach ($res as $x) if ($x['cat'] === 'garde' && $x['client'] && !$x['perso']) $domainesClient[$x['dom']] = true;
foreach ($res as &$x) {
    if ($x['cat'] !== 'garde') continue;
    if ($x['client'] || (!$x['perso'] && isset($domainesClient[$x['dom']]))) {
        $x['cat'] = 'client';
        $x['raison'] = $x['client'] ? 'Affaire conclue dans les objets' : "Même entreprise qu'un client probable";
    } else {
        $x['cat'] = 'prospect';
        $x['raison'] = $x['raison'] === 'Même entreprise' ? "Même entreprise qu'un contact commercial" : 'Demande, devis ou proposition';
    }
}
unset($x);
// « Client » écrit dans le Statut CRM (à la main, ou via « Domaines clients » des Réglages).
foreach ($res as &$x) {
    if ($x['crm'] === 'client' && in_array($x['cat'], ['prospect', 'verifier'], true)) {
        $x['cat'] = 'client';
        $x['raison'] = 'Marqué « Client » dans le fichier';
    }
}
unset($x);

// Type d'entreprise : décidé pour tout le domaine (un indice d'un contact vaut pour ses collègues),
// par l'indice le plus sûr, puis par le type le plus fréquent à sûreté égale.
$choisir = function (array $signaux): ?array {
    if (!$signaux) return null;
    $rang = min(array_column($signaux, 'rang'));
    $meilleurs = array_values(array_filter($signaux, fn ($s) => $s['rang'] === $rang));
    $nb = array_count_values(array_column($meilleurs, 'type'));
    arsort($nb);
    $type = array_key_first($nb);
    foreach ($meilleurs as $s) if ($s['type'] === $type) return $s;
};
$parDomaine = [];
foreach ($res as $x) {
    if ($x['perso'] || $x['dom'] === '') continue;
    $parDomaine[$x['dom']] ??= [];
    array_push($parDomaine[$x['dom']], ...$x['signaux']);
}
// Nom vérifié en ligne (secteurs.csv) : « Red Africa (Publicis) », « Ecko Agency » trahissent une agence.
// Seulement si la recherche a aussi classé l'entreprise dans la communication : sinon « Agence
// Nationale de Gestion des Déchets » ou « Productions Animales » passeraient pour des agences.
foreach (array_keys($parDomaine) as $dom) {
    $nomVerifie = sansAccent($SECTEURS[$dom]['nom'] ?? '');
    if ($nomVerifie === '' || !in_array($SECTEURS[$dom]['secteur'], ['Agence & Conseil', 'Médias & Communication'], true)) continue;
    $source = 'nom vérifié « ' . $SECTEURS[$dom]['nom'] . ' »';
    foreach ($CONNUS as $type => $re) {
        if (preg_match($re, str_replace(' ', '', $nomVerifie))) { $parDomaine[$dom][] = ['rang' => 1, 'type' => $type, 'source' => $source]; continue 2; }
    }
    foreach ($MOTS_NOM as $type => $re) {
        if (preg_match($re, $nomVerifie)) { $parDomaine[$dom][] = ['rang' => 4, 'type' => $type, 'source' => $source]; break; }
    }
}
$typesDomaine = array_map($choisir, $parDomaine);
$commercial = [];
foreach ($res as $x) if (in_array($x['cat'], ['client', 'prospect'], true)) $commercial[$x['dom']] = true;
foreach ($res as &$x) {
    $s = (!$x['perso'] && $x['dom'] !== '') ? $typesDomaine[$x['dom']] : $choisir($x['signaux']);
    if ($s) { $x['type'] = $s['type']; $x['typeSource'] = $s['source']; }
    elseif ($x['perso']) { $x['type'] = 'Non déterminé'; $x['typeSource'] = 'adresse personnelle sans indice'; }
    elseif (isset($commercial[$x['dom']])) { $x['type'] = 'Annonceur'; $x['typeSource'] = 'par défaut : ni régie, ni agence, ni média repérés'; }
    else { $x['type'] = 'Autre'; $x['typeSource'] = 'aucun indice'; }
}
unset($x);

// ══════════════════════════════════════════════════════════════
// FICHIER EXCEL
// ══════════════════════════════════════════════════════════════

$date = fn ($x) => is_numeric($x) && $x > 0 ? (float) $x : null; // dates Excel (numéro de série)
$groupes = ['client' => [], 'prospect' => [], 'verifier' => [], 'ecarte' => []];
foreach ($res as $x) {
    $r = $x['r'];
    $sens = $x['env'] > 0 && $x['rec'] > 0 ? 'Dans les deux sens' : ($x['env'] > 0 ? 'CIBLE lui a écrit' : ($x['rec'] > 0 ? 'Il a écrit à CIBLE' : 'En copie'));
    $groupes[$x['cat']][] = [
        'Entreprise' => $x['perso'] ? '' : $v($r, 'Entreprise (déduite)'),
        'Type' => $x['type'],
        'Secteur' => $SECTEURS[$x['dom']]['secteur'] ?? '',
        'Nom' => $v($r, 'Nom'),
        'Poste' => isset($H['Poste (signature)']) ? $v($r, 'Poste (signature)') : '',
        'E-mail' => $x['email'],
        'Téléphone(s)' => isset($H['Téléphones (signature)']) ? $v($r, 'Téléphones (signature)') : '',
        "Type d'adresse" => $x['perso'] ? 'Personnelle' : 'Professionnelle',
        'Dernier échange' => $date($r[$H['Dernier échange']]),
        'Premier échange' => $date($r[$H['Premier échange']]),
        'Échanges' => $x['ech'],
        'Sens des échanges' => $sens,
        'Boîtes CIBLE' => $v($r, 'Boîtes CIBLE'),
        'Objets récents' => $v($r, 'Objets récents'),
        'Indices' => $x['indices'],
        'Classement' => $x['raison'],
        'Type : indice' => $x['typeSource'],
    ];
}

// Une ligne par entreprise (domaine professionnel), hors contacts écartés.
$RELATIONS = ['client' => 'Client probable', 'prospect' => 'Prospect', 'verifier' => 'À vérifier'];
$ORDRE_TYPES = ['Régie publicitaire', 'Agence de communication', 'Média', 'Annonceur', 'Institution', 'Fournisseur', 'Autre'];
$societes = [];
foreach ($res as $x) {
    if ($x['perso'] || $x['dom'] === '' || $x['cat'] === 'ecarte') continue;
    $r = $x['r'];
    $s = &$societes[$x['dom']];
    $sect = $SECTEURS[$x['dom']] ?? [];
    $s ??= ['Entreprise' => $v($r, 'Entreprise (déduite)'), 'Nom vérifié' => $sect['nom'] ?? '', 'Type' => $x['type'],
        'Secteur' => $sect['secteur'] ?? '', 'Confiance' => $sect['confiance'] ?? '', 'Relation' => $x['cat'], 'Contacts' => 0,
        'Échanges' => 0, 'Dernier échange' => null, 'Domaine' => $x['dom'], 'Type : indice' => $x['typeSource'],
        'Secteur : source' => $sect['source'] ?? '', 'liste' => []];
    $s['Contacts']++;
    $s['Échanges'] += $x['ech'];
    $d = $date($r[$H['Dernier échange']]);
    if ($d && (!$s['Dernier échange'] || $d > $s['Dernier échange'])) $s['Dernier échange'] = $d;
    if (array_search($x['cat'], array_keys($RELATIONS)) < array_search($s['Relation'], array_keys($RELATIONS))) $s['Relation'] = $x['cat'];
    $s['liste'][] = [$x['ech'], trim($v($r, 'Nom') . ' <' . $x['email'] . '>')];
    unset($s);
}
foreach ($societes as &$s) {
    rsort($s['liste']);
    $s['Contacts principaux'] = implode(' ; ', array_column(array_slice($s['liste'], 0, 3), 1));
    $s['Relation'] = $RELATIONS[$s['Relation']];
    unset($s['liste']);
}
unset($s);
$societes = array_values($societes);
// Par secteur (sans secteur à la fin), puis les entreprises les plus actives d'abord.
usort($societes, fn ($a, $b) => [$a['Secteur'] === '' ? 1 : 0, $a['Secteur'], -$a['Échanges']] <=> [$b['Secteur'] === '' ? 1 : 0, $b['Secteur'], -$b['Échanges']]);
$parType = array_count_values(array_column($societes, 'Type'));
$parSecteur = array_count_values(array_filter(array_column($societes, 'Secteur')));
arsort($parSecteur);

// Entreprises A→Z (adresses personnelles à la fin), puis les contacts les plus actifs d'abord.
$parEntreprise = function ($a, $b) {
    return [$a['Entreprise'] === '' ? 1 : 0, mb_strtolower($a['Entreprise']), -$a['Échanges']]
       <=> [$b['Entreprise'] === '' ? 1 : 0, mb_strtolower($b['Entreprise']), -$b['Échanges']];
};
usort($groupes['client'], $parEntreprise);
usort($groupes['prospect'], $parEntreprise);
usort($groupes['verifier'], fn ($a, $b) => $b['Échanges'] <=> $a['Échanges']);
usort($groupes['ecarte'], fn ($a, $b) => [$a['Classement'], -$a['Échanges']] <=> [$b['Classement'], -$b['Échanges']]);

$entreprises = fn ($g) => count(array_unique(array_filter(array_column($groupes[$g], 'Entreprise'))));
$boites = [];
foreach ($lignes as $r) foreach (explode(', ', $v($r, 'Boîtes CIBLE')) as $b) if ($b !== '') $boites[$b] = true;
ksort($boites);

$wb = new Spreadsheet();
$lire = $wb->getActiveSheet()->setTitle('Lisez-moi');
$secteursLire = $parSecteur
    ? [[''], ["Secteurs d'activité", 'Entreprises'], ...array_map(fn ($sec, $n) => [$sec, $n], array_keys($parSecteur), $parSecteur),
       ['Sans secteur', count($societes) - array_sum($parSecteur)]]
    : [];
$contenuLire = [
    ['Contacts Gmail de CIBLE — tri du ' . date('d/m/Y')],
    [''],
    ['Boîtes analysées', implode(', ', array_keys($boites))],
    ['Contacts lus', count($res)],
    [''],
    ['Onglet', 'Contacts', 'Entreprises', 'Contenu'],
    ['Clients probables', count($groupes['client']), $entreprises('client'), "Les objets des mails montrent une affaire conclue : facture, bon de commande, BAT, pose, pige, règlement, contrat, renouvellement… Toute l'entreprise est classée ainsi."],
    ['Prospects', count($groupes['prospect']), $entreprises('prospect'), "Demandes de disponibilités, devis, propositions et prospections de CIBLE, sans trace d'affaire conclue."],
    ['À vérifier', count($groupes['verifier']), $entreprises('verifier'), 'Échanges réels mais objet non commercial : partenaires, cabinets, fournisseurs, contacts personnels.'],
    ['Écartés', count($groupes['ecarte']), $entreprises('ecarte'), 'CIBLE (interne), RH et personnel, outils et plateformes, envois automatiques, publicités et événements, candidatures, adresses vues une seule fois en copie, « Exclure » du Statut CRM. La raison est indiquée pour chacun.'],
    ['Entreprises', '', count($societes), "Une ligne par entreprise (adresses professionnelles, hors écartés), rangée par secteur : nom vérifié, type, secteur, relation, contacts principaux."],
    [''],
    ['Types d\'entreprise', 'Entreprises'],
    ...array_map(fn ($t) => [$t, $parType[$t] ?? 0], $ORDRE_TYPES),
    ...$secteursLire,
    [''],
    ['À savoir'],
    ['• Secteurs : mêmes libellés que les fiches clients de Panora. Vérifiés en ligne (nom ou domaine de l\'entreprise) ; la colonne « Confiance » dit s\'ils sont sûrs. « basse » = à vérifier. Pour corriger : modifiez « secteurs.csv » (à côté de ce fichier), puis relancez le tri.'],
    ['• « Client probable » est déduit des objets de mails. La comparaison avec la liste des clients Panora confirmera chaque client.'],
    ['• Le type vient, du plus sûr au moins sûr : du Statut CRM, d\'un nom connu (Havas, McCann, JCDecaux, Unfold, RTI…), du domaine (regie, agence, media, tv…), du nom affiché, du poste de la signature, puis des objets (« notre client »). Sans indice : « Annonceur ».'],
    ['• Pour corriger : dans le Sheets, écrivez « Agence », « Régie », « Média », « Annonceur », « Fournisseur », « Client » ou « Exclure » dans la colonne Statut CRM d\'un contact, puis réexportez. Le type vaut pour toute l\'entreprise.'],
    ['• Les colonnes « Indices », « Classement » et « Type : indice » montrent ce qui a décidé de chaque classement.'],
    ['• Les téléphones et les postes viennent des signatures : à vérifier avant usage.'],
    ['• Fichier confidentiel : coordonnées de clients et prospects. Ne pas diffuser hors de CIBLE.'],
];
$lire->fromArray($contenuLire, null, 'A1');
$lire->getStyle('A1')->getFont()->setBold(true)->setSize(14);
foreach ($contenuLire as $i => $l) {
    if (in_array($l[0], ['Onglet', "Types d'entreprise", "Secteurs d'activité", 'À savoir'], true)) $lire->getStyle('A' . ($i + 1) . ':D' . ($i + 1))->getFont()->setBold(true);
}
foreach (['A' => 34, 'B' => 12, 'C' => 12, 'D' => 110] as $c => $l) $lire->getColumnDimension($c)->setWidth($l);
$lire->getStyle('D7:D11')->getAlignment()->setWrapText(true);

$onglets = ['client' => ['Clients probables', 'D9F2D0'], 'prospect' => ['Prospects', 'DCE8F7'], 'verifier' => ['À vérifier', 'FFF2CC'], 'ecarte' => ['Écartés', 'EDEDED']];
$largeurs = ['Entreprise' => 22, 'Type' => 22, 'Secteur' => 24, 'Nom' => 26, 'Poste' => 26, 'E-mail' => 34, 'Téléphone(s)' => 24, "Type d'adresse" => 15, 'Dernier échange' => 14, 'Premier échange' => 14,
    'Échanges' => 10, 'Sens des échanges' => 19, 'Boîtes CIBLE' => 30, 'Objets récents' => 70, 'Indices' => 30, 'Classement' => 34, 'Type : indice' => 34];
$entetes = array_keys($largeurs);
$lettre = fn ($nom) => Coordinate::stringFromColumnIndex(array_search($nom, $entetes) + 1);
$texte = ['E-mail', 'Téléphone(s)', 'Nom', 'Poste', 'Objets récents']; // jamais convertis en nombre ou en formule
foreach ($onglets as $cle => [$titre, $couleur]) {
    $ws = $wb->createSheet()->setTitle($titre);
    $ws->fromArray($entetes, null, 'A1');
    foreach ($groupes[$cle] as $i => $l) {
        foreach ($entetes as $c => $e) {
            $cell = $ws->getCell([$c + 1, $i + 2]);
            in_array($e, $texte, true) ? $cell->setValueExplicit((string) $l[$e], DataType::TYPE_STRING) : $cell->setValue($l[$e]);
        }
    }
    $der = max(count($groupes[$cle]) + 1, 2);
    $fin = $lettre('Type : indice');
    $ws->getStyle($lettre('Dernier échange') . '2:' . $lettre('Premier échange') . $der)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    $ws->getStyle("A1:{$fin}1")->getFont()->setBold(true);
    $ws->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($couleur);
    $ws->freezePane('D2');
    $ws->setAutoFilter("A1:{$fin}{$der}");
    foreach ($entetes as $n => $e) $ws->getColumnDimensionByColumn($n + 1)->setWidth($largeurs[$e]);
    $ws->getTabColor()->setRGB($couleur);
}
// Onglet « Entreprises »
$ws = $wb->createSheet()->setTitle('Entreprises');
$colonnes = ['Entreprise' => 24, 'Nom vérifié' => 30, 'Type' => 24, 'Secteur' => 28, 'Confiance' => 11, 'Relation' => 16, 'Contacts' => 10,
    'Échanges' => 10, 'Dernier échange' => 14, 'Domaine' => 26, 'Type : indice' => 36, 'Secteur : source' => 40, 'Contacts principaux' => 90];
$noms = array_keys($colonnes);
$col = fn ($nom) => Coordinate::stringFromColumnIndex(array_search($nom, $noms) + 1);
$ws->fromArray($noms, null, 'A1');
foreach ($societes as $i => $s) {
    foreach ($noms as $c => $e) {
        $cell = $ws->getCell([$c + 1, $i + 2]);
        in_array($e, ['Entreprise', 'Nom vérifié', 'Domaine', 'Secteur : source', 'Contacts principaux'], true)
            ? $cell->setValueExplicit((string) $s[$e], DataType::TYPE_STRING) : $cell->setValue($s[$e]);
    }
}
$der = max(count($societes) + 1, 2);
$fin = $col('Contacts principaux');
$ws->getStyle($col('Dernier échange') . '2:' . $col('Dernier échange') . $der)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
$ws->getStyle("A1:{$fin}1")->getFont()->setBold(true);
$ws->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8DDF7');
$ws->freezePane('C2');
$ws->setAutoFilter("A1:{$fin}{$der}");
foreach ($noms as $n => $e) $ws->getColumnDimensionByColumn($n + 1)->setWidth($colonnes[$e]);
$ws->getTabColor()->setRGB('E8DDF7');

$wb->setActiveSheetIndex(1);
// Fichier ouvert dans Excel (verrouillé) : on écrit une copie datée à côté plutôt que d'échouer.
if (is_file($sortie) && !($h = @fopen($sortie, 'r+'))) {
    $sortie = preg_replace('/\.xlsx$/', ' ' . date('Y-m-d Hi') . '.xlsx', $sortie);
    fwrite(STDERR, "Le fichier trié est ouvert dans Excel : copie écrite à côté.\n");
} elseif (isset($h) && $h) {
    fclose($h);
}
(new Xlsx($wb))->save($sortie);

echo "Écrit : $sortie\n";
foreach ($onglets as $cle => [$titre]) echo '  ' . str_pad($titre, 20) . count($groupes[$cle]) . ' contacts, ' . $entreprises($cle) . " entreprises\n";
echo "  Types (entreprises) : " . implode(' · ', array_map(fn ($t) => $t . ' ' . ($parType[$t] ?? 0), $ORDRE_TYPES)) . "\n";
echo '  Secteurs (entreprises) : ' . ($parSecteur ? implode(' · ', array_map(fn ($s, $n) => "$s $n", array_keys($parSecteur), $parSecteur)) : 'aucun fichier secteurs.csv') . "\n";
