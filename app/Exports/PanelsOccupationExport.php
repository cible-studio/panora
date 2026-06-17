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

class PanelsOccupationExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function __construct(
        protected Collection $panels,
        protected \Carbon\Carbon $from,
        protected \Carbon\Carbon $to,
        protected ?string $zoneLabel = null,
    ) {}

    public function title(): string { return 'Occupation panneaux'; }

    public function startCell(): string { return $this->brandingStartCell(); }

    public function collection() { return $this->panels; }

    public function headings(): array
    {
        return [
            'RÉFÉRENCE',
            'EMPLACEMENT',
            'COMMUNE',
            'ZONE',
            'TARIF / MOIS (FCFA)',
            'CAMPAGNES',
            'JOURS OCCUPÉS',
            "TAUX D'OCCUPATION",
            'CA ESTIMÉ (FCFA)',
            'STATUT',
        ];
    }

    public function map($p): array
    {
        return [
            $p->reference,
            $p->name ?? '—',
            $p->commune_name ?? '—',
            $p->zone ?? '—',
            $p->monthly_rate ? (int) $p->monthly_rate : 0,
            (int) $p->campaigns_count,
            (int) $p->days_occupied,
            ($p->occupation_rate ?? 0) . ' %',
            $p->estimated_revenue ? (int) round($p->estimated_revenue) : 0,
            match ($p->status ?? 'libre') {
                'libre'       => 'Disponible',
                'occupe'      => 'Occupé',
                'option'      => 'En option',
                'confirme'    => 'Confirmé',
                'maintenance' => 'Maintenance',
                default       => ucfirst((string) ($p->status ?? '—')),
            },
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
                $info = array_filter([
                    'Période : ' . $this->from->format('d/m/Y') . ' → ' . $this->to->format('d/m/Y'),
                    $this->zoneLabel ? 'Zone : ' . $this->zoneLabel : null,
                    $this->panels->count() . ' panneau(x)',
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                ]);
                $this->applyBrandingHeader($event, "OCCUPATION DES PANNEAUX", $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}
