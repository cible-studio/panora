<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelBranding;
use App\Services\DashboardKpiService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export Excel multi-feuille du dashboard analytique (COMMIT D).
 *
 * Génère un classeur avec une feuille par module :
 *   1. Synthèse exécutive (KPIs principaux)
 *   2. Performance panneaux (top + sous-performants)
 *   3. Clients (top CA + inactifs)
 *   4. Campagnes (motifs annulation)
 *   5. Communes (parc + CA)
 *   6. Décappages (statut par campagne)
 *   7. CA par mois (12 mois glissants)
 *   8. Prévisions (régression linéaire 3 mois)
 *
 * Toutes les feuilles utilisent le KpiService cacheable → l'export
 * est rapide même sur gros parc.
 */
class RapportDashboardExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param ?string $filterRecapLine Récap des filtres actifs sur 1 ligne
     *                                 (cf. RapportFilterContextService::buildOneLine).
     *                                 Injecté en metaLine n°2 du bandeau de chaque feuille.
     */
    public function __construct(
        protected DashboardKpiService $kpi,
        protected ?string $filterRecapLine = null,
    ) {}

    public function sheets(): array
    {
        return [
            new RapportSheetSynthese($this->kpi, $this->filterRecapLine),
            new RapportSheetPanneaux($this->kpi, $this->filterRecapLine),
            new RapportSheetClients($this->kpi, $this->filterRecapLine),
            new RapportSheetCampagnes($this->kpi, $this->filterRecapLine),
            new RapportSheetCommunes($this->kpi, $this->filterRecapLine),
            new RapportSheetDecappages($this->kpi, $this->filterRecapLine),
            new RapportSheetCA($this->kpi, $this->filterRecapLine),
            new RapportSheetForecast($this->kpi, $this->filterRecapLine),
        ];
    }
}

/** ───────── Helper de style commun aux feuilles ─────────
 * Chaque feuille hérite du bandeau brandé PANORA (logo CIBLE + titre
 * + période) via ExcelBranding : rows 1-4 bandeau, row 5 headings,
 * row 6+ data. Le titre du bandeau reprend title() de la feuille. */
abstract class RapportSheetBase implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    use ExcelBranding;

    public function __construct(
        protected DashboardKpiService $kpi,
        protected ?string $filterRecapLine = null,
    ) {}

    public function startCell(): string { return $this->brandingStartCell(); }

    public function styles(Worksheet $sheet)
    {
        return [
            // Row 5 = headings (bandeau brandé occupe les rows 1-4)
            5 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0A0C10']],
                'alignment' => ['horizontal' => 'center'],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Note : si $filterRecapLine est fourni, il contient déjà la
                // période (cf. RapportFilterContextService::buildOneLine) —
                // on saute donc la ligne période séparée pour ne pas la dupliquer.
                if ($this->filterRecapLine) {
                    $metaLines = [
                        'Généré le ' . now()->format('d/m/Y à H:i'),
                        $this->filterRecapLine,
                    ];
                } else {
                    $period = $this->kpi->getPeriod();
                    $metaLines = array_filter([
                        'Généré le ' . now()->format('d/m/Y à H:i'),
                        isset($period['from'], $period['to'])
                            ? 'Période : ' . $period['from']->format('d/m/Y') . ' → ' . $period['to']->format('d/m/Y')
                            : null,
                    ]);
                }
                $this->applyBrandingHeader($event, 'RAPPORT · ' . mb_strtoupper($this->title()), $metaLines);
                $this->applyTableFinishing($event);
            },
        ];
    }
}

/** Feuille 1 — Synthèse exécutive */
class RapportSheetSynthese extends RapportSheetBase
{
    public function title(): string { return 'Synthèse'; }
    public function headings(): array { return ['Indicateur', 'Valeur', 'Détail']; }
    public function collection()
    {
        $parc       = $this->kpi->parcOverview();
        $stats      = $this->kpi->campaignStats();
        $revenue    = $this->kpi->totalRevenue();
        $inactivity = $this->kpi->inactivityBuckets();
        $decapStats = $this->kpi->decapStats();
        $period     = $this->kpi->getPeriod();

        return collect([
            ['Période', $period['from']->format('d/m/Y') . ' → ' . $period['to']->format('d/m/Y'), ''],
            ['', '', ''],
            ['Parc total', $parc['total'], 'panneaux installés'],
            ['Parc occupé', $parc['occupied'], $parc['occupation_rate'] . '% du parc'],
            ['Parc disponible', $parc['available'], ''],
            ['Parc en maintenance', $parc['maintenance'], ''],
            ['', '', ''],
            ['Campagnes', $stats['total'], 'sur la période'],
            ['  → Actives', $stats['active'], ''],
            ['  → Terminées', $stats['done'], ''],
            ['  → Annulées', $stats['cancelled'], $stats['cancel_rate'] . '% taux d\'annulation'],
            ['', '', ''],
            ['CA total', number_format($revenue, 0, ',', ' ') . ' FCFA', 'réservations confirmées + terminées'],
            ['', '', ''],
            ['Clients inactifs 3-6 mois', $inactivity['3_to_6'], ''],
            ['Clients inactifs 6-12 mois', $inactivity['6_to_12'], 'risque churn modéré'],
            ['Clients inactifs > 12 mois', $inactivity['12_plus'], 'risque churn élevé'],
            ['', '', ''],
            ['Panneaux à décaper', $decapStats['total'], '90 derniers jours'],
            ['  → Décappés', $decapStats['decapped'], $decapStats['rate'] . '% complétés'],
            ['  → En attente', $decapStats['pending'], ''],
            ['  → En retard (>7j)', $decapStats['overdue'], ''],
        ]);
    }
}

