<?php

namespace App\Console\Commands;

use App\Services\AdminAlertNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Alerte par email les comptables / admins quand des taxes communales
 * arrivent à échéance dans N jours sans avoir été payées.
 *
 * Schedule recommandé : quotidien à 08h45.
 */
class NotifyTaxDueSoon extends Command
{
    protected $signature = 'taxes:notify-due-soon {--days=15 : Jours avant échéance}';
    protected $description = 'Alerte par email les taxes communales arrivant à échéance dans N jours';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deadline = now()->addDays($days)->endOfDay();

        $rows = DB::table('taxes as t')
            ->leftJoin('communes as c', 'c.id', '=', 't.commune_id')
            ->whereNull('t.paid_at')
            ->whereNotNull('t.due_date')
            ->whereBetween('t.due_date', [now()->startOfDay(), $deadline])
            ->select('t.id', 't.type', 't.amount', 't.due_date', 'c.name as commune')
            ->orderBy('t.due_date')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("Aucune taxe à échéance dans les {$days} jours.");
            return self::SUCCESS;
        }

        $total = $rows->sum('amount');
        $lines = [];
        foreach ($rows->take(15) as $r) {
            $when = \Carbon\Carbon::parse($r->due_date);
            $dLeft = (int) now()->diffInDays($when, false);
            $lines[] = ($r->commune ?? '—') . " — " . strtoupper($r->type) . " " . number_format($r->amount, 0, ',', ' ') . " FCFA — échéance " . $when->format('d/m/Y') . " (J-{$dLeft})";
        }

        AdminAlertNotifier::notify(
            to: ['comptable', 'admin'],
            severity: 'warning',
            title: $rows->count() . " taxe(s) à payer dans " . $days . " jours",
            summary: "Montant total : " . number_format($total, 0, ',', ' ') . " FCFA. Anticipez les règlements pour éviter pénalités.",
            lines: $lines,
            ctaLabel: 'Voir le rapport taxes →',
            ctaUrl: url('/admin/rapports/taxes'),
            emoji: '🏛️',
            footer: 'Détection automatique quotidienne',
            dedupKey: 'tax-due-' . now()->format('Y-m-d'),
        );

        $this->info("Alerte envoyée pour {$rows->count()} taxe(s) à échéance.");
        return self::SUCCESS;
    }
}
