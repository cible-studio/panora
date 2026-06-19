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
 * Export Excel détaillé de l'historique des relances — 1 ligne par
 * relance avec TOUS les champs : motif/note, suite à donner, canal,
 * résultat, auteur, facture liée. Pensé pour analyse fine, audit ou
 * transmission au cabinet comptable / direction.
 *
 * Filtres respectés : ceux passés par FinanceDashboardController
 * via la closure applyRelanceFiltersFromRequest() — même périmètre
 * que la page Historique à l'écran.
 */
class RelancesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithCustomStartCell, WithTitle
{
    use Exportable, ExcelBranding;

    public function __construct(
        protected Collection $relances,
        protected ?string $filterRecapLine = null,
    ) {}

    public function title(): string { return 'Historique relances'; }

    public function startCell(): string { return $this->brandingStartCell(); }

    public function collection() { return $this->relances; }

    public function headings(): array
    {
        return [
            'DATE',
            'CLIENT',
            'TÉLÉPHONE',
            'FACTURE',
            'CANAL',
            'RÉSULTAT',
            'MOTIF / NOTE',
            'SUITE À DONNER',
            'AUTEUR',
        ];
    }

    public function map($r): array
    {
        $outcomeLabels = [
            'promesse_paiement' => 'Promesse de paiement',
            'paiement_recu'     => 'Paiement reçu',
            'a_relancer'        => 'À relancer',
            'sans_reponse'      => 'Sans réponse',
            'desaccord'         => 'Désaccord',
            'autre'             => 'Autre',
        ];
        return [
            $r->relance_date?->format('d/m/Y') ?? '—',
            $r->client?->name ?? '—',
            $r->client?->phone ?? '—',
            $r->invoice?->reference ?? '— (relance globale)',
            \App\Models\Relance::CANAUX[$r->canal] ?? $r->canal,
            $outcomeLabels[$r->outcome] ?? ($r->outcome ?: '—'),
            $r->note ?: '—',
            $r->suite_donnee ?: '—',
            $r->user?->name ?? '—',
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
                    $this->filterRecapLine,
                    $this->relances->count() . ' relance(s)',
                    'Généré le ' . now()->format('d/m/Y à H:i'),
                ]);
                $this->applyBrandingHeader($event, "HISTORIQUE DES RELANCES", $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}
