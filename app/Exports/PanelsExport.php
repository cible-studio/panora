<?php

namespace App\Exports;

use App\Models\Panel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PanelsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $panels;
    protected $startDate;
    protected $endDate;
    protected $hideStatus;

    public function __construct($panels, $startDate = null, $endDate = null, $hideStatus = false)
    {
        $this->panels = $panels;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->hideStatus = $hideStatus;
    }

    public function collection()
    {
        return $this->panels;
    }

    public function headings(): array
    {
        $headings = [
            'RÉFÉRENCE',
            'EMPLACEMENT',
            'COMMUNE',
            'ZONE',
            'FORMAT',
            'DIMENSIONS (m)',
            'CATÉGORIE',
            'ÉCLAIRAGE',
            'TRAFIC/JOUR',
            'TARIF MENSUEL (FCFA)',
        ];
        
        if (!$this->hideStatus) {
            $headings[] = 'STATUT';
        }
        
        if ($this->startDate && $this->endDate) {
            $headings[] = 'TOTAL PÉRIODE (FCFA)';
            $headings[] = 'NOMBRE MOIS';
        }
        
        return $headings;
    }

    public function map($panel): array
    {
        // Dimensions
        $dims = null;
        if ($panel->format?->width && $panel->format?->height) {
            $w = rtrim(rtrim(number_format($panel->format->width, 2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.');
            $dims = "{$w}x{$h}";
        }

        // Calcul du total période
        $totalPeriode = null;
        $months = null;
        if ($this->startDate && $this->endDate) {
            $months = $this->calculateMonths($this->startDate, $this->endDate);
            $totalPeriode = ($panel->monthly_rate ?? 0) * $months;
        }

        $row = [
            $panel->reference,
            $panel->name,
            $panel->commune?->name ?? '—',
            $panel->zone?->name ?? '—',
            $panel->format?->name ?? '—',
            $dims ?? '—',
            $panel->category?->name ?? '—',
            $panel->is_lit ? '💡 Éclairé' : 'Non éclairé',
            $panel->daily_traffic ? number_format($panel->daily_traffic, 0, ',', ' ') : '—',
            $panel->monthly_rate ? number_format($panel->monthly_rate, 0, ',', ' ') : '—',
        ];
        
        if (!$this->hideStatus) {
            $statusLabel = match($panel->status->value) {
                'libre' => 'Disponible',
                'occupe' => 'Occupé',
                'option' => 'En option',
                'confirme' => 'Confirmé',
                'maintenance' => 'Maintenance',
                default => ucfirst($panel->status->value),
            };
            $row[] = $statusLabel;
        }
        
        if ($this->startDate && $this->endDate) {
            $row[] = $totalPeriode ? number_format($totalPeriode, 0, ',', ' ') : '—';
            $row[] = $months ?? '—';
        }
        
        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        // Style de l'en-tête
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E20613'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Style des cellules
        $sheet->getStyle('A2:' . $sheet->getHighestColumn() . $sheet->getHighestRow())->applyFromArray([
            'font' => [
                'size' => 10,
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Largeurs automatiques déjà gérées par ShouldAutoSize
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