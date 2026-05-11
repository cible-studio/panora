<?php
namespace App\Exports;

use App\Models\Campaign;
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
 * Export Excel des campagnes (avec filtres appliqués depuis l'index admin).
 *
 * Performance : `FromQuery` streame les résultats par chunks (pas de chargement
 * full memory) — gère bien les exports 10k+ lignes.
 */
class CampaignsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(protected array $filters = [])
    {
    }

    public function query()
    {
        $q = Campaign::query()
            ->with(['client:id,name', 'user:id,name'])
            ->withCount('panels');

        if (!empty($this->filters['search'])) {
            $q->where('name', 'like', '%' . $this->filters['search'] . '%');
        }
        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['client_id'])) {
            $q->where('client_id', $this->filters['client_id']);
        }
        if (!empty($this->filters['date_debut'])) {
            $q->where('start_date', '>=', $this->filters['date_debut']);
        }
        if (!empty($this->filters['date_fin'])) {
            $q->where('start_date', '<=', $this->filters['date_fin']);
        }
        if (!empty($this->filters['date_from'])) {
            $q->where('start_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $q->where('end_date', '<=', $this->filters['date_to']);
        }

        return $q->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Nom de la campagne',
            'Client',
            'Statut',
            'Date début',
            'Date fin',
            'Nb panneaux',
            'Montant total (FCFA)',
            'Créée par',
            'Créée le',
        ];
    }

    public function map($campaign): array
    {
        return [
            (string) ($campaign->id),
            $campaign->name,
            $campaign->client?->name ?? '—',
            $campaign->status?->label() ?? (string) $campaign->status,
            $campaign->start_date?->format('d/m/Y') ?? '',
            $campaign->end_date?->format('d/m/Y') ?? '',
            (int) ($campaign->panels_count ?? 0),
            (float) ($campaign->total_amount ?? 0),
            $campaign->user?->name ?? '—',
            $campaign->created_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Première ligne (en-têtes) en gras + fond orange
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
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
                // Hauteur ligne entête
                $sheet->getRowDimension(1)->setRowHeight(28);
                // Format monétaire colonne H (Montant)
                $sheet->getStyle('H2:H' . $sheet->getHighestRow())
                    ->getNumberFormat()
                    ->setFormatCode('#,##0 [$FCFA]');
                // Freeze header row
                $sheet->freezePane('A2');
            },
        ];
    }
}
