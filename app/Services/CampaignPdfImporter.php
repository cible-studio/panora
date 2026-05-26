<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Parse un PDF "Liste des panneaux commandes" et extrait :
 *   - Nom du client
 *   - Nom de la campagne
 *   - Période (start_date, end_date)
 *   - Codes panneaux (références)
 *
 * Format attendu :
 *   - "Periode du DD/MM/YYYY au DD/MM/YYYY"
 *   - "CLIENT : <nom>"
 *   - "CAMPAGNE: <nom>" (ou CAMPAGNE :)
 *   - Tables groupées par commune avec colonnes Code · Désignation · Format
 *
 * Si le PDF est une image scannée (pas de texte extractible), retourne une
 * exception claire — pas d'OCR ici.
 */
class CampaignPdfImporter
{
    /**
     * Extrait les données du PDF.
     *
     * @return array{
     *     client: string,
     *     campaign: string,
     *     start_date: \Carbon\Carbon,
     *     end_date: \Carbon\Carbon,
     *     codes: string[],
     *     raw_text: string
     * }
     */
    public function extract(UploadedFile $file): array
    {
        $parser = new Parser();

        try {
            $pdf  = $parser->parseFile($file->getRealPath());
            $text = $pdf->getText();
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Impossible de lire le PDF. Le fichier est-il un PDF texte valide ? (Erreur : {$e->getMessage()})"
            );
        }

        // Si le texte extrait est vide, c'est probablement un scan.
        if (trim($text) === '') {
            throw new RuntimeException(
                "Le PDF semble être une image scannée (aucun texte extractible). " .
                "Demande au client une version texte (PDF généré directement, pas scanné)."
            );
        }

