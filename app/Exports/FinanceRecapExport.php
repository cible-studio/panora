<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Récap Finance — Excel multi-feuilles pour direction / comptabilité.
 *
 * Centralise les 4 dimensions clés du dashboard Finance dans un seul
 * fichier exploitable : encaissements, créances, recouvrement, top clients.
 *
 * Source : FinancialDashboardService (cohérent au franc près avec la
 * page Finance affichée à l'écran — pas de calcul dupliqué).
 *
 * Filtres respectés : période [from, to] + scope commercial (RBAC).
 */
class FinanceRecapExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $kpis,
        protected Collection $recentPayments,
        protected Collection $creances,
        protected Collection $clientsToFollow,
        protected Collection $topClients,
        protected Carbon $from,
        protected Carbon $to,
        protected ?string $userName = null,
    ) {}

    public function sheets(): array
    {
        return [
            new FinanceRecapSyntheseSheet($this->kpis, $this->from, $this->to, $this->userName),
            new FinanceRecapVersementsSheet($this->recentPayments, $this->from, $this->to),
            new FinanceRecapCreancesSheet($this->creances, $this->from, $this->to),
            new FinanceRecapRecouvrementSheet($this->clientsToFollow, $this->from, $this->to),
            new FinanceRecapTopClientsSheet($this->topClients, $this->from, $this->to),
        ];
    }
}

// ════════════════════════════════════════════════════════════════════
// Helper de base — bandeau brandé Panora sur chaque feuille
// ════════════════════════════════════════════════════════════════════
abstract class FinanceRecapBaseSheet implements
    \Maatwebsite\Excel\Concerns\FromCollection,
    \Maatwebsite\Excel\Concerns\WithHeadings,
    \Maatwebsite\Excel\Concerns\WithStyles,
    \Maatwebsite\Excel\Concerns\WithTitle,
    \Maatwebsite\Excel\Concerns\ShouldAutoSize,
    \Maatwebsite\Excel\Concerns\WithCustomStartCell,
    \Maatwebsite\Excel\Concerns\WithEvents
{
    use \App\Exports\Concerns\ExcelBranding;

    public function startCell(): string { return $this->brandingStartCell(); }

    public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $sheet->getStyle('A5:' . $sheet->getHighestColumn() . '5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0A0C10'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        return [];
    }
}

// ════════════════════════════════════════════════════════════════════
// Feuille 1 — Synthèse KPI
// ════════════════════════════════════════════════════════════════════
class FinanceRecapSyntheseSheet extends FinanceRecapBaseSheet
{
    public function __construct(
        protected array $kpis,
        protected Carbon $from,
        protected Carbon $to,
        protected ?string $userName,
    ) {}

    public function title(): string { return 'Synthèse'; }

    public function headings(): array { return ['INDICATEUR', 'VALEUR', 'NOTE']; }

    public function collection()
    {
        $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
        return collect([
            ['💰 Encaissé période (TTC)',  $fmt($this->kpis['encaisse_total'] ?? 0) . ' FCFA', 'Versements reçus sur la période'],
            ['📤 Facturé période (HT)',    $fmt($this->kpis['facture_total']  ?? 0) . ' FCFA', 'Factures émises (hors annulées)'],
            ['📉 Total dû (créances)',     $fmt($this->kpis['total_du']       ?? 0) . ' FCFA', 'Cumul des restes à payer'],
            ['⚠ En retard',                $fmt($this->kpis['retard_total']   ?? 0) . ' FCFA', 'Créances avec échéance dépassée'],
            ['🔁 Taux de recouvrement',    number_format($this->kpis['taux_recouvrement'] ?? 0, 1, ',', ' ') . ' %', 'Encaissé / facturé TTC'],
            ['👥 Clients à relancer',      (int) ($this->kpis['clients_a_relancer'] ?? 0), 'Avec créance ouverte'],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $info = [
                    'Période : ' . $this->from->format('d/m/Y') . ' → ' . $this->to->format('d/m/Y'),
                    'Édité le ' . now()->format('d/m/Y à H:i'),
                    $this->userName ? 'Par ' . $this->userName : null,
                ];
                $this->applyBrandingHeader($event, 'SYNTHÈSE FINANCIÈRE', array_filter($info));
                $this->applyTableFinishing($event);
            },
        ];
    }
}

