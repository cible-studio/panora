<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel du rapport détaillé des taxes communales (Évolution 4 — UI).
 *
 * Reçoit directement la collection de lignes produite par
 * TaxCalculationService::generateLines() — pas de query rejouée pour
 * garantir la cohérence stricte avec ce qu'affiche /admin/taxes/details
 * et le PDF (mêmes filtres, mêmes tarifs historiques).
 *
 * Format colonnes :
 *   Commune · Panneau · Nom · Dim. · Surface · Type · Statut · Client ·
 *   Campagne · Période début · Période fin · Mois · Tarif · Montant
 */
class TaxesDetailsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(
        protected Collection $lines,
        protected string $periodLabel,
        protected string $filterSummary = '',
    ) {}

    public function collection()
    {
        return $this->lines;
    }

    public function headings(): array
    {
        return [
            'Commune',
            'Réf. panneau',
            'Nom panneau',
            'Dimensions',
            'Surface (m²)',
            'Type taxe',
            'Statut',
            'Client',
            'Campagne',
            'Période début',
            'Période fin',
            'Mois',
            'Tarif (FCFA)',
            'Montant (FCFA)',
        ];
    }

    public function map($row): array
    {
        $typeLabels = ['tm' => 'TM', 'odp' => 'ODP', 'db' => 'DB'];
        $statutLabels = [
            'libre'       => 'Libre',
            'occupe'      => 'Occupé',
            'option'      => 'Option',
            'confirme'    => 'Confirmé',
            'maintenance' => 'Maintenance',
        ];

        // generateLines() retourne des arrays — accès par clé.
        return [
            $row['commune'] ?? '',
            $row['reference'] ?? '',
            $row['name'] ?? '',
            $row['dimensions'] ?? '',
            (float) ($row['surface'] ?? 0),
            $typeLabels[$row['type'] ?? ''] ?? $row['type'] ?? '',
            $statutLabels[$row['statut'] ?? ''] ?? $row['statut'] ?? '',
            $row['client_name'] ?? '',
            $row['campaign_name'] ?? '',
            isset($row['period_start']) ? $row['period_start']->format('d/m/Y') : '',
            isset($row['period_end'])   ? $row['period_end']->format('d/m/Y')   : '',
            (int) ($row['months'] ?? 0),
            (float) ($row['rate'] ?? 0),
            (float) ($row['amount'] ?? 0),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0A0C10'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getRowDimension(1)->setRowHeight(30);

                $lastRow = $sheet->getHighestRow();
                if ($lastRow < 2) return;

                // Formats numériques
                $sheet->getStyle("E2:E{$lastRow}")
                      ->getNumberFormat()->setFormatCode('0.00');                  // Surface
                $sheet->getStyle("M2:M{$lastRow}")
                      ->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');         // Tarif
                $sheet->getStyle("N2:N{$lastRow}")
                      ->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');         // Montant

                // Freeze header
                $sheet->freezePane('A2');

                // Ligne TOTAL en bas
                $totalRow = $lastRow + 2;
                $sheet->setCellValue("L{$totalRow}", 'TOTAL :');
                $sheet->setCellValue("N{$totalRow}", "=SUM(N2:N{$lastRow})");
                $sheet->getStyle("L{$totalRow}:N{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("L{$totalRow}:N{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF7ED');
                $sheet->getStyle("N{$totalRow}")
                      ->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');

                // Méta-données en haut (info filtres) sur des lignes au-dessus
                // serait idéal mais Maatwebsite ne supporte pas l'insertion de
                // lignes sans tout recalculer. Solution simple : on met la
                // période et les filtres dans une feuille séparée si besoin.
                // Pour ne pas alourdir, on ajoute juste une bordure haut/bas
                // sur les rangées de données.
                $sheet->getStyle("A2:N{$lastRow}")->getBorders()->getBottom()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR);

                // Titre fichier visible dans l'aperçu impression
                $sheet->getPageSetup()->setOrientation(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
                );
                $sheet->getHeaderFooter()->setOddHeader(
                    '&L&BCIBLE CI — Taxes ' . $this->periodLabel .
                    ($this->filterSummary ? '&C' . $this->filterSummary : '')
                );
            },
        ];
    }
}
