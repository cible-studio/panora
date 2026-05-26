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
        // "CLIENT : SOGELEC" — accepte ":" ou ": " ou "  : "
        if (preg_match('/CLIENT\s*:?\s*([^\r\n]+)/iu', $text, $m)) {
            return trim($m[1]);
        }
        throw new RuntimeException("Nom du client introuvable dans le PDF (attendu : « CLIENT : ... »).");
    }

    private function extractCampaign(string $text): string
    {
        // "CAMPAGNE: HIGOLD" ou "CAMPAGNE : HIGOLD"
        if (preg_match('/CAMPAGNE\s*:?\s*([^\r\n]+)/iu', $text, $m)) {
            return trim($m[1]);
        }
        throw new RuntimeException("Nom de la campagne introuvable dans le PDF (attendu : « CAMPAGNE : ... »).");
    }

    private function extractStartDate(string $text): Carbon
    {
        // "Periode du 01/12/2025 au 28/02/2026"
        if (preg_match('/P[ée]riode\s+du\s+(\d{2}\/\d{2}\/\d{4})/iu', $text, $m)) {
            return Carbon::createFromFormat('d/m/Y', $m[1])->startOfDay();
        }
        throw new RuntimeException("Date de début introuvable (attendu : « Periode du JJ/MM/AAAA au ... »).");
    }

    private function extractEndDate(string $text): Carbon
    {
        if (preg_match('/P[ée]riode\s+du\s+\d{2}\/\d{2}\/\d{4}\s+au\s+(\d{2}\/\d{2}\/\d{4})/iu', $text, $m)) {
            return Carbon::createFromFormat('d/m/Y', $m[1])->endOfDay();
        }
        throw new RuntimeException("Date de fin introuvable (attendu : « ... au JJ/MM/AAAA »).");
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
