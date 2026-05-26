<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Trait Excel : insère un bandeau de branding PANORA cohérent avec
 * les en-têtes PDF (#0d1117 + accent #e8a020 + logo CIBLE blanc).
 *
 * Pattern :
 *   - rows 1-4  : bandeau (logo + titre + sous-titre + meta)
 *   - row 5     : headings de colonnes (avec startCell A5)
 *   - row 6+    : data
 *
 * Usage dans une classe Export :
 *   class FooExport implements ... WithCustomStartCell ... {
 *       use Exportable, ExcelBranding;
 *       public function startCell(): string { return $this->brandingStartCell(); }
 *       public function registerEvents(): array {
 *           return [
 *               AfterSheet::class => function (AfterSheet $e) {
 *                   $this->applyBrandingHeader($e, 'LISTE DES CAMPAGNES', [
 *                       'Généré le ' . now()->format('d/m/Y à H:i'),
 *                       $e->sheet->getDelegate()->getHighestRow() - 5 . ' ligne(s)',
 *                   ]);
 *                   $this->applyTableFinishing($e);
 *               },
 *           ];
 *       }
 *   }
 *
 * Le bandeau couvre automatiquement de la colonne A à la dernière colonne
 * utilisée, et la zone data hérite de bordures + lignes alternées.
 */
trait ExcelBranding
{
    /**
     * Cellule de départ pour les headings — laisse les 4 premières rows
     * pour le bandeau brandé.
     */
    protected function brandingStartCell(): string
    {
        return 'A5';
    }

    /**
     * Bandeau 4 rows (logo + titre + sous-titre + meta) sur fond foncé.
     *
     * @param  AfterSheet  $event
     * @param  string      $title       Titre principal (ex: « LISTE DES CAMPAGNES »)
     * @param  array       $metaLines   Lignes de méta-données (date, filtres, nb)
     * @param  string|null $subtitle    Sous-titre optionnel (sous le titre)
     */
    protected function applyBrandingHeader(
        AfterSheet $event,
        string     $title,
        array      $metaLines = [],
        ?string    $subtitle  = null,
    ): void {
        $sheet   = $event->sheet->getDelegate();
        $lastCol = $sheet->getHighestColumn();

        // ── Merge des 4 premières rows sur toute la largeur ──────────
        foreach (['A1', 'A2', 'A3', 'A4'] as $cell) {
            $sheet->mergeCells("{$cell}:{$lastCol}" . substr($cell, 1));
        }

        // ── Fond foncé sur rows 1-3 (cohérence avec pdf-header #0d1117) ──
        $sheet->getStyle("A1:{$lastCol}3")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0D1117');

        // ── Row 1 : bande décorative fine ───────────────────────────
        $sheet->getRowDimension(1)->setRowHeight(8);

        // ── Row 2 : zone réservée au logo (Drawing posé en A2) ──────
        $sheet->getRowDimension(2)->setRowHeight(42);

        // ── Row 3 : titre (orange accent) à droite ──────────────────
        $sheet->setCellValue('A3', $title);
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'E8A020'], 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'indent'     => 1,
            ],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(28);

        // ── Row 4 : sous-titre + meta (fond gris clair) ─────────────
        $meta = $subtitle ? $subtitle . '   ·   ' : '';
        $meta .= implode('   ·   ', array_filter($metaLines));
        $sheet->setCellValue('A4', $meta);
        $sheet->getStyle('A4')->applyFromArray([
            'font'      => ['color' => ['rgb' => '6B7280'], 'size' => 9, 'italic' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'indent'     => 1,
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(18);

        // ── Row 5 : headings (style appliqué via la classe Export) ──
        $sheet->getRowDimension(5)->setRowHeight(24);

        // ── Logo CIBLE CI (blanc sur fond bleu, visible sur noir) ───
        // On utilise logob.png en priorité (logo officiel pour fond foncé).
        // Si absent, fallback sur logon.png. Sinon, on n'ajoute rien
        // (le titre orange reste lisible seul).
        $logoCandidates = [
            public_path('images/logob.png'),
            public_path('images/logon.png'),
        ];
        foreach ($logoCandidates as $logoPath) {
            if (is_file($logoPath) && is_readable($logoPath)) {
                $drawing = new Drawing();
                $drawing->setName('CIBLE CI');
                $drawing->setDescription('Logo CIBLE CI');
                $drawing->setPath($logoPath);
                $drawing->setHeight(34);
                $drawing->setCoordinates('A2');
                $drawing->setOffsetX(14);
                $drawing->setOffsetY(4);
                $drawing->setWorksheet($sheet);
                break;
            }
        }
    }

    /**
     * Applique les finitions de la zone data (bordures, lignes alternées,
     * freeze, print landscape fit-to-width). Optionnel — utile pour la
     * plupart des exports tabulaires.
     */
    protected function applyTableFinishing(AfterSheet $event, int $headerRow = 5): void
    {
        $sheet   = $event->sheet->getDelegate();
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $dataStart = $headerRow + 1;

        if ($lastRow >= $dataStart) {
            // Bordures fines sur toute la zone (header + data)
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                ->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setRGB('E5E7EB');

            // Alternance de couleur sur les rows paires (à partir de la première data row)
            for ($r = $dataStart; $r <= $lastRow; $r++) {
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FAFAFA');
                }
            }
        }

        // Freeze : on garde le bandeau + le header visibles au scroll
        $sheet->freezePane('A' . $dataStart);

        // Print : paysage, fit-to-width, header/footer brandé
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
        $sheet->getHeaderFooter()
            ->setOddHeader('&L&BPANORA &Bopéré par ' . $operator . '&R&P / &N')
            ->setOddFooter('&CDocument confidentiel — ' . $operator . ' · ' . date('Y'));
    }
}
