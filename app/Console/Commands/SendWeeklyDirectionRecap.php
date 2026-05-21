<?php

namespace App\Console\Commands;

use App\Services\AdminAlertNotifier;
use App\Services\DashboardKpiService;
use Illuminate\Console\Command;

/**
 * Récap hebdo direction (KPIs + alertes critiques) — chaque lundi 08h00.
 * Réutilise DashboardKpiService::executiveSummary() pour la cohérence.
 */
class SendWeeklyDirectionRecap extends Command
{
    protected $signature = 'recap:weekly-direction';
    protected $description = 'Récap hebdomadaire de direction (KPIs + risques + actions)';

    public function handle(): int
    {
        $kpi = new DashboardKpiService(now()->subDays(7)->startOfDay(), now()->endOfDay());
        $summary = $kpi->executiveSummary();

        $lines = [
            "🏆 Score performance : {$summary['score']}/10 ({$summary['score_label']})",
            "💰 CA réalisé (7j) : " . number_format($summary['kpis']['revenue'], 0, ',', ' ') . " FCFA",
            "📊 Taux occupation : {$summary['kpis']['occupation_rate']}%",
            "❌ Taux annulation : {$summary['kpis']['cancel_rate']}%",
            "📋 Campagnes (7j) : {$summary['kpis']['campaigns_total']}",
            "🔮 CA prévu 3 mois : " . number_format($summary['forecast_3m_revenue'], 0, ',', ' ') . " FCFA",
            '',
            '⚠️ RISQUES MAJEURS',
        ];

        foreach ($summary['risks']->take(3) as $r) {
            $lines[] = '  • ' . $r;
        }

        $lines[] = '';
        $lines[] = '🎯 ACTIONS PRIORITAIRES';
        foreach ($summary['actions']->take(3) as $a) {
            $lines[] = '  • [' . strtoupper($a['priority']) . '] ' . $a['action'];
        }

        AdminAlertNotifier::notify(
            to: ['admin'], // direction = admin par défaut, ajustez selon votre RACI
            severity: $summary['score'] >= 6 ? 'success' : 'warning',
            title: 'Récap direction — semaine ' . now()->subDays(7)->format('d/m') . ' → ' . now()->format('d/m/Y'),
            summary: 'Synthèse hebdomadaire des KPIs et alertes Panora.',
            lines: $lines,
            ctaLabel: 'Ouvrir la synthèse exécutive →',
            ctaUrl: url('/admin/rapports?tab=insights'),
            emoji: '📈',
            footer: 'Récap automatique envoyé chaque lundi 08h00',
            dedupKey: 'weekly-recap-' . now()->format('Y-W'),
        );

        $this->info('Récap hebdo direction envoyé.');
        return self::SUCCESS;
    }
}
