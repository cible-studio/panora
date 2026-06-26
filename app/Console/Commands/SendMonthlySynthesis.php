<?php

namespace App\Console\Commands;

use App\Services\AdminAlertNotifier;
use App\Services\DashboardKpiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Synthèse de fin de mois (direction + comptable) — 1er du mois à 06h00.
 * Couvre le mois N-1 complet.
 */
class SendMonthlySynthesis extends Command
{
    protected $signature = 'recap:monthly';
    protected $description = 'Synthèse mensuelle (CA, taxes, anomalies) pour direction + comptable';

    public function handle(): int
    {
        $startMonth = now()->subMonth()->startOfMonth();
        $endMonth   = now()->subMonth()->endOfMonth();
        $kpi = new DashboardKpiService($startMonth, $endMonth);

        $parc       = $kpi->parcOverview();
        $revenue    = $kpi->totalRevenue();
        $stats      = $kpi->campaignStats();
        $decapStats = $kpi->decapStats();

        $taxesDueMonth = DB::table('taxes')
            ->whereBetween('due_date', [$startMonth, $endMonth])
            ->sum('amount');
        $taxesPaidMonth = DB::table('taxes')
            ->whereBetween('paid_at', [$startMonth, $endMonth])
            ->sum('amount');

        $lines = [
            "📅 Période : " . $startMonth->translatedFormat('F Y'),
            '',
            '💰 ACTIVITÉ COMMERCIALE',
            "  • CA réalisé : " . number_format($revenue, 0, ',', ' ') . " FCFA",
            "  • Campagnes : {$stats['total']} (actives {$stats['active']} / terminées {$stats['done']} / annulées {$stats['cancelled']})",
            "  • Taux annulation : {$stats['cancel_rate']}%",
            '',
            '📊 PARC',
            "  • Total panneaux : {$parc['total']}",
            "  • Occupation actuelle : {$parc['occupation_rate']}% ({$parc['occupied']} occupés)",
            '',
            '⚙️ OPÉRATIONS',
            "  • Décapages effectués : {$decapStats['decapped']} / {$decapStats['total']}",
            "  • Décapages en retard : {$decapStats['overdue']}",
            '',
            '🏛️ FISCAL',
            "  • Taxes dues ce mois : " . number_format($taxesDueMonth, 0, ',', ' ') . " FCFA",
            "  • Taxes payées ce mois : " . number_format($taxesPaidMonth, 0, ',', ' ') . " FCFA",
        ];

        AdminAlertNotifier::notify(
            to: ['admin', 'comptable'],
            severity: 'info',
            title: 'Synthèse mensuelle — ' . $startMonth->translatedFormat('F Y'),
            summary: "Bilan complet du mois écoulé (CA, parc, opérations, fiscal).",
            lines: $lines,
            ctaLabel: 'Voir le rapport complet →',
            ctaUrl: url('/admin/rapports'),
            emoji: '📅',
            footer: 'Récap automatique le 1er de chaque mois 06h00',
            dedupKey: 'monthly-recap-' . $startMonth->format('Y-m'),
        );

        $this->info("Synthèse mensuelle envoyée pour " . $startMonth->translatedFormat('F Y') . '.');
        return self::SUCCESS;
    }
}
