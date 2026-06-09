<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceAlert;
use App\Models\InvoiceSchedule;
use App\Models\User;
use App\Notifications\InvoiceScheduleAlertNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Job planifié quotidien — Phase 4 du cahier des charges Recouvrement (§7).
 *
 * S'exécute via `php artisan invoices:alerts` (planifié dans
 * routes/console.php → daily at 07:00).
 *
 * Deux passes :
 *
 *   PASSE 1 — Matérialisation
 *   Pour chaque échéance active (paid_at NULL, invoice non annulée) :
 *     - Génère les InvoiceAlerts manquantes pour les 4+4 offsets de
 *       config('billing.alerts.offsets'), par canal de
 *       config('billing.alerts.channels'). Idempotent : la contrainte
 *       UNIQUE (schedule_id, kind, offset_days, channel) empêche les
 *       doublons sur re-run.
 *
 *   PASSE 2 — Envoi
 *   Pour chaque alerte pending dont due_date <= today :
 *     - Envoie via canal (in_app → admins/commercial via database,
 *       email → client via mail).
 *     - Marque sent_at + status='sent'. En cas d'exception, status='failed'
 *       avec error_message tronqué.
 *
 *   PASSE 3 — Transition auto en_retard
 *   Toute facture avec au moins une schedule.due_date < today &&
 *   paid_at NULL bascule en status='en_retard' (transition auto via
 *   Invoice::transitionTo qui alimente invoice_status_history).
 */
class InvoiceAlertsCommand extends Command
{
    protected $signature = 'invoices:alerts
                            {--dry-run : Affiche ce qui SERAIT fait sans rien envoyer}
                            {--invoice= : Restreint à une facture précise (id ou référence)}';

    protected $description = 'Génère et envoie les alertes d\'échéance facture (J-15…J+30) — cahier §7.';

