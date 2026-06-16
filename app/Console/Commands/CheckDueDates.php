<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceSchedule;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * finance:check-due-dates
 *
 * Cron quotidien (06h30) qui parcourt l'échéancier prévisionnel des
 * factures actives et émet 3 types d'alertes selon la position de
 * l'échéance par rapport à aujourd'hui :
 *
 *   J-7  : échéance dans 7 jours pile      → echeance_proche_j7  (info)
 *   J-3  : échéance dans 3 jours pile      → echeance_proche_j3  (warning)
 *   J0+  : échéance dépassée non payée     → echeance_depassee   (danger)
 *
 * Anti-spam :
 *   • La table invoice_schedules a déjà reminder_count + reminded_at.
 *   • Chaque palier est émis 1× max grâce au dedup_key d'AlertService
 *     (clé "echeance_proche_jX:Schedule:<id>") qui bump l'existante
 *     plutôt que d'en créer une nouvelle. On incrémente quand même
 *     reminder_count pour audit.
 *
 * Ciblage :
 *   • On résout le commercial de la facture via campaign.resolveCommercialContact()
 *   • Alerte créée avec user_id = commercial->id (cible directe).
 *   • Admin voit ces alertes par défaut (admin voit toutes les alertes).
 *   • MP/Technique : pas concernés.
 */
class CheckDueDates extends Command
{
    protected $signature   = 'finance:check-due-dates';
    protected $description = 'Détecte les échéances de facture J-7, J-3 et dépassées et émet les alertes correspondantes.';

    public function handle(): int
    {
        $this->info('Analyse des échéances facture…');

        $today = Carbon::today();

        // On charge en bloc avec les relations utilisées par notify()
        // pour résoudre le commercial sans N+1.
        $schedules = InvoiceSchedule::query()
            ->whereNull('paid_at')
            ->whereHas('invoice', fn($q) => $q
                ->whereNotIn('status', ['brouillon', 'annulee'])
                ->whereNull('credit_note_for_id'))
            ->with([
                'invoice.client:id,name',
                'invoice.campaign',
                'invoice.creator:id,name',
            ])
            ->get();

        $counts = ['j7' => 0, 'mail_j7' => 0, 'j3' => 0, 'depassee' => 0];

        foreach ($schedules as $sch) {
            /** @var Invoice $invoice */
            $invoice = $sch->invoice;
            if (!$invoice) continue;

            // Pas la peine d'alerter si la facture est globalement soldée
            // (cas rare : versement couvre toute la facture mais une ligne
            // d'échéancier n'a pas été cochée individuellement)
            if ($invoice->remainingAmount() <= 0.01) continue;

            $dueDate = $sch->due_date;
            if (!$dueDate) continue;

            $diff = (int) $today->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);
            // diff > 0  → due_date dans le futur (X jours restants)
            // diff < 0  → due_date passée (|X| jours de retard)

            if ($diff > 7) continue; // Trop tôt pour alerter

            $commercial   = $this->resolveCommercial($invoice);
            $targetUserId = $commercial?->id;

            if ($diff < 0) {
                $alert = AlertService::notify(
                    'echeance_depassee',
                    "🔴 Échéance dépassée — facture {$invoice->reference}",
                    "L'échéance « " . ($sch->label ?? 'Solde') . " » (" . number_format($sch->amount, 0, ',', ' ')
                        . " FCFA) du client {$invoice->client?->name} est en retard de "
                        . abs($diff) . " jour(s). Reste à payer : "
                        . number_format($invoice->remainingAmount(), 0, ',', ' ') . " FCFA.",
                    $invoice,
                    [
                        'lien'        => route('admin.invoices.show', $invoice),
                        'user_id'     => $targetUserId,
                        'dedup_extra' => 'sch:' . $sch->id,
                    ]
                );
                if ($alert) {
                    $counts['depassee']++;
                    $this->touchReminder($sch);
                }
                continue;
            }

            if ($diff === 3) {
                $alert = AlertService::notify(
                    'echeance_proche_j3',
                    "⚠️ Échéance dans 3 jours — facture {$invoice->reference}",
                    "L'échéance « " . ($sch->label ?? 'Solde') . " » (" . number_format($sch->amount, 0, ',', ' ')
                        . " FCFA) du client {$invoice->client?->name} arrive dans 3 jours (le "
                        . $dueDate->format('d/m/Y') . ").",
                    $invoice,
                    [
                        'lien'        => route('admin.invoices.show', $invoice),
                        'user_id'     => $targetUserId,
                        'dedup_extra' => 'sch:' . $sch->id . ':j3',
                    ]
                );
                if ($alert) { $counts['j3']++; $this->touchReminder($sch); }
                continue;
            }

            if ($diff === 7) {
                $alert = AlertService::notify(
                    'echeance_proche_j7',
                    "📅 Échéance dans 7 jours — facture {$invoice->reference}",
                    "L'échéance « " . ($sch->label ?? 'Solde') . " » (" . number_format($sch->amount, 0, ',', ' ')
                        . " FCFA) du client {$invoice->client?->name} arrive dans 7 jours (le "
                        . $dueDate->format('d/m/Y') . ").",
                    $invoice,
                    [
                        'lien'        => route('admin.invoices.show', $invoice),
                        'user_id'     => $targetUserId,
                        'dedup_extra' => 'sch:' . $sch->id . ':j7',
                    ]
                );
                if ($alert) { $counts['j7']++; $this->touchReminder($sch); }

                // Email client J-7 : envoi automatique au client final pour
                // qu'il anticipe le règlement. Idempotence : on n'envoie
                // qu'UNE fois par couple (schedule, palier J-7) en posant
                // un PublicLink dédié dont la metadata sert de marqueur.
                if ($this->sendClientReminderMail($invoice, $sch, 7)) {
                    $counts['mail_j7']++;
                }
            }
        }

