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
 * Export Excel de l'onglet "Occupation détaillée".
 * Une ligne par couple Panneau × Campagne active sur la période.
 */
class OccupationDetailsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function __construct(
        protected Collection $rows,
        protected \Carbon\Carbon $from,
        protected \Carbon\Carbon $to,
    ) {}

    public function title(): string { return 'Occupation détaillée'; }

    public function startCell(): string { return $this->brandingStartCell(); }

    public function collection() { return $this->rows; }

    public function headings(): array
    {
        return [
            'COMMUNE',
            'VILLE',
            'RÉFÉRENCE PANNEAU',
            'NOM PANNEAU',
            'TYPE',
            'DIMENSIONS',
            'SURFACE (m²)',
            'CAMPAGNE',
            'STATUT CAMPAGNE',
            'CLIENT',
            'SECTEUR CLIENT',
            'DATE DÉBUT',
            'DATE FIN',
            'DURÉE',
            'DÉCAPÉ LE',
        ];
    }

    public function map($r): array
    {
        $statusLabel = match ((string) ($r['campaign_status'] ?? '')) {
            'actif'    => 'Actif',
            'planifie' => 'Planifié',
            'termine'  => 'Terminé',
            'pause'    => 'En pause',
            'annule'   => 'Annulé',
            default    => (string) ($r['campaign_status'] ?? '—'),
        };

        return [
            $r['commune'],
            $r['city'],
            $r['panel_ref'],
            $r['panel_name'],
            $r['panel_type'],
            $r['panel_dims'],
            $r['panel_surface'] !== null ? (float) $r['panel_surface'] : '',
            $r['campaign_name'],
            $statusLabel,
            $r['client_name'],
            $r['client_sector'],
            $r['campaign_start'] ? \Carbon\Carbon::parse($r['campaign_start'])->format('d/m/Y') : '',
            $r['campaign_end']   ? \Carbon\Carbon::parse($r['campaign_end'])->format('d/m/Y')   : '',
            $r['duration_label'],
            $r['decapped_at'] ? \Carbon\Carbon::parse($r['decapped_at'])->format('d/m/Y') : '',
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
                $info = [
                    'Période : ' . $this->from->format('d/m/Y') . ' → ' . $this->to->format('d/m/Y'),
                    $this->rows->count() . ' ligne(s) — '
                        . $this->rows->pluck('panel_id')->unique()->count() . ' panneau(x) — '
                        . $this->rows->pluck('campaign_id')->unique()->count() . ' campagne(s)',
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                ];
                $this->applyBrandingHeader($event, 'OCCUPATION DÉTAILLÉE — PANNEAUX × CAMPAGNES', $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}
