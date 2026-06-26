<?php

namespace App\Console\Commands;

use App\Services\AdminAlertNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Détecte les campagnes terminées avec des panneaux toujours non décapés
 * depuis plus de 7 jours et alerte l'admin + l'équipe terrain par email.
 *
 * Idempotent via le dedupKey AdminAlertNotifier (30 min de cooldown).
 *
 * Schedule recommandé : quotidien à 08h30.
 */
class NotifyOverdueDecap extends Command
{
    protected $signature = 'decap:notify-overdue {--days=7 : Seuil de retard en jours}';
    protected $description = 'Alerte par email les décapages en retard > N jours';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        // Agrégation : campagnes terminées avec panneaux non décapés > $days
        $rows = DB::table('campaigns as c')
            ->join('campaign_panels as cp', 'cp.campaign_id', '=', 'c.id')
            ->join('panels as p', 'p.id', '=', 'cp.panel_id')
            ->leftJoin('communes as co', 'co.id', '=', 'p.commune_id')
            ->leftJoin('clients as cl', 'cl.id', '=', 'c.client_id')
            ->where('c.status', 'termine')
            ->where('c.end_date', '<=', $threshold)
            ->whereNull('cp.decapped_at')
            ->whereNull('c.deleted_at')
            ->select(
                'c.id as campaign_id', 'c.name as campaign_name', 'c.end_date',
                'cl.name as client_name',
                DB::raw('COUNT(*) as pending'),
                DB::raw("GROUP_CONCAT(DISTINCT p.reference SEPARATOR ', ') as panel_refs"),
                DB::raw("GROUP_CONCAT(DISTINCT co.name SEPARATOR ', ') as communes")
            )
            ->groupBy('c.id', 'c.name', 'c.end_date', 'cl.name')
            ->orderBy('c.end_date')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Aucun retard de décapage > '. $days .'j.');
            return self::SUCCESS;
        }

        // Alerte agrégée : un seul mail listant tous les retards
        $lines = [];
        $totalPending = 0;
        foreach ($rows as $r) {
            $daysOverdue = (int) \Carbon\Carbon::parse($r->end_date)->diffInDays(now());
            $lines[] = "« {$r->campaign_name} » ({$r->client_name}) — {$r->pending} panneau(x) en retard de {$daysOverdue}j — " . \Illuminate\Support\Str::limit($r->panel_refs ?? '', 80);
            $totalPending += $r->pending;
        }

        AdminAlertNotifier::notify(
            to: ['admin', 'mediaplanner'],
            severity: 'danger',
            title: "{$totalPending} panneau(x) en retard de décapage (> {$days}j)",
            summary: "Risque amende municipale + plainte client. Planifiez les tournées de décapage en urgence.",
            lines: array_slice($lines, 0, 12),
            ctaLabel: 'Ouvrir le rapport décapages →',
            ctaUrl: url('/admin/rapports#tab-decap'),
            emoji: '⚠️',
            footer: 'Détection automatique quotidienne',
            dedupKey: 'overdue-decap-' . now()->format('Y-m-d'),
        );

        $this->info("Alerte envoyée pour {$totalPending} panneau(x) en retard.");
        return self::SUCCESS;
    }
}
