<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * PDF du devis — point de passage unique (2026-11-19, lot B).
 *
 * Les quatre chemins qui produisent le PDF d'un devis passent par ici :
 * bouton admin (QuoteController::exportPdf), espace client
 * (ClientQuoteController::pdf), lien public (PublicQuoteController::pdf)
 * et pièce jointe du mail d'envoi (QuoteSentMail). Avant, chacun appelait
 * DomPDF de son côté : un réglage oublié sur un chemin aurait donné au
 * client un PDF différent de celui du commercial.
 *
 * fontHeightRatio 0,85 (cahier des charges, lot B) : sans lui, DomPDF
 * donne aux lignes une hauteur de 1,1 fois la police et le devis déborde
 * sur une troisième page. Le réglage est posé SUR CE RENDU SEULEMENT —
 * jamais dans config/dompdf.php, qui toucherait tous les PDF, dont la
 * facture FNE. La façade crée un DomPDF neuf à chaque appel : l'option ne
 * déborde pas sur le PDF suivant.
 */
class QuotePdf
{
    /** Hauteur de ligne du devis, en proportion de la taille de police. */
    public const FONT_HEIGHT_RATIO = 0.85;

    /** PDF prêt à télécharger (->download) ou à joindre (->output). */
    public function pdf(Quote $quote): DomPdf
    {
        $quote->loadMissing(['client', 'lines', 'services']);

        return Pdf::loadView('pdf.quote', ['quote' => $quote])
            ->setPaper('A4', 'portrait')
            ->setOption('fontHeightRatio', self::FONT_HEIGHT_RATIO);
    }

    /**
     * Nom du PDF joint au mail du devis (2026-11-23) : le commercial peut le
     * changer dans la fenêtre d'envoi (« Devis CIBLE - Black Friday » au
     * lieu de « Devis-DEV-2026-009 »). Sans saisie, ou si la saisie est
     * vide après nettoyage : le nom habituel.
     *
     * Nettoyage : caractères interdits dans un nom de fichier (\ / : * ? " < > |
     * et caractères de contrôle) retirés, espaces resserrés, 120 caractères
     * au plus, « .pdf » ajouté s'il manque.
     */
    public static function nomPieceJointe(Quote $quote, ?string $souhaite = null): string
    {
        $nom = preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/u', ' ', (string) $souhaite) ?? '';
        $nom = trim(preg_replace('/\s+/u', ' ', $nom) ?? '', " .\t");
        $nom = preg_replace('/\.pdf$/iu', '', $nom) ?? '';
        $nom = trim(mb_substr($nom, 0, 120), " .");

        return ($nom !== '' ? $nom : self::nomPieceJointeParDefaut($quote)) . '.pdf';
    }

    /** Nom habituel, sans extension : « Devis-DEV-2026-009 ». */
    public static function nomPieceJointeParDefaut(Quote $quote): string
    {
        return 'Devis-' . $quote->reference;
    }
}
