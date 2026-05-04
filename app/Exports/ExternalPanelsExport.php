<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExternalPanelsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $panels;
    protected $startDate;
    protected $endDate;
    protected $hideStatus;
    protected $agencyName;

    public function __construct($panels, $startDate = null, $endDate = null, $hideStatus = true, ?string $agencyName = null)
    {
        $this->panels     = $panels;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->hideStatus = $hideStatus;
        $this->agencyName = $agencyName;
    }

    public function collection()
    {
        return $this->panels;
    }

    public function title(): string
    {
        return $this->agencyName
            ? \Illuminate\Support\Str::limit($this->agencyName, 28, '')
            : 'Panneaux régie externe';
    }

    public function headings(): array
    {
        $headings = [
            'CODE PANNEAU',
            'DÉSIGNATION',
            'COMMUNE',
            'ZONE',
            'FORMAT',
            'DIMENSIONS (m)',
            'CATÉGORIE',
            'FACES',
            'ÉCLAIRAGE',
            'TRAFIC/JOUR',
            'TARIF MENSUEL (FCFA)',
            'CLIENT ACTUEL',
            'CAMPAGNE',
        ];

        if (!$this->hideStatus) {
            $headings[] = 'STATUT';
        }

        if ($this->startDate && $this->endDate) {
            $headings[] = 'NOMBRE MOIS';
            $headings[] = 'TOTAL PÉRIODE (FCFA)';
        }

        return $headings;
    }

    public function map($panel): array
    {
        $dims = null;
        if ($panel->format?->width && $panel->format?->height) {
            $w = rtrim(rtrim(number_format($panel->format->width, 2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.');
            $dims = "{$w}x{$h}";
        }

        $months = null;
        $totalPeriode = null;
        if ($this->startDate && $this->endDate) {
            $months = $this->calculateMonths($this->startDate, $this->endDate);
            $totalPeriode = ($panel->monthly_rate ?? 0) * $months;
        }

        $row = [
            $panel->code_panneau,
            $panel->designation,
            $panel->commune?->name ?? '—',
            $panel->zone?->name ?? '—',
            $panel->format?->name ?? '—',
            $dims ?? '—',
            $panel->category?->name ?? ($panel->type ?? '—'),
            (int) ($panel->nombre_faces ?? 1),
            $panel->is_lit ? 'Éclairé' : 'Non éclairé',
            $panel->daily_traffic ? number_format($panel->daily_traffic, 0, ',', ' ') : '—',
            $panel->monthly_rate ? number_format($panel->monthly_rate, 0, ',', ' ') : '—',
            $panel->client?->name ?? '—',
            $panel->campaign?->name ?? '—',
        ];

        if (!$this->hideStatus) {
            $statusLabel = match ($panel->availability_status ?? 'disponible') {
                'disponible'  => 'Disponible',
                'occupe'      => 'Occupé',
                'option'      => 'En option',
                'confirme'    => 'Confirmé',
                'maintenance' => 'Maintenance',
                default       => ucfirst((string) ($panel->availability_status ?? 'inconnu')),
            };
            $row[] = $statusLabel;
        }

        if ($this->startDate && $this->endDate) {
            $row[] = $months ?? '—';
            $row[] = $totalPeriode ? number_format($totalPeriode, 0, ',', ' ') : '—';
        }

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8A020'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A2:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        return [];
    }

    private function calculateMonths($start, $end): float
    {
        $s = \Carbon\Carbon::parse($start)->startOfDay();
        $e = \Carbon\Carbon::parse($end)->startOfDay();
        $totalDays = (int) $s->diffInDays($e);

        if ($totalDays <= 0) return 0.5;

        $fullMonths = (int) floor($totalDays / 30);
        $remainDays = $totalDays % 30;

        $fraction = 0;
        if ($remainDays >= 1 && $remainDays <= 15) {
            $fraction = 0.5;
        } elseif ($remainDays > 15) {
            $fraction = 1;
        }

        return max($fullMonths + $fraction, 0.5);
    }
}
