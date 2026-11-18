<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Baromètre clients (2026-11-18) : les clients sans destinataire, remis
 * aux commerciaux pour qu'ils trouvent l'interlocuteur. En jaune, les
 * colonnes à remplir. Lignes : BarometreService::aCompleter().
 * Même présentation que InterlocuteursAFournirExport.
 */
class BarometreACompleterExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /** @param list<array{commercial: string, client: string, probleme: string, connus: string, fiche: string}> $lignes */
    public function __construct(private array $lignes) {}

    public function title(): string
    {
        return 'Contacts à compléter';
    }

    public function headings(): array
    {
        return [
            'Commercial', 'Client', 'Ce qui manque', 'Déjà dans Panora',
            "Nom de l'interlocuteur", 'Fonction', 'E-mail', 'Téléphone', 'Fiche Panora',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $l) => [
            $l['commercial'], $l['client'], $l['probleme'], $l['connus'], '', '', '', '', $l['fiche'],
        ], $this->lignes);
    }

    public function columnWidths(): array
    {
        return ['A' => 24, 'B' => 26, 'C' => 40, 'D' => 42, 'E' => 28, 'F' => 22, 'G' => 32, 'H' => 18, 'I' => 40];
    }

    public function styles(Worksheet $feuille): array
    {
        $derniere = max(2, $feuille->getHighestRow());

        $feuille->freezePane('C2');
        $feuille->setAutoFilter("A1:I{$derniere}");
        $feuille->getStyle("A1:I{$derniere}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $feuille->getStyle("E1:H{$derniere}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFF4CC');

        return [1 => ['font' => ['bold' => true]]];
    }
}