// ════════════════════════════════════════════════════════════════════
// Feuille 2 — Versements détaillés
// ════════════════════════════════════════════════════════════════════
class FinanceRecapVersementsSheet extends FinanceRecapBaseSheet
{
    public function __construct(
        protected Collection $versements,
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string { return 'Versements'; }

    public function headings(): array
    {
        return ['DATE', 'FACTURE', 'ACOMPTE ?', 'CLIENT', 'MODE', 'RÉFÉRENCE', 'BANQUE', 'MONTANT (FCFA)', 'ENREGISTRÉ PAR'];
    }

    public function collection()
    {
        return $this->versements->map(fn ($p) => [
            $p['paid_at']?->format('d/m/Y') ?? '—',
            $p['invoice_ref'] ?? '—',
            !empty($p['is_acompte']) ? 'OUI' : '',
            $p['client_name'] ?? '—',
            $p['mode_label'] ?? $p['mode'] ?? '—',
            $p['reference'] ?: '—',
            $p['bank'] ?: '—',
            (int) ($p['montant'] ?? 0),
            $p['creator_name'] ?? '—',
        ]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $total = $this->versements->sum('montant');
                $info = [
                    'Période : ' . $this->from->format('d/m/Y') . ' → ' . $this->to->format('d/m/Y'),
                    $this->versements->count() . ' versement(s)',
                    'Total : ' . number_format($total, 0, ',', ' ') . ' FCFA',
                ];
                $this->applyBrandingHeader($event, 'DÉTAIL DES VERSEMENTS', $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}

// ════════════════════════════════════════════════════════════════════
// Feuille 3 — Créances ouvertes
// ════════════════════════════════════════════════════════════════════
class FinanceRecapCreancesSheet extends FinanceRecapBaseSheet
{
    public function __construct(
        protected Collection $creances,
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string { return 'Créances'; }

    public function headings(): array
    {
        return ['FACTURE', 'CLIENT', 'ÉMISE LE', 'ÉCHÉANCE', 'TOTAL FACTURE (FCFA)', 'RESTE À PAYER (FCFA)', 'STATUT'];
    }

    public function collection()
    {
        return $this->creances->map(function ($inv) {
            $statusLabels = [
                'paye'      => 'Soldée',
                'partiel'   => 'Paiement partiel',
                'en_attente'=> 'En attente',
                'en_retard' => 'En retard',
            ];
            return [
                $inv->reference ?? '—',
                $inv->client?->name ?? '—',
                $inv->issued_at?->format('d/m/Y') ?? '—',
                $inv->due_date?->format('d/m/Y') ?? '—',
                (int) ($inv->total_a_payer ?? 0),
                (int) round($inv->remainingAmount()),
                $statusLabels[$inv->paymentStatus()] ?? $inv->paymentStatus(),
            ];
        });
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $totalRest = $this->creances->sum(fn ($i) => $i->remainingAmount());
                $info = [
                    'Snapshot ' . now()->format('d/m/Y H:i'),
                    $this->creances->count() . ' facture(s) ouverte(s)',
                    'Reste à recouvrer : ' . number_format($totalRest, 0, ',', ' ') . ' FCFA',
                ];
                $this->applyBrandingHeader($event, 'CRÉANCES OUVERTES', $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}

// ════════════════════════════════════════════════════════════════════
// Feuille 4 — Recouvrement (clients à relancer)
// ════════════════════════════════════════════════════════════════════
class FinanceRecapRecouvrementSheet extends FinanceRecapBaseSheet
{
    public function __construct(
        protected Collection $clientsToFollow,
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string { return 'Recouvrement'; }

    public function headings(): array
    {
        return ['CLIENT', 'TÉLÉPHONE', 'DETTE ACTUELLE (FCFA)', 'NB FACTURES OUVERTES', 'DERNIÈRE RELANCE', 'RÉSULTAT DERNIÈRE RELANCE', 'AUTEUR'];
    }

    public function collection()
    {
        return $this->clientsToFollow->map(function ($row) {
            $outcomeLabels = [
                'promesse_paiement' => 'Promesse paiement',
                'paiement_recu'     => 'Paiement reçu',
                'a_relancer'        => 'À relancer',
                'sans_reponse'      => 'Sans réponse',
                'desaccord'         => 'Désaccord',
                'autre'             => 'Autre',
            ];
            $dr = $row['derniere_relance'] ?? null;
            $drDate = $dr instanceof \DateTimeInterface ? $dr->format('d/m/Y') : ($dr ? \Carbon\Carbon::parse($dr)->format('d/m/Y') : '— Jamais');
            return [
                $row['client_name'] ?? '—',
                $row['client_phone'] ?? '—',
                (int) ($row['total_du'] ?? 0),
                (int) ($row['factures_open'] ?? 0),
                $drDate,
                $outcomeLabels[$row['derniere_relance_outcome'] ?? ''] ?? '—',
                $row['derniere_relance_user'] ?? '—',
            ];
        });
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $totalDu = $this->clientsToFollow->sum('total_du');
                $info = [
                    'Snapshot ' . now()->format('d/m/Y H:i'),
                    $this->clientsToFollow->count() . ' client(s) à relancer',
                    'Total dû : ' . number_format($totalDu, 0, ',', ' ') . ' FCFA',
                ];
                $this->applyBrandingHeader($event, 'CLIENTS À RELANCER', $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}

// ════════════════════════════════════════════════════════════════════
// Feuille 5 — Top clients (encaissements)
// ════════════════════════════════════════════════════════════════════
class FinanceRecapTopClientsSheet extends FinanceRecapBaseSheet
{
    public function __construct(
        protected Collection $topClients,
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string { return 'Top clients'; }

    public function headings(): array
    {
        return ['RANG', 'CLIENT', 'NB VERSEMENTS', 'TOTAL ENCAISSÉ (FCFA)'];
    }

    public function collection()
    {
        return $this->topClients->values()->map(fn ($row, $i) => [
            $i + 1,
            $row['client_name'] ?? $row['name'] ?? '—',
            (int) ($row['nb_versements'] ?? $row['count'] ?? 0),
            (int) ($row['total_encaisse'] ?? $row['montant'] ?? 0),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $info = [
                    'Période : ' . $this->from->format('d/m/Y') . ' → ' . $this->to->format('d/m/Y'),
                    $this->topClients->count() . ' clients',
                ];
                $this->applyBrandingHeader($event, 'TOP CLIENTS ENCAISSEMENTS', $info);
                $this->applyTableFinishing($event);
            },
        ];
    }
}
