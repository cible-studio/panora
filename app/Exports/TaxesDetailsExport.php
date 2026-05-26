<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelBranding;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
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
class TaxesDetailsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function __construct(
        protected Collection $lines,
        protected string $periodLabel,
        protected string $filterSummary = '',
    ) {}

    public function title(): string { return 'Taxes'; }

    public function startCell(): string { return $this->brandingStartCell(); }

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
            // Row 5 = headings (le bandeau brandé occupe rows 1-4)
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
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
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Bandeau brandé PANORA (rows 1-4)
                $this->applyBrandingHeader($event, 'TAXES COMMUNALES — ' . strtoupper($this->periodLabel), array_filter([
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                    max(0, $lastRow - 5) . ' ligne(s)',
                    $this->filterSummary ?: null,
                ]));

                if ($lastRow < 6) {
                    $this->applyTableFinishing($event);
                    return;
                }

                // Formats numériques sur les colonnes data (row 6+)
                $sheet->getStyle("E6:E{$lastRow}")
                      ->getNumberFormat()->setFormatCode('0.00');                  // Surface
                $sheet->getStyle("M6:M{$lastRow}")
                      ->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');         // Tarif
                $sheet->getStyle("N6:N{$lastRow}")
                      ->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');         // Montant

                // Ligne TOTAL en bas
                $totalRow = $lastRow + 2;
                $sheet->setCellValue("L{$totalRow}", 'TOTAL :');
                $sheet->setCellValue("N{$totalRow}", "=SUM(N6:N{$lastRow})");
                $sheet->getStyle("L{$totalRow}:N{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("L{$totalRow}:N{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF7ED');
                $sheet->getStyle("N{$totalRow}")
                      ->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');

                $this->applyTableFinishing($event);
            },
        ];
    }
}
