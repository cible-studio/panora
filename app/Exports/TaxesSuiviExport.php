<?php

namespace App\Exports;

use App\Exports\Sheets\TaxesSuiviReglementsSheet;
use App\Exports\Sheets\TaxesSuiviZoneSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Suivi des mairies (2026-11-16) — export au format de la fiche de la
 * direction « GESTION ODP-TM » : une feuille ABIDJAN, une feuille
 * INTERIEUR, mêmes colonnes (Mairies · Dim · Nbr Pa · Nbr face · Arriérés ·
 * ODP · TM par trimestre · Total TM · Total général · Règlements · Total
 * paiement · Solde), une ligne par dimension sous chaque mairie. Une
 * troisième feuille détaille chaque règlement (date, référence, mode).
 *
 * Reçoit les lignes déjà calculées par TaxeExerciceService.
 */
class TaxesSuiviExport implements WithMultipleSheets
{
    use Exportable;

    /** @param array{abidjan: Collection, interieur: Collection} $zones */
    public function __construct(
        protected int $exercice,
        protected array $zones,
        protected bool $reprise,
        // 2026-11-18 — Sélection de mairies : libellé « N mairies sur M », ou null (tout l'exercice).
        protected ?string $selection = null,
    ) {}

    public function sheets(): array
    {
        $feuilles = [];
        foreach (['abidjan' => ['ABIDJAN', 'ABIDJAN ET BANLIEUE'], 'interieur' => ['INTERIEUR', 'INTÉRIEUR']] as $z => [$titre, $libelle]) {
            // Avec une sélection, une zone sans mairie choisie n'a pas de feuille.
            if ($this->selection && $this->zones[$z]->isEmpty()) {
                continue;
            }
            $feuilles[] = new TaxesSuiviZoneSheet($this->exercice, $titre, $libelle, $this->zones[$z], $this->reprise, $this->selection);
        }
        $feuilles[] = new TaxesSuiviReglementsSheet($this->exercice, $this->zones['abidjan']->concat($this->zones['interieur']));

        return $feuilles;
    }
}
