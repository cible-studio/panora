<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelBranding;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, WithCustomStartCell, ShouldAutoSize, WithTitle
{
    use Exportable, ExcelBranding;

    public function __construct(
        protected Collection $clients,
        protected array $filters = [],
    ) {}

    public function title(): string { return 'Clients'; }

    public function startCell(): string { return $this->brandingStartCell(); }

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
                $lastRow = $sheet->getHighestRow();

                // Bandeau brandé PANORA (rows 1-4) — extrait dans le trait ExcelBranding
                // pour cohérence avec les autres exports.
                $this->applyBrandingHeader($event, 'LISTE DES CLIENTS', array_filter([
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                    $this->clients->count() . ' client(s)',
                    !empty($this->filters['search'])      ? 'Recherche : "' . $this->filters['search'] . '"' : null,
                    !empty($this->filters['sector'])      ? 'Secteur : ' . $this->filters['sector'] : null,
                    !empty($this->filters['active_only']) ? 'Avec campagne active' : null,
                ]));

                // Spécificités data Clients : colonnes numériques centrées + mise en
                // évidence du statut « Compte client : Actif » (data row 6+).
                if ($lastRow >= 6) {
                    foreach (['H', 'I', 'J', 'K'] as $col) {
                        $sheet->getStyle("{$col}6:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                    for ($r = 6; $r <= $lastRow; $r++) {
                        $val = $sheet->getCell("K{$r}")->getValue();
                        if ($val === 'Actif') {
                            $sheet->getStyle("K{$r}")->getFont()->getColor()->setRGB('065F46');
                            $sheet->getStyle("K{$r}")->getFont()->setBold(true);
                        }
                    }
                }

                // Bordures + alternance + freeze + print landscape (factorisé)
                $this->applyTableFinishing($event);
            },
        ];
    }
}
