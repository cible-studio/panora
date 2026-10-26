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

    $cat = null; $raison = '';
    if (domaineDans($dom, $INTERNES) || str_contains($local, 'cible') && ($perso || $dom === '')
        || preg_match($MOTS_INTERNE, $objets)) { $cat = 'ecarte'; $raison = 'CIBLE (interne)'; }
    elseif (preg_match($MOTS_RH, $objets) && !preg_match($MOTS_PANNEAU, $objets)) { $cat = 'ecarte'; $raison = 'RH / personnel'; }
    elseif (domaineDans($dom, $OUTILS)) { $cat = 'ecarte'; $raison = 'Outil / plateforme'; }
    elseif ($env === 0 && (preg_match($ROBOT_LOCAL, $local . '@') || preg_match($ESP_SOUS_DOMAINE, $dom) || preg_match('/\+.*\d|[a-f0-9]{16,}/', $local))) { $cat = 'ecarte'; $raison = 'Envoi automatique'; }
    elseif (!$metier && preg_match($MOTS_EMPLOI, $objets)) { $cat = 'ecarte'; $raison = 'Candidature / stage'; }
    elseif (!$metier && $env === 0 && preg_match($MOTS_PUB, $objets)) { $cat = 'ecarte'; $raison = 'Publicité / événement'; }
    elseif ($metier) { $cat = 'garde'; $raison = 'Échanges commerciaux'; }

    preg_match_all($MOTS_CLIENT, $objets, $m1); preg_match_all($MOTS_METIER, $objets, $m2);
    $indices = implode(', ', array_slice(array_unique(array_map('trim', array_merge($m1[0], $m2[0]))), 0, 5));
    $res[] = compact('r', 'email', 'dom', 'env', 'rec', 'ech', 'perso', 'cat', 'raison', 'client', 'indices');
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
    ];
}

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
$lire->fromArray([
    ['Contacts Gmail de CIBLE — tri du ' . date('d/m/Y')],
    [''],
    ['Boîtes analysées', implode(', ', array_keys($boites))],
    ['Contacts lus', count($res)],
    [''],
    ['Onglet', 'Contacts', 'Entreprises', 'Contenu'],
    ['Clients probables', count($groupes['client']), $entreprises('client'), "Les objets des mails montrent une affaire conclue : facture, bon de commande, BAT, pose, pige, règlement, contrat, renouvellement… Toute l'entreprise est classée ainsi."],
    ['Prospects', count($groupes['prospect']), $entreprises('prospect'), "Demandes de disponibilités, devis, propositions et prospections de CIBLE, sans trace d'affaire conclue."],
    ['À vérifier', count($groupes['verifier']), $entreprises('verifier'), 'Échanges réels mais objet non commercial : partenaires, cabinets, fournisseurs, contacts personnels.'],
    ['Écartés', count($groupes['ecarte']), $entreprises('ecarte'), 'CIBLE (interne), RH et personnel, outils et plateformes, envois automatiques, publicités et événements, candidatures, adresses vues une seule fois en copie. La raison est indiquée pour chacun.'],
    [''],
    ['À savoir'],
    ['• « Client probable » est déduit des objets de mails. La comparaison avec la liste des clients Panora confirmera chaque client.'],
    ['• Les colonnes « Indices » et « Classement » montrent ce qui a décidé du classement.'],
    ['• Les téléphones et les postes viennent des signatures : à vérifier avant usage.'],
    ['• Fichier confidentiel : coordonnées de clients et prospects. Ne pas diffuser hors de CIBLE.'],
], null, 'A1');
$lire->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$lire->getStyle('A6:D6')->getFont()->setBold(true);
$lire->getStyle('A12')->getFont()->setBold(true);
foreach (['A' => 22, 'B' => 12, 'C' => 12, 'D' => 110] as $c => $l) $lire->getColumnDimension($c)->setWidth($l);
$lire->getStyle('D7:D10')->getAlignment()->setWrapText(true);

$onglets = ['client' => ['Clients probables', 'D9F2D0'], 'prospect' => ['Prospects', 'DCE8F7'], 'verifier' => ['À vérifier', 'FFF2CC'], 'ecarte' => ['Écartés', 'EDEDED']];
$largeurs = ['Entreprise' => 22, 'Nom' => 26, 'Poste' => 26, 'E-mail' => 34, 'Téléphone(s)' => 24, "Type d'adresse" => 15, 'Dernier échange' => 14, 'Premier échange' => 14,
    'Échanges' => 10, 'Sens des échanges' => 19, 'Boîtes CIBLE' => 30, 'Objets récents' => 70, 'Indices' => 30, 'Classement' => 34];
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
    $fin = $lettre('Classement');
    $ws->getStyle($lettre('Dernier échange') . '2:' . $lettre('Premier échange') . $der)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    $ws->getStyle("A1:{$fin}1")->getFont()->setBold(true);
    $ws->getStyle("A1:{$fin}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($couleur);
    $ws->freezePane('D2');
    $ws->setAutoFilter("A1:{$fin}{$der}");
    foreach ($entetes as $n => $e) $ws->getColumnDimensionByColumn($n + 1)->setWidth($largeurs[$e]);
    $ws->getTabColor()->setRGB($couleur);
}
$wb->setActiveSheetIndex(1);
(new Xlsx($wb))->save($sortie);

echo "Écrit : $sortie\n";
foreach ($onglets as $cle => [$titre]) echo '  ' . str_pad($titre, 20) . count($groupes[$cle]) . ' contacts, ' . $entreprises($cle) . " entreprises\n";
