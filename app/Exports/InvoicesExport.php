<?php
namespace App\Exports;

use App\Exports\Concerns\ExcelBranding;
use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
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
 * Export Excel des factures (filtres index repris : client_id, status,
 * date_from/to). FromQuery streame les résultats — pas de chargement full
 * memory pour les comptes avec gros historique.
 *
 * Branding : bandeau PANORA (rows 1-4) + headings (row 5) + data (row 6+)
 * via le trait ExcelBranding.
 */
class InvoicesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function title(): string { return 'Factures'; }

    public function startCell(): string { return $this->brandingStartCell(); }

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

        // RBAC commercial : si l'appelant a injecté commercial_user_id,
        // on restreint le périmètre via le scope canonique. Sans ça,
        // l'export Excel streamait toutes les factures même appelé par
        // un commercial (FromQuery contourne la query du controller).
        if (!empty($this->filters['commercial_user_id'])) {
            $q->forCommercialUser((int) $this->filters['commercial_user_id']);
        }

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
        // Libellés alignés sur Invoice::STATUS_LABELS — M1 cosmétique.
        $statusLabels = \App\Models\Invoice::STATUS_LABELS;

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
            // Row 5 = headings (le bandeau occupe rows 1-4)
            5 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A0C10']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
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
                $this->applyBrandingHeader($event, 'LISTE DES FACTURES', array_filter([
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                    max(0, $lastRow - 5) . ' facture(s)',
                    !empty($this->filters['status'])    ? 'Statut : ' . $this->filters['status'] : null,
                    !empty($this->filters['client_id']) ? 'Client #' . $this->filters['client_id'] : null,
                    !empty($this->filters['date_from']) ? 'Depuis ' . $this->filters['date_from'] : null,
                    !empty($this->filters['date_to'])   ? 'Jusqu\'au ' . $this->filters['date_to'] : null,
                ]));

                // Format monétaire FCFA sur colonnes G (HT) et I (TTC) — data row 6+
                if ($lastRow >= 6) {
                    $sheet->getStyle("G6:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                    $sheet->getStyle("I6:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                    $sheet->getStyle("H6:H{$lastRow}")->getNumberFormat()->setFormatCode('0.##"%"');
                }

                // Ligne TOTAL en bas (somme HT + TTC)
                if ($lastRow >= 6) {
                    $totalRow = $lastRow + 2;
                    $sheet->setCellValue("F{$totalRow}", 'TOTAL :');
                    $sheet->setCellValue("G{$totalRow}", "=SUM(G6:G{$lastRow})");
                    $sheet->setCellValue("I{$totalRow}", "=SUM(I6:I{$lastRow})");
                    $sheet->getStyle("F{$totalRow}:I{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                    $sheet->getStyle("I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0 [$FCFA]');
                }

                // Bordures + alternance + freeze + print landscape
                $this->applyTableFinishing($event);
            },
        ];
    }
}