    public function handle(): int
    {
        $this->info('🔔 Phase 4 — Alertes échéances factures');
        $dry = (bool) $this->option('dry-run');

        $offsets = config('billing.alerts.offsets');
        $channels = collect(config('billing.alerts.channels'))
            ->filter()->keys()->all();

        if (empty($channels)) {
            $this->warn('Aucun canal d\'alerte activé (config billing.alerts.channels). Rien à faire.');
            return self::SUCCESS;
        }

        $this->line('Canaux actifs : ' . implode(', ', $channels));
        $this->line('Offsets before : ' . implode(', ', $offsets['before']));
        $this->line('Offsets after  : ' . implode(', ', $offsets['after']));

        // ───────────── Passe 1 : matérialisation ─────────────
        $stats = ['created' => 0, 'sent' => 0, 'failed' => 0, 'overdue_invoices' => 0];

        $invoiceFilter = $this->option('invoice');
        $invoiceQuery = Invoice::query()
            ->whereNotIn('status', ['annulee', 'brouillon', 'generee', 'validee'])
            ->whereHas('schedules', fn($q) => $q->whereNull('paid_at'));
        if ($invoiceFilter) {
            if (is_numeric($invoiceFilter)) {
                $invoiceQuery->where('id', (int) $invoiceFilter);
            } else {
                $invoiceQuery->where('reference', $invoiceFilter);
            }
        }
        $invoices = $invoiceQuery->with('schedules', 'client', 'commercialUser')->get();

        foreach ($invoices as $invoice) {
            foreach ($invoice->schedules->whereNull('paid_at') as $schedule) {
                foreach ($offsets['before'] as $d) {
                    $stats['created'] += $this->ensureAlert($schedule, $invoice, 'before', -$d, $channels, $dry);
                }
                foreach ($offsets['after'] as $d) {
                    $stats['created'] += $this->ensureAlert($schedule, $invoice, 'after', $d, $channels, $dry);
                }
            }
        }
        $this->info("✓ Matérialisé {$stats['created']} alerte(s).");

        // ───────────── Passe 2 : envoi des pending dues ─────────────
        $pending = InvoiceAlert::where('status', 'pending')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->with('invoice.client', 'invoice.commercialUser', 'schedule')
            ->get();

        foreach ($pending as $alert) {
            try {
                if ($dry) {
                    $this->line("[dry] envoi alerte #{$alert->id} ({$alert->channel}) — {$alert->humanLabel()}");
                } else {
                    $this->dispatchAlert($alert);
                    $alert->update(['status' => 'sent', 'sent_at' => now()]);
                }
                $stats['sent']++;
            } catch (\Throwable $e) {
                $alert->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 500),
                ]);
                $stats['failed']++;
                Log::error('invoice.alert.failed', [
                    'alert_id' => $alert->id,
                    'invoice_id' => $alert->invoice_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $this->info("✓ Envoyé {$stats['sent']} alerte(s) — {$stats['failed']} échec(s).");

        // ───────────── Passe 3 : bascule auto en_retard ─────────────
        foreach ($invoices as $invoice) {
            if (in_array($invoice->status, ['annulee', 'brouillon', 'generee', 'validee', 'payee', 'litige', 'en_retard'], true)) {
                continue;
            }
            $hasOverdue = $invoice->schedules
                ->whereNull('paid_at')
                ->contains(fn($s) => $s->due_date && $s->due_date->lt(now()->startOfDay()));
            if ($hasOverdue && $invoice->remainingAmount() > 0) {
                if ($dry) {
                    $this->line("[dry] facture #{$invoice->id} {$invoice->reference} → en_retard");
                } else {
                    $invoice->transitionStatusTo(
                        'en_retard',
                        reason: 'Auto (Job alertes) : échéance dépassée + reste à payer',
                        auto:   true
                    );
                }
                $stats['overdue_invoices']++;
            }
        }
        $this->info("✓ Facture(s) basculée(s) en_retard : {$stats['overdue_invoices']}.");

        return self::SUCCESS;
    }

    /** Génère les alertes manquantes pour les canaux donnés. Retourne le nombre créé. */
    protected function ensureAlert(InvoiceSchedule $schedule, Invoice $invoice, string $kind, int $offsetDays, array $channels, bool $dry): int
    {
        $created = 0;
        $effectiveDate = $schedule->due_date->copy()->addDays($offsetDays);

        foreach ($channels as $channel) {
            $exists = InvoiceAlert::where('schedule_id', $schedule->id)
                ->where('kind', $kind)
                ->where('offset_days', $offsetDays)
                ->where('channel', $channel)
                ->exists();
            if ($exists) continue;

            if ($dry) {
                $created++;
                continue;
            }

            InvoiceAlert::create([
                'invoice_id'  => $invoice->id,
                'schedule_id' => $schedule->id,
                'kind'        => $kind,
                'offset_days' => $offsetDays,
                'due_date'    => $effectiveDate->toDateString(),
                'channel'     => $channel,
                'status'      => 'pending',
            ]);
            $created++;
        }
        return $created;
    }

    protected function dispatchAlert(InvoiceAlert $alert): void
    {
        $invoice = $alert->invoice;

        if ($alert->channel === 'in_app') {
            // Notifications in-app : commercial responsable + admins
            $recipients = collect();
            if ($invoice->commercialUser) $recipients->push($invoice->commercialUser);

            $admins = User::query()
                ->where(function ($q) {
                    $q->whereHas('role', fn($r) => $r->whereIn('value', ['admin']))
                      ->orWhere('role', 'admin');
                })
                ->where('id', '!=', $invoice->commercial_user_id ?? 0)
                ->get();
            $recipients = $recipients->concat($admins)->unique('id');

            if ($recipients->isEmpty()) return;
            Notification::send($recipients, new InvoiceScheduleAlertNotification($alert));
            return;
        }

        if ($alert->channel === 'email') {
            $email = $invoice->client?->email;
            if (!$email) {
                throw new \DomainException("Client #{$invoice->client_id} sans email — alerte impossible.");
            }
            Notification::route('mail', $email)
                ->notify(new InvoiceScheduleAlertNotification($alert));
            return;
        }

        if ($alert->channel === 'whatsapp') {
            // Délégué à un service Twilio existant — Phase 5 si activé
            throw new \DomainException('Canal WhatsApp non encore implémenté côté job (Phase 5).');
        }
    }
}