/** Feuille 2 — Panneaux */
class RapportSheetPanneaux extends RapportSheetBase
{
    public function title(): string { return 'Panneaux'; }
    public function headings(): array
    {
        return ['Section', 'Référence', 'Nom', 'Commune', 'Jours occupés', 'Campagnes', 'CA estimé (FCFA)'];
    }
    public function collection()
    {
        $top = $this->kpi->topPanels(50)->map(fn($p) => [
            'TOP', $p->reference, $p->name, $p->commune_name,
            $p->days_occupied, $p->campaigns_count, round($p->estimated_revenue ?? 0),
        ]);
        $low = $this->kpi->lowPanels(50)->map(fn($p) => [
            'SOUS-PERFORMANT', $p->reference, $p->name, $p->commune_name,
            '', $p->campaigns_count, $p->monthly_rate,
        ]);
        return $top->concat($low);
    }
}

/** Feuille 3 — Clients */
class RapportSheetClients extends RapportSheetBase
{
    public function title(): string { return 'Clients'; }
    public function headings(): array
    {
        return ['Section', 'Nom', 'Email', 'Campagnes', 'CA total (FCFA)', 'Dernière campagne'];
    }
    public function collection()
    {
        $top = $this->kpi->topClients(50)->map(fn($c) => [
            'TOP CA', $c->name, $c->email, $c->campaigns_count,
            round($c->total_revenue), $c->last_campaign_at,
        ]);
        $inactive = $this->kpi->inactiveClients(6, 200)->map(fn($c) => [
            'INACTIF 6m+', $c->name, $c->email ?? '', '', '', $c->last_campaign_at,
        ]);
        return $top->concat($inactive);
    }
}

/** Feuille 4 — Campagnes (motifs annulation) */
class RapportSheetCampagnes extends RapportSheetBase
{
    public function title(): string { return 'Campagnes'; }
    public function headings(): array
    {
        return ['Motif annulation', 'Nombre', '% du total'];
    }
    public function collection()
    {
        $reasons = $this->kpi->cancelReasons();
        $total   = $reasons->sum('count');
        return $reasons->map(fn($r) => [
            $r->cancellation_reason,
            $r->count,
            $total > 0 ? round(($r->count / $total) * 100, 1) . '%' : '0%',
        ]);
    }
}

/** Feuille 5 — Communes */
class RapportSheetCommunes extends RapportSheetBase
{
    public function title(): string { return 'Communes'; }
    public function headings(): array
    {
        return ['Commune', 'Ville', 'Parc total', 'Occupés', 'Libres', 'Taux %', 'CA (FCFA)', 'Campagnes'];
    }
    public function collection()
    {
        $parc    = $this->kpi->parcByCommune()->keyBy('id');
        $revenue = $this->kpi->revenueByCommune(999)->keyBy('id');
        return $parc->map(fn($p) => [
            $p['commune'], $p['city'], $p['total'], $p['occupied'], $p['free'], $p['rate'],
            round((float) ($revenue->get($p['id'])->revenue ?? 0)),
            (int) ($revenue->get($p['id'])->campaigns_count ?? 0),
        ])->values();
    }
}

/** Feuille 6 — Décappages */
class RapportSheetDecappages extends RapportSheetBase
{
    public function title(): string { return 'Décappages'; }
    public function headings(): array
    {
        return ['Campagne', 'Client', 'Fin', 'Panneaux', 'Décappés', 'En attente', 'Progression %', 'Statut'];
    }
    public function collection()
    {
        return $this->kpi->decapList(200)->map(fn($c) => [
            $c->name,
            $c->client?->name ?? '—',
            $c->end_date?->format('d/m/Y'),
            $c->total_panels,
            $c->decapped_count,
            $c->pending_count,
            $c->decap_progress,
            $c->is_overdue ? 'EN RETARD' : ($c->decapped_count === $c->total_panels ? 'COMPLET' : 'EN COURS'),
        ]);
    }
}

/** Feuille 7 — CA mensuel 12 mois */
class RapportSheetCA extends RapportSheetBase
{
    public function title(): string { return 'CA mensuel'; }
    public function headings(): array
    {
        return ['Mois', 'CA (FCFA)', "Taux d'occupation %"];
    }
    public function collection()
    {
        $revenue   = $this->kpi->revenueByMonth(12)->keyBy('label');
        $occupation = $this->kpi->occupationTrend(12)->keyBy('label');
        return $revenue->map(fn($r, $k) => [
            $r['label'] ?? $k,
            round((float) $r['total']),
            (float) ($occupation->get($k)['rate'] ?? 0),
        ])->values();
    }
}

/** Feuille 8 — Prévisions (régression linéaire) */
class RapportSheetForecast extends RapportSheetBase
{
    public function title(): string { return 'Prévisions'; }
    public function headings(): array
    {
        return ['Période', 'Indicateur', 'Valeur prévue', 'Tendance', 'Confiance'];
    }
    public function collection()
    {
        $forecaster = new \App\Services\KpiForecastService($this->kpi);
        $rev = $forecaster->revenueForecast(3);
        $occ = $forecaster->occupationForecast(3);

        $rows = collect();
        foreach ($rev['forecast'] as $f) {
            $rows->push([$f['label'], 'CA', round($f['value']), $f['trend'], $rev['confidence'] . '%']);
        }
        foreach ($occ['forecast'] as $f) {
            $rows->push([$f['label'], "Taux d'occupation", round($f['value'], 1) . '%', $f['trend'], $occ['confidence'] . '%']);
        }
        return $rows;
    }
}
