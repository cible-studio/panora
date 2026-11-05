<?php
namespace App\Exports;

use App\Exports\Concerns\ExcelBranding;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Campagnes\CampagneFiltres;
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
 * Export Excel des campagnes (avec filtres appliqués depuis l'index admin).
 *
 * Performance : `FromQuery` streame les résultats par chunks (pas de chargement
 * full memory) — gère bien les exports 10k+ lignes.
 *
 * Branding : bandeau PANORA (rows 1-4) + headings (row 5) + data (row 6+)
 * via le trait ExcelBranding pour cohérence avec les PDFs.
 */
class CampaignsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function title(): string { return 'Campagnes'; }

    public function startCell(): string { return $this->brandingStartCell(); }

    /**
     * @param array     $filters  critères de la liste (CampagneFiltres::CLES),
     *                            + status, ou ['ids' => […]] pour une sélection
     * @param User|null $user     auteur de l'export : un commercial n'exporte
     *                            que SES campagnes (2026-11-05)
     */
    public function __construct(protected array $filters = [], protected ?User $user = null)
    {
    }

    public function query()
    {
        $q = Campaign::query()
            ->with(['client:id,name', 'user:id,name'])
            ->withCount('panels');

        // Sélection explicite (cases cochées) — prioritaire sur les filtres,
        // mais jamais hors du périmètre de l'utilisateur.
        if (!empty($this->filters['ids'])) {
            return CampagneFiltres::appliquer($q->whereIn('id', (array) $this->filters['ids']), [], $this->user)
                     ->orderByDesc('created_at');
        }

        // Mêmes critères que la liste (2026-11-05) : CampagneFiltres.
        CampagneFiltres::appliquer($q, $this->filters, $this->user);
        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
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
            // Row 5 = headings (le bandeau prend les rows 1-4)
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
                $sheet = $event->sheet->getDelegate();

                // Bandeau brandé PANORA (rows 1-4)
                $this->applyBrandingHeader($event, 'LISTE DES CAMPAGNES', array_filter([
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                    max(0, $sheet->getHighestRow() - 5) . ' campagne(s)',
                    !empty($this->filters['search']) ? 'Recherche : "' . $this->filters['search'] . '"' : null,
                    !empty($this->filters['status']) ? 'Statut : ' . $this->filters['status'] : null,
                    !empty($this->filters['client_id']) ? 'Client #' . $this->filters['client_id'] : null,
                ]));

                // Format monétaire colonne H (Montant) — data commence row 6
                $lastRow = $sheet->getHighestRow();
                if ($lastRow >= 6) {
                    $sheet->getStyle('H6:H' . $lastRow)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0 [$FCFA]');
                }

                // Bordures + alternance + freeze + print landscape
                $this->applyTableFinishing($event);
            },
        ];
    }
}
