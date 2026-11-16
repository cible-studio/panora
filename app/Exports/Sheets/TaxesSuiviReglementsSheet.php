<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Le détail de chaque règlement de l'exercice — ce que la fiche papier ne
 * gardait pas : la date, la référence, le mode, et les annulations.
 */
class TaxesSuiviReglementsSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    private int $nb = 0;

    public function __construct(protected int $exercice, protected Collection $lignes) {}

    public function title(): string
    {
        return 'REGLEMENTS';
    }

    public function array(): array
    {
        $rows = [['Mairie', 'Exercice', 'Versé le', 'Montant', 'Objet', 'Mode', 'Référence', 'Commentaire', 'Annulé le', 'Motif d\'annulation']];
        foreach ($this->lignes->sortBy(fn ($l) => \App\Models\Commune::normalizeForMatch($l['commune']->name)) as $l) {
            foreach ($l['reglements'] as $r) {
                $rows[] = [
                    $l['commune']->name,
                    $r->exercice,
                    $r->paid_at?->format('d/m/Y'),
                    (int) $r->montant,
                    $r->objet_label,
                    $r->mode ? trim(preg_replace('/^\S+\s/u', '', $r->mode_label)) : null,
                    $r->reference,
                    $r->commentaire,
                    $r->annule_le?->format('d/m/Y'),
                    $r->motif_annulation,
                ];
            }
        }
        $this->nb = count($rows);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();
                $s->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3E3C0']],
                ]);
                if ($this->nb > 1) {
                    $s->getStyle('D2:D' . $this->nb)->getNumberFormat()->setFormatCode('#,##0');
                }
                $s->freezePane('A2');
            },
        ];
    }
}
