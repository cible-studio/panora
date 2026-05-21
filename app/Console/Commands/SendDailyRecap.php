<?php

namespace App\Console\Commands;

use App\Services\AdminAlertNotifier;
use App\Services\DashboardKpiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Récap quotidien à envoyer en fin de journée à l'équipe (admin + commerciaux).
 *
 * Contenu :
 *   - Campagnes créées aujourd'hui
 *   - Poses réalisées aujourd'hui
 *   - Piges uploadées aujourd'hui
 *   - Alertes critiques en cours
 *
 * Schedule : 18h00 quotidien.
 */
class SendDailyRecap extends Command
{
    protected $signature = 'recap:daily';
    protected $description = "Récapitulatif quotidien d'activité (admin + commerciaux)";

    public function handle(): int
    {
        $today = now()->startOfDay();

        $campaignsNew = DB::table('campaigns')
            ->whereBetween('created_at', [$today, now()])
            ->whereNull('deleted_at')->count();

        $posesDone = DB::table('pose_tasks')
            ->whereBetween('done_at', [$today, now()])
            ->where('status', 'realisee')->count();

        $pigesUploaded = DB::table('piges')
            ->whereBetween('created_at', [$today, now()])->count();

        $pigesPending = DB::table('piges')
            ->where('status', 'en_attente')->count();

        $decapOverdue = DB::table('campaign_panels as cp')
            ->join('campaigns as c', 'c.id', '=', 'cp.campaign_id')
            ->where('c.status', 'termine')
            ->where('c.end_date', '<=', now()->subDays(7))
            ->whereNull('cp.decapped_at')
            ->whereNull('c.deleted_at')
            ->count();

        $reservationsToday = DB::table('reservations')
            ->whereBetween('created_at', [$today, now()])
            ->whereNull('deleted_at')->count();

        $lines = [
            "🆕 {$campaignsNew} campagne(s) créée(s) aujourd'hui",
            "📋 {$reservationsToday} réservation(s) créée(s) aujourd'hui",
            "✅ {$posesDone} pose(s) réalisée(s) aujourd'hui",
            "📸 {$pigesUploaded} pige(s) uploadée(s) aujourd'hui",
            "⏳ {$pigesPending} pige(s) en attente de validation",
            "⚠️ {$decapOverdue} décappage(s) en retard (> 7j)",
        ];

        $hasIssue = $pigesPending > 5 || $decapOverdue > 0;

        AdminAlertNotifier::notify(
            to: ['admin', 'commercial', 'mediaplanner'],
            severity: $hasIssue ? 'warning' : 'info',
            title: 'Récap quotidien — ' . now()->translatedFormat('l d M Y'),
            summary: 'Synthèse de l\'activité Panora aujourd\'hui.',
            lines: $lines,
            ctaLabel: 'Ouvrir le tableau de bord →',
            ctaUrl: url('/admin/rapports'),
            emoji: '📊',
            footer: 'Récap automatique envoyé à 18h00',
            dedupKey: 'daily-recap-' . now()->format('Y-m-d'),
        );

        $this->info('Récap quotidien envoyé.');
        return self::SUCCESS;
    }
}
