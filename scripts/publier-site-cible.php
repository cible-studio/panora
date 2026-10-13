<?php
/**
 * Republie le site vitrine CIBLE (statique) dans public/cible/.
 *
 *   php scripts/publier-site-cible.php ["C:/wamp64/www/PROJET 3D/TEST 3D REGIE OOH"]
 *
 * Source par défaut : le dossier de travail « TEST 3D REGIE OOH ».
 * Copie : les pages et fichiers de la racine (*.html, manifest.webmanifest,
 * robots.txt, sitemap.xml, sw.js) et tout le dossier assets/. Ne copie PAS
 * _sources/, _archives/, outils/, README.md, .htaccess ni envoi.php.
 * Les fichiers qui n'existent plus dans la source sont retirés de
 * public/cible/ (miroir exact).
 *
 * Adaptations de préproduction appliquées à chaque copie :
 *   · <meta name="robots" content="noindex, nofollow"> sur chaque page ;
 *   · index.html : redirection « /cible » → « /cible/ » (le serveur PHP
 *     intégré ne l'ajoute pas, et les liens relatifs casseraient).
 *
 * Ensuite : git add public/cible && commit && push sur develop, puis
 * Redeploy de dev.panora-cible.com.
 */

$source = rtrim(str_replace('\\', '/', $argv[1] ?? 'C:/wamp64/www/PROJET 3D/TEST 3D REGIE OOH'), '/');
$cible  = str_replace('\\', '/', dirname(__DIR__)) . '/public/cible';

if (! is_file("$source/index.html")) {
    fwrite(STDERR, "Source introuvable ou sans index.html : $source\n");
    exit(1);
}

// ── 1. Liste des fichiers à publier (chemins relatifs) ──
$voulus = [];
foreach (scandir($source) as $nom) {
    if (is_file("$source/$nom") && preg_match('/\.html$|^(manifest\.webmanifest|robots\.txt|sitemap\.xml|sw\.js)$/', $nom)) {
        $voulus[] = $nom;
    }
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$source/assets", FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $voulus[] = 'assets/' . str_replace('\\', '/', substr($f->getPathname(), strlen("$source/assets/")));
}
sort($voulus);

// ── 2. Copie ──
$copies = 0;
foreach ($voulus as $rel) {
    $dest = "$cible/$rel";
    if (! is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
    copy("$source/$rel", $dest);
    $copies++;
}

// ── 3. Retrait des fichiers disparus de la source ──
$retires = [];
if (is_dir($cible)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cible, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    $index = array_flip($voulus);
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen("$cible/")));
        if ($f->isDir()) {
            if (count(scandir($f->getPathname())) === 2) rmdir($f->getPathname());
        } elseif (! isset($index[$rel])) {
            unlink($f->getPathname());
            $retires[] = $rel;
        }
    }
}

// ── 4. Adaptations de préproduction ──
$adaptees = 0;
foreach (glob("$cible/*.html") as $page) {
    $s = file_get_contents($page);
    $ajout = '';
    if (basename($page) === 'index.html' && ! str_contains($s, 'location.pathname==="/cible"')) {
        $ajout .= "\n<!-- Préproduction Panora : « /cible » sans barre finale casserait les liens relatifs (assets/…). Le serveur PHP intégré ne redirige pas : on le fait ici, avant tout chargement. -->\n"
                . '<script>if(location.pathname==="/cible")location.replace("/cible/"+location.search+location.hash)</script>';
    }
    if (! preg_match('/<meta\s+name="robots"/i', $s)) {
        $ajout .= "\n<!-- Préproduction Panora (dev.panora-cible.com/cible) : ne pas indexer. -->\n"
                . '<meta name="robots" content="noindex, nofollow">';
    }
    if ($ajout !== '') {
        $s = preg_replace('#<head([^>]*)>#i', '<head$1>' . $ajout, $s, 1, $n);
        if ($n === 1) { file_put_contents($page, $s); $adaptees++; }
    }
}

echo "Publié depuis : $source\n";
echo "  $copies fichiers copiés, " . count($retires) . " retirés, $adaptees pages adaptées.\n";
foreach ($retires as $r) echo "  - retiré : $r\n";
