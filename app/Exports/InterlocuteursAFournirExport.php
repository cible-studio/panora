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
 * Fichier remis à un commercial (2026-11-09) : ses campagnes sans
 * interlocuteur, et en jaune les colonnes qu'il doit remplir.
 * Lignes : CampagnesSansInterlocuteur::parCommercial().
 */
class InterlocuteursAFournirExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /** @param iterable<array<string, string>> $lignes */
    public function __construct(private iterable $lignes) {}

    public function title(): string
    {
        return 'À renseigner';
    }

    public function headings(): array
    {
        return [
            'Échéance', 'Client', 'Campagne', 'Statut', 'Reconduction', 'Contacts déjà dans Panora',
            "Nom de l'interlocuteur", 'Fonction', 'Mail direct', 'Téléphone',
        ];
    }

    public function array(): array
    {
        return collect($this->lignes)
            ->map(fn (array $l) => [
                $l['echeance'], $l['client'], $l['campagne'], $l['statut'], $l['reconduction'], $l['contacts'],
                '', '', '', '',
            ])
            ->values()
            ->all();
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 26, 'C' => 34, 'D' => 11, 'E' => 13, 'F' => 44, 'G' => 28, 'H' => 24, 'I' => 32, 'J' => 20];
    }

    public function styles(Worksheet $feuille): array
    {
        $derniere = max(2, $feuille->getHighestRow());

        $feuille->freezePane('A2');
        $feuille->getStyle("A1:J{$derniere}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $feuille->getStyle("G1:J{$derniere}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFF4CC');

        return [1 => ['font' => ['bold' => true]]];
    }
}
