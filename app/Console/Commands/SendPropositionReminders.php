<?php
namespace App\Console\Commands;

use App\Mail\PropositionReminderMail;
use App\Models\Reservation;
use App\Services\NotificationMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Envoie les rappels J+2 et J+5 sur les propositions encore "en_attente"
 * et non expirées. Idempotent : utilise les flags
 * `proposition_reminded_j2_at` / `proposition_reminded_j5_at` pour ne pas
 * réenvoyer le même rappel sur plusieurs runs.
 *
 * Cron prévu : daily 09:00 (cf. Console\Kernel ou routes/console.php).
 */
class SendPropositionReminders extends Command
{
    protected $signature   = 'propositions:send-reminders
                                {--dry-run : Affiche ce qui serait envoyé sans envoyer}';
    protected $description = 'Envoie les rappels J+2 et J+5 sur les propositions en attente';

    public function handle(NotificationMailer $mailer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now    = now();

        // Sélection : propositions actives (en_attente, token + sent_at présents,
        // expiration future). On exclut les réservations dont le client est
        // soft-deleted ou sans email (skip défensif).
        $candidates = Reservation::query()
            ->with(['client', 'user', 'panels', 'externalPanels'])
            ->where('status', 'en_attente')
            ->whereNotNull('proposition_token')
            ->whereNotNull('proposition_sent_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('proposition_expires_at')
                  ->orWhere('proposition_expires_at', '>', $now);
            })
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Aucune proposition à relancer.');
            return Command::SUCCESS;
        }

        $sentJ2 = $sentJ5 = $skipped = 0;

        foreach ($candidates as $r) {
            // Garde-fou : client supprimé ou email manquant → skip silencieux
            if (!$r->client || $r->client->trashed()) {
                $this->_logSkip($r, 'client_deleted');
                $skipped++;
                continue;
            }
            if (empty($r->client->email) || !filter_var($r->client->email, FILTER_VALIDATE_EMAIL)) {
                $this->_logSkip($r, 'no_valid_email');
                $skipped++;
                continue;
            }

            $sentAt = $r->proposition_sent_at;
            $hours  = (int) $sentAt->diffInHours($now);

            // ── J+5 (priorité haute — on l'évalue avant J+2 pour traiter
            //   correctement les cas où on rattrape un cron raté)
            if ($hours >= 24 * 5 && empty($r->proposition_reminded_j5_at)) {
                $this->_send($r, 5, $mailer, $dryRun);
                $sentJ5++;
                continue;
            }

            // ── J+2 (saut si le client a déjà ouvert le lien — pas la peine
            //   de relancer quelqu'un qui a vu mais pas encore tranché ;
            //   J+5 le relancera quand même)
            if ($hours >= 24 * 2 && empty($r->proposition_reminded_j2_at)) {
                if ($r->proposition_viewed_at !== null) {
                    $this->_logSkip($r, 'j2_already_viewed');
                    $skipped++;
                    continue;
                }
                $this->_send($r, 2, $mailer, $dryRun);
                $sentJ2++;
                continue;
            }

            $skipped++;
        }

        $this->info(sprintf(
            '%s · J+2 envoyés: %d · J+5 envoyés: %d · Sautés: %d · Total candidates: %d',
            $dryRun ? '[DRY-RUN]' : '[LIVE]',
            $sentJ2,
            $sentJ5,
            $skipped,
            $candidates->count()
        ));

        return Command::SUCCESS;
    }

    private function _send(Reservation $r, int $step, NotificationMailer $mailer, bool $dryRun): void
    {
        $col = $step === 2 ? 'proposition_reminded_j2_at' : 'proposition_reminded_j5_at';

        if ($dryRun) {
            $this->line(sprintf(
                '  [DRY] Would send J+%d reminder to %s for reservation %s',
                $step, $r->client->email, $r->reference
            ));
            return;
        }

        $result = $mailer->sendNow(
            $r->client->email,
            new PropositionReminderMail($r, $step),
            context: [
                'action'         => 'proposition.reminder',
                'reservation_id' => $r->id,
                'reference'      => $r->reference,
                'step'           => $step,
            ]
        );

        if ($result->ok ?? false) {
            // Idempotence : tag le flag même si le mail est en queue —
            // si l'envoi finit en KO on aura le log mail.failed pour
            // diagnostiquer mais on ne re-spam pas le client demain.
            $r->forceFill([$col => now()])->saveQuietly();

            Log::info('proposition.reminder.sent', [
                'reservation_id' => $r->id,
                'reference'      => $r->reference,
                'step'           => $step,
                'recipient'      => $r->client->email,
            ]);
            $this->line("  ✓ J+{$step} envoyé : {$r->reference} → {$r->client->email}");
        } else {
            Log::warning('proposition.reminder.failed', [
                'reservation_id' => $r->id,
                'reference'      => $r->reference,
                'step'           => $step,
                'recipient'      => $r->client->email,
                'error'          => $result->message ?? 'unknown',
            ]);
            $this->warn("  ✗ J+{$step} ÉCHEC : {$r->reference} ({$result->message})");
        }
    }

    private function _logSkip(Reservation $r, string $reason): void
    {
        Log::info('proposition.reminder.skipped', [
            'reservation_id' => $r->id,
            'reference'      => $r->reference,
            'reason'         => $reason,
        ]);
    }
}
