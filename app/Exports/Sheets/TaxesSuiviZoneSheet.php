<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Une feuille de la fiche « GESTION ODP-TM » (ABIDJAN ou INTERIEUR), même
 * présentation que le fichier de la direction : le nom de la mairie et ses
 * montants sur la première ligne, une ligne par dimension en dessous.
 */
class TaxesSuiviZoneSheet implements FromArray, WithTitle, WithEvents
{
    private const ENTETE_LIGNE = 4;

    private int $nbReglements;
    private int $derniereLigne = 0;
    /** @var array<int, int> lignes où commence une mairie */
    private array $debutsMairie = [];

    public function __construct(
        protected int $exercice,
        protected string $titre,
        protected string $zone,
        protected Collection $lignes,
        protected bool $reprise,
    ) {
        $max = (int) $lignes->max(fn ($l) => $l['nb_reglements']);
        $this->nbReglements = max(6, $max);
    }

    public function title(): string
    {
        return $this->titre;
    }

    public function array(): array
    {
        $regie = mb_strtoupper((string) config('charte.nom', 'CIBLE'));
        $rows = [
            ["GESTION DES TAXES (ODP ET TM) DE LA RÉGIE {$regie} : EXERCICE {$this->exercice}"],
            [$this->zone],
            [$this->reprise ? 'Exercice de reprise : montants réclamés par les mairies, saisis par la comptabilité.' : 'Calcul Panora : panneaux et tarifs de Panora, arriérés = solde ' . ($this->exercice - 1) . '.'],
            $this->entetes(),
        ];

        foreach ($this->lignes as $l) {
            $this->debutsMairie[] = count($rows) + 1;
            $base = array_values($l['base'] ?? []);
            if (empty($base)) {
                $base = [['surface' => null, 'panneaux' => null, 'faces' => null]];
            }

            $montants = $l['reglements']
                ->reject(fn ($r) => $r->estAnnule())
                ->pluck('montant')
                ->map(fn ($m) => (int) $m)
                ->values()
                ->all();
            $reglements = array_pad($montants, $this->nbReglements, null);

            foreach ($base as $i => $b) {
                $dim = [$b['surface'] !== null ? (float) $b['surface'] : null, $b['panneaux'], $b['faces']];
                if ($i === 0) {
                    $rows[] = array_merge(
                        [$l['commune']->name],
                        $dim,
                        [
                            $l['arrieres'] ?: null,
                            $l['odp'] ?: null,
                            $l['tm_trim'][1] ?: null, $l['tm_trim'][2] ?: null,
                            $l['tm_trim'][3] ?: null, $l['tm_trim'][4] ?: null,
                            $l['tm'],
                            $l['total_du'],
                        ],
                        $reglements,
                        [$l['total_paye'], $l['solde']]
                    );
                } else {
                    $rows[] = array_merge([null], $dim);
                }
            }
        }

        $t = fn (string $cle) => (int) $this->lignes->sum($cle);
        $tTrim = fn (int $q) => (int) $this->lignes->sum(fn ($l) => $l['tm_trim'][$q]);
        $rows[] = [];
        $rows[] = array_merge(
            ['TOTAL', null, (int) $this->lignes->sum('nb_panneaux'), (int) $this->lignes->sum('nb_faces'),
             $t('arrieres'), $t('odp'), $tTrim(1), $tTrim(2), $tTrim(3), $tTrim(4), $t('tm'), $t('total_du')],
            array_fill(0, $this->nbReglements, null),
            [$t('total_paye'), $t('solde')]
        );
        $this->derniereLigne = count($rows);

        return $rows;
    }

    private function entetes(): array
    {
        $regl = [];
        $ordinaux = ['1er règl.', '2ème', '3ème', '4ème', '5ème', '6ème'];
        for ($i = 0; $i < $this->nbReglements; $i++) {
            $regl[] = $ordinaux[$i] ?? ($i + 1) . 'ème';
        }

        return array_merge(
            ['MAIRIES', 'Dim (m²)', 'Nbr Pa', 'Nbr face', 'TAXES ARRIÉRÉES', 'ODP ' . $this->exercice,
             'TM 1er Trim', 'TM 2è Trim', 'TM 3è Trim', 'TM 4è Trim', 'TOTAL TM', 'TOTAL GÉNÉRAL'],
            $regl,
            ['TOTAL Paiement', 'SOLDE']
        );
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();
                $nbCol = 12 + $this->nbReglements + 2;
                $der = Coordinate::stringFromColumnIndex($nbCol);
                $h = self::ENTETE_LIGNE;

                $s->mergeCells("A1:{$der}1");
                $s->mergeCells("A2:{$der}2");
                $s->mergeCells("A3:{$der}3");
                $s->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $s->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $s->getStyle('A3')->getFont()->setItalic(true)->setSize(9);
                $s->getStyle("A1:A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $s->getStyle("A{$h}:{$der}{$h}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 9],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3E3C0']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $s->getRowDimension($h)->setRowHeight(30);

                if ($this->derniereLigne > $h) {
                    $plage = "A{$h}:{$der}{$this->derniereLigne}";
                    $s->getStyle($plage)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $s->getStyle("E" . ($h + 1) . ":{$der}{$this->derniereLigne}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("A{$this->derniereLigne}:{$der}{$this->derniereLigne}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDEDED']],
                    ]);
                    foreach ($this->debutsMairie as $ligne) {
                        $s->getStyle("A{$ligne}")->getFont()->setBold(true);
                    }
                }

                $s->getColumnDimension('A')->setWidth(22);
                for ($c = 2; $c <= $nbCol; $c++) {
                    $s->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($c <= 4 ? 8 : 13);
                }
                $s->freezePane('B' . ($h + 1));
            },
        ];
    }
}
