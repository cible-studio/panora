<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\InvoiceController;
use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use App\Services\NotificationMailer;
use App\Services\PublicLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Envoie des relances automatiques pour les factures impayées.
 *
 * Cadence (depuis issued_at) :
 *   - J+7  → relance 1 (courtois)
 *   - J+14 → relance 2 (ferme)
 *   - J+30 → relance 3 (mise en demeure light)
 *
 * Idempotence : le numéro de relance est stocké en metadata du PublicLink
 * pour éviter les doublons.
 *
 * À planifier dans App\Console\Kernel::schedule() :
 *   $schedule->command('invoices:send-reminders')->dailyAt('09:00');
 */
class SendUnpaidInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders
                            {--dry-run : Affiche sans envoyer}
                            {--invoice= : Cible une facture spécifique (test)}';

    protected $description = 'Envoie des relances email pour les factures impayées (J+7, J+14, J+30)';

    public function handle(): int
    {
        $thresholds = [
            ['reminder_number' => 1, 'min_days' => 7,  'max_days' => 13],
            ['reminder_number' => 2, 'min_days' => 14, 'max_days' => 29],
            ['reminder_number' => 3, 'min_days' => 30, 'max_days' => 999],
        ];

        $invoiceId = $this->option('invoice');
        $dryRun    = (bool) $this->option('dry-run');

        $base = Invoice::where('status', 'envoyee')
            ->whereNull('paid_at')
            ->with('client');

        if ($invoiceId) {
            $base->where('id', $invoiceId);
        }

        $sent    = 0;
        $skipped = 0;

        foreach ($base->cursor() as $invoice) {
            if (!$invoice->client?->email) {
                $skipped++;
                continue;
            }

            $issued = $invoice->issued_at ?? $invoice->created_at;
            $daysOverdue = (int) $issued->diffInDays(now());

            // Détermine le numéro de relance approprié
            $reminderNum = null;
            foreach ($thresholds as $t) {
                if ($daysOverdue >= $t['min_days'] && $daysOverdue <= $t['max_days']) {
                    $reminderNum = $t['reminder_number'];
                    break;
                }
            }
            if (!$reminderNum) { $skipped++; continue; }

            // Idempotence : vérifie qu'on n'a pas déjà envoyé cette relance
            $alreadySent = \App\Models\PublicLink::where('type', 'invoice')
                ->where('target_type', Invoice::class)
                ->where('target_id', $invoice->id)
                ->where('metadata->reminder_number', $reminderNum)
                ->exists();
            if ($alreadySent) {
                $this->line("  ↪ #{$invoice->id} {$invoice->reference} — relance {$reminderNum} déjà envoyée");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("[DRY] #{$invoice->id} {$invoice->reference} → relance {$reminderNum} ({$daysOverdue}j retard) → {$invoice->client->email}");
                continue;
            }

            try {
                $link = PublicLinkService::create(
                    target:    $invoice,
                    type:      'invoice',
                    expiresAt: now()->addDays(30),
                    clientId:  $invoice->client_id,
                    metadata:  ['reminder_number' => $reminderNum, 'days_overdue' => $daysOverdue],
                );

                app(NotificationMailer::class)->sendSilently(
                    $invoice->client->email,
                    new InvoiceIssuedMail($invoice, $link, isReminder: true, reminderNumber: $reminderNum),
                    cc: null,
                    context: ['invoice_id' => $invoice->id, 'reminder' => $reminderNum],
                );
                $sent++;
                $this->info("✓ #{$invoice->id} {$invoice->reference} → relance {$reminderNum} envoyée à {$invoice->client->email}");
            } catch (\Throwable $e) {
                Log::error('invoice.reminder.failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->error("✗ #{$invoice->id} {$invoice->reference} : {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->line('');
        $this->info("Bilan : {$sent} relance(s) envoyée(s) · {$skipped} ignorée(s)");
        return self::SUCCESS;
    }
}
