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

        // 2026-11-16 — Les taxes viennent du Suivi des mairies. Avant : la
        // table legacy `taxes` (due_date / paid_at), que plus aucun écran ne
        // remplit — les deux lignes affichaient 0.
        //   · dues du mois  : le calcul Panora du mois (moteur unique) ;
        //   · payées du mois : les règlements versés dans le mois ;
        //   · reste à payer : somme des soldes positifs de l'exercice.
        $taxesDueMonth = (int) round(app(\App\Services\TaxCalculationService::class)
            ->generateLines(\App\Services\TaxCalculationService::PERIOD_MONTHLY, (int) $startMonth->month, (int) $startMonth->year, ['inclure_lumipub' => \App\Services\Taxes\TaxeExerciceService::inclureLumipub()])
            ->sum('amount'));
        $taxesPaidMonth = (int) \App\Models\TaxeReglement::valides()
            ->whereBetween('paid_at', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->sum('montant');
        $suivi = app(\App\Services\Taxes\TaxeExerciceService::class);
        $exerciceMois = (int) $startMonth->year;
        $resteExercice = $suivi->estSuivi($exerciceMois)
            ? $suivi->totaux($suivi->exercices($exerciceMois))['reste']
            : null;

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
            "  • Taxes du mois (calcul Panora) : " . number_format($taxesDueMonth, 0, ',', ' ') . " FCFA",
            "  • Règlements versés aux mairies ce mois : " . number_format($taxesPaidMonth, 0, ',', ' ') . " FCFA",
        ];
        if ($resteExercice !== null) {
            $lines[] = "  • Reste à payer aux mairies (exercice {$exerciceMois}, arriérés compris) : "
                . number_format($resteExercice, 0, ',', ' ') . " FCFA";
        }

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