        return [
            'client'     => $this->extractClient($text),
            'campaign'   => $this->extractCampaign($text),
            'start_date' => $this->extractStartDate($text),
            'end_date'   => $this->extractEndDate($text),
            'codes'      => $this->extractPanelCodes($text),
            'raw_text'   => $text,
        ];
    }

    private function extractClient(string $text): string
    {
        return $this->extractField($text, 'CLIENT', 'Nom du client');
    }

    private function extractCampaign(string $text): string
    {
        return $this->extractField($text, 'CAMPAGNE', 'Nom de la campagne');
    }

    /**
     * Tente d'extraire la valeur d'un libellé tabulaire genre "LABEL : valeur".
     * Couvre 3 cas (le PDF extrait peut placer la valeur n'importe où selon
     * comment pdfparser tokenise une mise en page en colonnes) :
     *   1) Même ligne  : "CAMPAGNE : HIGOLD"
     *   2) Ligne suivante : "CAMPAGNE :\n  HIGOLD"
     *   3) Plusieurs lignes plus loin : "CAMPAGNE :\nCLIENT :\nSOGELEC\nHIGOLD"
     *      (extraction par colonnes — on prend la 2e occurrence non-libellée).
     */
    private function extractField(string $text, string $label, string $human): string
    {
        // 1) Valeur sur la même ligne que le libellé
        if (preg_match('/' . preg_quote($label, '/') . '\s*:?\s*([^\r\n]+)/iu', $text, $m)) {
            $value = $this->cleanFieldValue($m[1]);
            if ($value !== '') return $value;
        }

        // 2) Valeur sur la ligne directement suivante
        if (preg_match('/' . preg_quote($label, '/') . '\s*:?\s*[\r\n]+\s*(\S[^\r\n]*)/iu', $text, $m)) {
            $value = $this->cleanFieldValue($m[1]);
            if ($value !== '') return $value;
        }

        // Dernier recours : on dump un extrait du texte parsé pour aider
        // l'utilisateur à diagnostiquer un layout PDF non-standard.
        $excerpt = mb_substr(preg_replace('/\s+/', ' ', trim($text)), 0, 280);
        throw new RuntimeException(
            "{$human} introuvable dans le PDF. Texte parsé (extrait) : « {$excerpt}… »"
        );
    }

    /**
     * Nettoie une valeur capturée :
     *   - trim espaces début/fin
     *   - coupe au premier "espace large" (2+ espaces consécutifs) qui
     *     marque souvent une frontière de colonne tabulaire
     *   - retire les caractères de contrôle invisibles
     */
    private function cleanFieldValue(string $value): string
    {
        $value = trim($value);
        // 2+ espaces consécutifs = séparateur de colonne → on garde la 1ère
        $parts = preg_split('/\s{2,}/', $value);
        $value = trim($parts[0] ?? '');
        // Retire caractères de contrôle (sauf espaces normaux)
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        return trim($value);
    }

    private function extractStartDate(string $text): Carbon
    {
        return Carbon::createFromFormat('d/m/Y', $this->extractPeriodDate($text, 'start'))->startOfDay();
    }

    private function extractEndDate(string $text): Carbon
    {
        return Carbon::createFromFormat('d/m/Y', $this->extractPeriodDate($text, 'end'))->endOfDay();
    }

    /**
     * Récupère start/end depuis le bloc "Periode du JJ/MM/AAAA au JJ/MM/AAAA".
     * Tolère les retours à la ligne / colonnes éparpillées par pdfparser :
     *   - Cas idéal : tout sur une ligne (1 regex)
     *   - Fallback  : on prend les 2 premières dates DD/MM/AAAA qui suivent
     *                 le mot "Periode" dans le texte.
     */
    private function extractPeriodDate(string $text, string $which): string
    {
        // 1) Cas idéal — même ligne
        if (preg_match(
            '/P[ée]riode\s+du\s+(\d{2}\/\d{2}\/\d{4})\s+au\s+(\d{2}\/\d{2}\/\d{4})/iu',
            $text, $m
        )) {
            return $which === 'start' ? $m[1] : $m[2];
        }

        // 2) Fallback — split par pdfparser : on trouve "Periode", puis les
        // 2 premières dates DD/MM/YYYY qui le suivent (ordre lecture).
        if (preg_match('/P[ée]riode/iu', $text, $pMatch, PREG_OFFSET_CAPTURE)) {
            $after = mb_substr($text, $pMatch[0][1]);
            preg_match_all('/\b(\d{2}\/\d{2}\/\d{4})\b/', $after, $dates);
            if (count($dates[1] ?? []) >= 2) {
                return $which === 'start' ? $dates[1][0] : $dates[1][1];
            }
        }

        // 3) Dernier recours — toutes les dates DD/MM/YYYY du document,
        // on prend les 2 premières en filtrant celles trop proches du jour
        // d'impression (premier match = "Imprimé le X" parfois).
        preg_match_all('/\b(\d{2}\/\d{2}\/\d{4})\b/', $text, $allDates);
        $candidates = array_values(array_unique($allDates[1] ?? []));
        if (count($candidates) >= 2) {
            // Si la 1re date est isolée près de "Imprim", on la skip.
            $skipFirst = (bool) preg_match('/Imprim[ée]\s+le\s+' . preg_quote($candidates[0], '/') . '/iu', $text);
            if ($skipFirst && count($candidates) >= 3) {
                return $which === 'start' ? $candidates[1] : $candidates[2];
            }
            return $which === 'start' ? $candidates[0] : $candidates[1];
        }

        $excerpt = mb_substr(preg_replace('/\s+/', ' ', trim($text)), 0, 280);
        throw new RuntimeException(
            "Période introuvable dans le PDF (date " . ($which === 'start' ? 'de début' : 'de fin') . " manquante). " .
            "Texte parsé (extrait) : « {$excerpt}… »"
        );
    }

    /**
     * Extrait tous les codes panneaux du PDF.
     *
     * Heuristique : un code commence par 2+ lettres majuscules, un tiret,
     * puis des alphanum (ex: CDY-041B, CDYT2-001A, ABJ-XYZ-99).
     * Les codes sont dédupliqués et l'ordre du PDF est préservé.
     */
    private function extractPanelCodes(string $text): array
    {
        // Lignes du tableau : code à gauche, désignation, format à droite.
        // On capture les codes qui apparaissent au début d'une ligne ou
        // après whitespace — pattern défensif pour ne pas capturer
        // n'importe quel mot en majuscules au milieu du texte.
        preg_match_all('/(?:^|\s)([A-Z]{2,}[A-Z0-9]*-[A-Z0-9]+(?:-[A-Z0-9]+)?)\b/mu', $text, $matches);

        $codes = $matches[1] ?? [];

        // Déduplication tout en gardant l'ordre.
        $seen = [];
        $unique = [];
        foreach ($codes as $code) {
            $u = strtoupper(trim($code));
            if (!isset($seen[$u])) {
                $seen[$u] = true;
                $unique[] = $u;
            }
        }

        if (empty($unique)) {
            throw new RuntimeException(
                "Aucun code panneau détecté dans le PDF. " .
                "Vérifie que le PDF contient bien un tableau avec des codes au format AAA-NNN."
            );
        }

        return $unique;
    }
}
