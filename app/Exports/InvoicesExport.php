<?php
namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
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
 * Export Excel des factures (filtres index repris : client_id, status,
 * date_from/to). FromQuery streame les résultats — pas de chargement full
 * memory pour les comptes avec gros historique.
 */
class InvoicesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(protected array $filters = [])
    {
    }

    public function query()
    {
        $q = Invoice::query()
            ->with([
                'client:id,name',
                'campaign:id,name',
                'creator:id,name',
            ]);

        if (!empty($this->filters['client_id'])) {
            $q->where('client_id', $this->filters['client_id']);
        }
        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $q->where('issued_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $q->where('issued_at', '<=', $this->filters['date_to']);
        }

        return $q->orderByDesc('issued_at')->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Client',
            'Campagne',
            'Statut',
            'Date émission',
            'Date paiement',
            'Montant HT (FCFA)',
            'TVA (%)',
            'Montant TTC (FCFA)',
            'Créée par',
        ];
    }

    public function map($invoice): array
    {
        $statusLabels = [
            'brouillon' => 'Brouillon',
            'envoyee'   => 'Envoyée',
            'payee'     => 'Payée',
            'annulee'   => 'Annulée',
        ];

        return [
            $invoice->reference,
            $invoice->client?->name ?? '—',
            $invoice->campaign?->name ?? '—',
            $statusLabels[$invoice->status] ?? $invoice->status,
            $invoice->issued_at?->format('d/m/Y') ?? '',
            $invoice->paid_at?->format('d/m/Y') ?? '',
            (float) ($invoice->amount ?? 0),
            (float) ($invoice->tva ?? 0),
            (float) ($invoice->amount_ttc ?? 0),
            $invoice->creator?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'C2570D'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getRowDimension(1)->setRowHeight(28);

                $lastRow = $sheet->getHighestRow();

                // Format monétaire FCFA sur colonnes G (HT) et I (TTC)
                $sheet->getStyle("G2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                // TVA en pourcentage entier (0,5 → 0.5%)
                $sheet->getStyle("H2:H{$lastRow}")->getNumberFormat()->setFormatCode('0.##"%"');

                // Freeze header
                $sheet->freezePane('A2');

                // Ligne TOTAL en bas
                if ($lastRow > 1) {
                    $totalRow = $lastRow + 2;
                    $sheet->setCellValue("F{$totalRow}", 'TOTAL :');
                    $sheet->setCellValue("G{$totalRow}", "=SUM(G2:G{$lastRow})");
                    $sheet->setCellValue("I{$totalRow}", "=SUM(I2:I{$lastRow})");
                    $sheet->getStyle("F{$totalRow}:I{$totalRow}")
                        ->getFont()->setBold(true);
                    $sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                    $sheet->getStyle("I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                }
            },
        ];
    }
}
