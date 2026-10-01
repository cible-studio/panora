<?php

namespace App\Support;

/**
 * Charte graphique appliquée aux PDF générés par Panora (DomPDF).
 *
 * Source unique : config/charte.php (palette verrouillée, polices, coordonnées).
 * Cette classe ne fait que DÉRIVER des valeurs d'habillage à partir de la
 * config — aucune couleur n'est inventée ici :
 *   • les 8 couleurs de la palette, telles quelles ;
 *   • des « teintes » = une couleur de la palette fondue sur du blanc
 *     (DomPDF gère mal la transparence rgba() sur les fonds, on précalcule
 *     donc le mélange) — utilisées pour les fonds de badges, d'encadrés et
 *     le texte secondaire (noir adouci) ;
 *   • les piles de polices CSS (Poppins / Nunito intégrées localement, avec
 *     repli DejaVu Sans pour les glyphes absents : →, ■, ✓, ⚠…).
 *
 * Injectée dans toutes les vues PDF sous `$charte` par le view composer
 * d'AppServiceProvider. Les partiels `pdf.partials.charte-*` font un repli
 * sur `PdfCharte::data()` si une vue n'est pas couverte par le composer.
 */
class PdfCharte
{
    private static ?array $cache = null;

    /** Fichiers TTF intégrés (resources/fonts, licence SIL OFL 1.1). */
    private const FONTS = [
        // famille => [poids CSS => fichier]
        // DomPDF associe un fichier à un poids EXACT : un poids non déclaré
        // (500, 600, 800…) retombe sur la police par défaut (Times). On
        // déclare donc tous les poids utilisés par les vues.
        'Poppins' => [
            400 => 'Poppins-SemiBold.ttf',
            500 => 'Poppins-SemiBold.ttf',
            600 => 'Poppins-SemiBold.ttf',
            700 => 'Poppins-Bold.ttf',
            800 => 'Poppins-ExtraBold.ttf',
            900 => 'Poppins-ExtraBold.ttf',
        ],
        'Nunito' => [
            300 => 'Nunito-Regular.ttf',
            400 => 'Nunito-Regular.ttf',
            500 => 'Nunito-Regular.ttf',
            600 => 'Nunito-Bold.ttf',
            700 => 'Nunito-Bold.ttf',
            800 => 'Nunito-ExtraBold.ttf',
            900 => 'Nunito-ExtraBold.ttf',
        ],
    ];

    /** Italiques (texte courant uniquement). */
    private const FONTS_ITALIC = [
        'Nunito' => [
            300 => 'Nunito-Italic.ttf',
            400 => 'Nunito-Italic.ttf',
            500 => 'Nunito-Italic.ttf',
            600 => 'Nunito-BoldItalic.ttf',
            700 => 'Nunito-BoldItalic.ttf',
            800 => 'Nunito-BoldItalic.ttf',
            900 => 'Nunito-BoldItalic.ttf',
        ],
    ];

