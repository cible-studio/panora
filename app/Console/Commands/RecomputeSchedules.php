<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\ScheduleAllocationService;
use Illuminate\Console\Command;

/**
 * php artisan schedules:recompute-all [--dry-run] [--invoice=]
 *
 * Recalcule rétroactivement les statuts d'échéances de toutes les
 * factures (ou d'une seule via --invoice=ID). Mission BUG_ECHEANCIER.
 *
 *   --dry-run   N'écrit RIEN en base. Affiche le diff before/after pour
 *               chaque facture où au moins un schedule change. Utiliser
 *               obligatoirement avant la passe live.
 *   --invoice=  Cible une seule facture (test).
 *
 * Stratégie : on délègue chaque facture au ScheduleAllocationService.
 * En mode --dry-run, on ouvre une transaction qu'on rollback à la fin
 * pour pouvoir inspecter le diff sans persister.
 */
class RecomputeSchedules extends Command
{
    protected $signature = 'schedules:recompute-all
                            {--dry-run : Affiche les changements sans les appliquer}
                            {--invoice= : Cible une facture précise (id)}';

    protected $description = 'Recalcule from-scratch les statuts d\'échéance de toutes les factures (mission BUG_ECHEANCIER).';

    public function handle(ScheduleAllocationService $service): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $invoiceId = $this->option('invoice');

        $this->info($dryRun ? '🧪 Mode DRY-RUN — aucune écriture en base.' : '⚠ Mode LIVE — les changements seront persistés.');

        $query = Invoice::query()
            ->whereHas('schedules')
            ->with(['schedules', 'payments']);

        if ($invoiceId) {
            $query->where('id', (int) $invoiceId);
        }

        $invoices = $query->orderBy('id')->get();
        $this->line("  Factures à analyser : {$invoices->count()}");
        $this->newLine();

        $totals = ['factures_changed' => 0, 'schedules_changed' => 0];

        foreach ($invoices as $invoice) {
            if ($dryRun) {
                \DB::beginTransaction();
                $result = $service->recomputeFromPayments($invoice);
                \DB::rollBack();
            } else {
                $result = $service->recomputeFromPayments($invoice);
            }

            if ($result['schedules_changed'] === 0) {
                continue;
            }

            $totals['factures_changed']++;
            $totals['schedules_changed'] += $result['schedules_changed'];

            $this->line("  📄 {$invoice->reference} (id={$invoice->id}) — {$result['schedules_changed']} échéance(s) modifiée(s) :");
            foreach ($result['diffs'] as $d) {
                if (!$d['changed']) continue;
                $beforeAmt = number_format($d['before']['paid_amount'], 0, ',', ' ');
                $afterAmt  = number_format($d['after']['paid_amount'], 0, ',', ' ');
                $beforeAt  = $d['before']['paid_at']  ?? '—';
                $afterAt   = $d['after']['paid_at']   ?? '—';
                $beforeSt  = $d['before']['state'];
                $afterSt   = $d['after']['state'];
                $this->line(
                    "      • {$d['label']} ({$d['amount']} F)"
                    . "  paid_amount: {$beforeAmt} → {$afterAmt}"
                    . "  · paid_at: {$beforeAt} → {$afterAt}"
                    . "  · state: {$beforeSt} → {$afterSt}"
                );
            }
        }

        $this->newLine();
        $this->info("✅ Bilan :");
        $this->line("  Factures modifiées      : {$totals['factures_changed']}");
        $this->line("  Échéances mises à jour  : {$totals['schedules_changed']}");

        if ($dryRun) {
            $this->newLine();
            $this->comment('  Pour appliquer ces changements :');
            $this->comment('    php artisan schedules:recompute-all');
        }

        return self::SUCCESS;
    }
}
