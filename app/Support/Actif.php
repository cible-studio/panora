<?php

namespace App\Support;

/**
 * Adresse « versionnée » d'un fichier de public/ (2026-11-13) : son empreinte.
 *
 * Avant : ?v={app.version}, une valeur fixe d'un déploiement à l'autre. Le
 * service worker de l'espace technicien sert CSS et JS depuis son cache
 * (stale-while-revalidate) : après un déploiement, la page arrivait neuve
 * avec l'ANCIEN style — boutons gris, cartes mal formées — tant que le
 * technicien n'avait pas fermé l'onglet. Avec l'empreinte du contenu dans
 * l'adresse, chaque fichier modifié a une nouvelle adresse : ni le cache du
 * service worker ni celui du navigateur ne servent l'ancien.
 */
final class Actif
{
    /** Empreintes déjà calculées pendant cette requête. */
    private static array $empreintes = [];

    /**
     * Version d'un fichier = empreinte de son CONTENU (2026-11-13). La date
     * de modification ne suffit pas : selon la construction de l'image, elle
     * peut rester la même d'un déploiement à l'autre — l'adresse ne changeait
     * pas et le téléphone gardait l'ancien style. L'empreinte change
     * exactement quand le fichier change.
     */
    public static function version(string $fichier): string
    {
        return self::$empreintes[$fichier] ??= (is_file($fichier)
            ? hash_file('crc32b', $fichier) . dechex(filesize($fichier))
            : (string) config('app.version', '1'));
    }

    /** asset($chemin) suivi de ?v=<empreinte du fichier>. */
    public static function url(string $chemin): string
    {
        return asset($chemin) . '?v=' . self::version(public_path($chemin));
    }

    /**
     * Table d'import des modules ES d'un dossier de public/ (ex. js/tech) :
     * « /js/tech/features/refonte.js » → « …refonte.js?v=<empreinte> ». Les imports
     * relatifs entre modules (« ./features/upload.js ») passent par elle : ils
     * suivent aussi chaque déploiement. Clés en chemins absolus depuis la
     * racine du site (le navigateur les résout contre l'adresse de la page).
     *
     * @return array<string, string>
     */
    public static function carteImports(string $dossier): array
    {
        $racine = rtrim(public_path($dossier), '/\\');
        if (!is_dir($racine)) {
            return [];
        }

        $carte = [];
        $fichiers = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($fichiers as $f) {
            if ($f->getExtension() !== 'js') {
                continue;
            }
            $relatif = str_replace('\\', '/', substr($f->getPathname(), strlen($racine) + 1));
            $chemin = '/' . trim($dossier, '/') . '/' . $relatif;
            $carte[$chemin] = $chemin . '?v=' . self::version($f->getPathname());
        }
        ksort($carte);

        return $carte;
    }
}