    /**
     * Données de charte pour les vues PDF (mémoïsées par requête).
     *
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $c = array_map('strtoupper', (array) config('charte.couleurs', []));
        // Garde-fou : si une clé manque dans la config, on ne casse pas le PDF.
        $c += [
            'rouge' => '#E20613', 'jaune' => '#FAB80B', 'vert' => '#3AA835',
            'bleu' => '#3F7FC0', 'violet' => '#81358A', 'gris' => '#E6E6E6',
            'noir' => '#111111', 'blanc' => '#FFFFFF',
        ];

        $data = $c;

        // Teintes claires (fonds de badges / encadrés) : couleur à 12 %.
        foreach (['rouge', 'jaune', 'vert', 'bleu', 'violet'] as $nom) {
            $data[$nom . '_clair'] = self::fondu($c[$nom], 0.12);
        }
        // Fond très léger (bandeaux méta, cartes KPI) : gris de charte à 45 %.
        $data['gris_clair'] = self::fondu($c['gris'], 0.45);
        // Texte secondaire : noir de charte adouci (lisible à l'impression).
        $data['texte_doux'] = self::fondu($c['noir'], 0.72);
        $data['texte_pale'] = self::fondu($c['noir'], 0.50);

        $data['lisere'] = array_values(array_map(
            fn ($nom) => $c[$nom] ?? $c['noir'],
            (array) config('charte.lisere', ['rouge', 'jaune', 'vert', 'bleu', 'violet'])
        ));

        $data['nom']         = (string) config('charte.nom', 'CIBLE');
        $data['slogan']      = (string) config('charte.slogan', '');
        $data['coordonnees'] = (array) config('charte.coordonnees', []);

        $polices = self::policesDisponibles();
        $data['polices_ok'] = $polices;
        $titres = (string) config('charte.polices.titres', 'Poppins');
        $texte  = (string) config('charte.polices.texte', 'Nunito');
        $data['ff_titres'] = $polices ? "'{$titres}', 'DejaVu Sans', sans-serif" : "'DejaVu Sans', sans-serif";
        $data['ff_texte']  = $polices ? "'{$texte}', 'DejaVu Sans', sans-serif"  : "'DejaVu Sans', sans-serif";
        $data['ff_mono']   = "'DejaVu Sans Mono', monospace";
        $data['font_face_css'] = $polices ? self::fontFaceCss() : '';

        return self::$cache = $data;
    }

    /**
     * Les polices intégrées sont utilisables si les TTF sont présents ET si
     * le cache de polices DomPDF (storage/fonts) existe et est inscriptible —
     * sans lui, DomPDF lève une exception à l'enregistrement de la police.
     * Dans le doute on retombe sur DejaVu Sans : un PDF sans Poppins vaut
     * mieux qu'un PDF qui plante.
     */
    private static function policesDisponibles(): bool
    {
        $cacheDir = (string) config('dompdf.options.font_cache', storage_path('fonts'));
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
            return false;
        }
        foreach (array_merge(array_values(self::FONTS), array_values(self::FONTS_ITALIC)) as $fichiers) {
            foreach ($fichiers as $fichier) {
                if (!is_file(resource_path('fonts/' . $fichier))) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Règles @font-face pointant vers les TTF locaux (pas de réseau). */
    private static function fontFaceCss(): string
    {
        $css = '';
        foreach (self::FONTS as $famille => $poids) {
            foreach ($poids as $w => $fichier) {
                $css .= "@font-face{font-family:'{$famille}';font-style:normal;font-weight:{$w};"
                      . "src:url('" . self::fileUrl(resource_path('fonts/' . $fichier)) . "') format('truetype');}\n";
            }
        }
        foreach (self::FONTS_ITALIC as $famille => $poids) {
            foreach ($poids as $w => $fichier) {
                $css .= "@font-face{font-family:'{$famille}';font-style:italic;font-weight:{$w};"
                      . "src:url('" . self::fileUrl(resource_path('fonts/' . $fichier)) . "') format('truetype');}
";
            }
        }
        // DejaVu Sans (police de repli pour les symboles) : DomPDF ne connaît
        // que les poids 400/700 ; sans ces déclarations, un symbole dans un
        // texte en 600/800/900 retombait sur Times (glyphe manquant).
        $dejavuBold = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $dejavu     = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (is_file($dejavuBold) && is_file($dejavu)) {
            foreach ([300 => $dejavu, 500 => $dejavu, 600 => $dejavuBold, 800 => $dejavuBold, 900 => $dejavuBold] as $w => $f) {
                $css .= "@font-face{font-family:'DejaVu Sans';font-style:normal;font-weight:{$w};"
                      . "src:url('" . self::fileUrl($f) . "') format('truetype');}\n";
            }
        }
        // Idem pour la police à chasse fixe (références, montants alignés).
        $monoBold = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono-Bold.ttf');
        $mono     = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono.ttf');
        if (is_file($monoBold) && is_file($mono)) {
            foreach ([300 => $mono, 500 => $mono, 600 => $monoBold, 800 => $monoBold, 900 => $monoBold] as $w => $f) {
                $css .= "@font-face{font-family:'DejaVu Sans Mono';font-style:normal;font-weight:{$w};"
                      . "src:url('" . self::fileUrl($f) . "') format('truetype');}\n";
            }
        }
        return $css;
    }

    private static function fileUrl(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // « file://C:/… » sous Windows, « file:///var/… » sous Linux.
        return 'file://' . $path;
    }

    /** Mélange une couleur hex avec du blanc (alpha = part de la couleur). */
    private static function fondu(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $out = '#';
        foreach (str_split($hex, 2) as $canal) {
            $v = (int) round(hexdec($canal) * $alpha + 255 * (1 - $alpha));
            $out .= strtoupper(str_pad(dechex(max(0, min(255, $v))), 2, '0', STR_PAD_LEFT));
        }
        return $out;
    }
}