        $this->line("  📅 Alertes J-7   : {$counts['j7']}");
        $this->line("  📨 Emails client J-7 : {$counts['mail_j7']}");
        $this->line("  ⚠️  Alertes J-3   : {$counts['j3']}");
        $this->line("  🔴 Alertes dépassée : {$counts['depassee']}");
        $this->info('Total : ' . array_sum($counts) . ' notification(s) émise(s).');

        return Command::SUCCESS;
    }

    /**
     * Envoie un rappel par email au client pour une échéance proche.
     *
     * Idempotent : si un PublicLink "reminder J-7" existe déjà pour ce
     * couple (invoice, schedule), on ne renvoie pas. L'admin peut purger
     * manuellement via /admin/alerts si besoin de relancer.
     *
     * @return bool true si email envoyé, false si skip (déjà envoyé,
     *              pas d'email client, ou erreur silencieuse).
     */
    protected function sendClientReminderMail(Invoice $invoice, InvoiceSchedule $sch, int $palierJours): bool
    {
        $client = $invoice->client;
        if (!$client?->email) return false;

        // Marqueur d'idempotence dans PublicLink::metadata.
        $alreadySent = \App\Models\PublicLink::where('type', 'invoice')
            ->where('target_type', Invoice::class)
            ->where('target_id', $invoice->id)
            ->where('metadata->schedule_reminder_j' . $palierJours, $sch->id)
            ->exists();

        if ($alreadySent) return false;

        try {
            // Lien public sécurisé (palierJours + buffer 7j)
            $link = \App\Services\PublicLinkService::create(
                target:    $invoice,
                type:      'invoice',
                expiresAt: now()->addDays($palierJours + 7),
                clientId:  $invoice->client_id,
                metadata:  ['schedule_reminder_j' . $palierJours => $sch->id],
            );

            \Illuminate\Support\Facades\Mail::to($client->email)
                ->send(new \App\Mail\InvoiceScheduleReminderMail(
                    invoice:       $invoice,
                    schedule:      $sch,
                    link:          $link,
                    daysUntilDue:  $palierJours,
                ));

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('schedule_reminder.mail_failed', [
                'invoice_id'  => $invoice->id,
                'schedule_id' => $sch->id,
                'palier'      => $palierJours,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Marque le rappel comme envoyé — sert d'audit et permet à terme
     * d'afficher "Dernier rappel : DD/MM" sur la fiche facture.
     */
    protected function touchReminder(InvoiceSchedule $sch): void
    {
        // Update direct, pas via Eloquent::save() pour ne pas déclencher
        // d'observer qui pourrait re-vérifier l'échéance (boucle).
        DB::table('invoice_schedules')
            ->where('id', $sch->id)
            ->update([
                'reminded_at'    => now(),
                'reminder_count' => DB::raw('reminder_count + 1'),
                'updated_at'     => now(),
            ]);
    }

    protected function resolveCommercial(Invoice $invoice)
    {
        $camp = $invoice->campaign;
        if ($camp && method_exists($camp, 'resolveCommercialContact')) {
            $c = $camp->resolveCommercialContact();
            if ($c) return $c;
        }
        return $invoice->creator;
    }
}
