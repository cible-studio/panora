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
}
