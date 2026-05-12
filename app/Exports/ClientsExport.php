<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, WithCustomStartCell, ShouldAutoSize, WithTitle
{
    use Exportable;

    public function __construct(
        protected Collection $clients,
        protected array $filters = [],
    ) {}

    public function title(): string { return 'Clients'; }

    public function startCell(): string { return 'A5'; }

    public function collection(): Collection { return $this->clients; }

    public function headings(): array
    {
        return [
            'Nom', 'NCC', 'Secteur', 'Contact', 'Email', 'Téléphone',
            'Adresse', 'Campagnes', 'Camp. actives', 'Réservations',
            'Compte client', 'Créé le',
        ];
    }

    public function map($c): array
    {
        return [
            $c->name,
            $c->ncc          ?? '',
            $c->sector       ?? '',
            $c->contact_name ?? '',
            $c->email        ?? '',
            $c->phone        ?? '',
            $c->address      ?? '',
            (int) ($c->campaigns_count        ?? 0),
            (int) ($c->active_campaigns_count ?? 0),
            (int) ($c->reservations_count     ?? 0),
            (method_exists($c, 'hasAccount') && $c->hasAccount()) ? 'Actif' : 'Non actif',
            $c->created_at?->format('d/m/Y') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
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
                $lastCol = 'L';
                $lastRow = $sheet->getHighestRow();

                // ── Banner rows 1-4 ────────────────────────────────────────
                foreach (['A1', 'A2', 'A3', 'A4'] as $cell) {
                    $sheet->mergeCells("{$cell}:{$lastCol}" . substr($cell, 1));
                }

                // Fond sombre sur rows 1-3
                $sheet->getStyle("A1:{$lastCol}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0A0C10');

                // Row 1 : bande décorative fine
                $sheet->getRowDimension(1)->setRowHeight(8);

                // Row 2 : zone logo
                $sheet->getRowDimension(2)->setRowHeight(42);

                // Row 3 : titre document (aligné à droite pour laisser place au logo)
                $sheet->setCellValue('A3', 'LISTE DES CLIENTS — CIBLE CI');
                $sheet->getStyle('A3')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'E8A020'], 'size' => 13],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(24);

                // Row 4 : méta-données (filtres + date + nb)
                $meta = 'Généré le ' . now()->format('d/m/Y à H:i') . '   ·   ' . $this->clients->count() . ' client(s)';
                if (!empty($this->filters['search']))      $meta .= '   ·   Recherche : "' . $this->filters['search'] . '"';
                if (!empty($this->filters['sector']))      $meta .= '   ·   Secteur : ' . $this->filters['sector'];
                if (!empty($this->filters['active_only'])) $meta .= '   ·   Avec campagne active';

                $sheet->setCellValue('A4', $meta);
                $sheet->getStyle('A4')->applyFromArray([
                    'font'      => ['color' => ['rgb' => '6B7280'], 'size' => 8, 'italic' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(16);

                // Row 5 : en-têtes colonnes
                $sheet->getRowDimension(5)->setRowHeight(22);

                // ── Logo ──────────────────────────────────────────────────
                $logoPath = public_path('images/logob.png');
                if (file_exists($logoPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('CIBLE CI');
                    $drawing->setDescription('Logo CIBLE CI');
                    $drawing->setPath($logoPath);
                    $drawing->setHeight(34);
                    $drawing->setCoordinates('A2');
                    $drawing->setOffsetX(14);
                    $drawing->setOffsetY(4);
                    $drawing->setWorksheet($sheet);
                }

                // ── Zone données ───────────────────────────────────────────
                if ($lastRow >= 6) {
                    // Bordures légères
                    $sheet->getStyle("A5:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');

                    // Alternance de couleur de ligne
                    for ($r = 6; $r <= $lastRow; $r++) {
                        if ($r % 2 === 0) {
                            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FAFAFA');
                        }
                    }

                    // Colonnes numériques centrées (Campagnes, Actives, Réservations)
                    foreach (['H', 'I', 'J', 'K'] as $col) {
                        $sheet->getStyle("{$col}6:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Colonne "Compte client" — mise en évidence conditionnelle simple
                    for ($r = 6; $r <= $lastRow; $r++) {
                        $val = $sheet->getCell("K{$r}")->getValue();
                        if ($val === 'Actif') {
                            $sheet->getStyle("K{$r}")->getFont()->getColor()->setRGB('065F46');
                            $sheet->getStyle("K{$r}")->getFont()->setBold(true);
                        }
                    }
                }

                // ── Freeze header ──────────────────────────────────────────
                $sheet->freezePane('A6');

                // ── Impression ─────────────────────────────────────────────
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToPage(true)->setFitToWidth(1);
                $sheet->getHeaderFooter()
                    ->setOddHeader('&L&BCIBLE CI — Liste clients&R&P / &N')
                    ->setOddFooter('&CDocument confidentiel — CIBLE CI · Abidjan, Côte d\'Ivoire');
            },
        ];
    }
}
