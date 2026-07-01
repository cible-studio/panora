<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelBranding;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Excel de l'onglet "Zones & Communes".
 * Une ligne par commune avec stats occupation + CA sur la période.
 */
class ZonesCommunesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function __construct(
        protected Collection $rows,
        protected \Carbon\Carbon $from,
        protected \Carbon\Carbon $to,
        protected array $summary = [],
    ) {}

    public function title(): string { return 'Zones & Communes'; }

    public function startCell(): string { return $this->brandingStartCell(); }

    public function collection() { return $this->rows; }

    public function headings(): array
    {
        return [
            'COMMUNE',
            'VILLE',
            'ZONE',
            'TOTAL PANNEAUX',
            'OCCUPÉS',
            'LIBRES',
            'MAINTENANCE',
            "TAUX D'OCCUPATION",
            'TARIF MOYEN / MOIS (FCFA)',
            'CA CONTRACTUEL (FCFA)',
        ];
    }

    public function map($r): array
    {
        return [
            $r['commune'],
            $r['city'],
            $r['zone'],
            (int) $r['total'],
            (int) $r['occupes'],
            (int) $r['libres'],
            (int) $r['maintenance'],
            ($r['taux'] ?? 0) . ' %',
            (int) $r['tarif_moyen'],
            (int) $r['ca_annee'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A5:' . $sheet->getHighestColumn() . '5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A0C10']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $S = $this->summary;
                $info = array_filter([
                    'Période : ' . $this->from->format('d/m/Y') . ' → ' . $this->to->format('d/m/Y'),
                    ($S['nb_communes'] ?? $this->rows->count()) . ' commune(s) — '
                        . ($S['nb_panels'] ?? 0) . ' panneau(x) — '
                        . ($S['nb_occupes'] ?? 0) . ' occupé(s) — '
                        . 'Taux moyen : ' . ($S['taux_moyen'] ?? 0) . ' %',
                    isset($S['ca_total']) ? 'CA contractuel total : ' . number_format($S['ca_total'], 0, ',', ' ') . ' FCFA' : null,
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                ]);
                $this->applyBrandingHeader($event, 'ZONES & COMMUNES — OCCUPATION DU PARC', $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}
