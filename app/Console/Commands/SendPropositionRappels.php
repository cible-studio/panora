<?php
namespace App\Console\Commands;

use App\Mail\PropositionRappelMail;
use App\Models\Reservation;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPropositionRappels extends Command
{
    protected $signature   = 'propositions:send-rappels';
    protected $description = 'Envoie les rappels J+2 et J+5 aux clients dont la proposition est en attente';

    public function handle(NotificationMailer $mailer): int
    {
        $sent  = 0;
        $skipped = 0;

        foreach ([2, 5] as $jour) {
            $reservations = Reservation::query()
                ->where('status', 'en_attente')
                ->whereNotNull('proposition_token')
                ->whereNotNull('proposition_sent_at')
                ->where('proposition_expires_at', '>', now())
                ->whereDate('proposition_sent_at', now()->subDays($jour)->toDateString())
                ->with('client')
                ->get();

            foreach ($reservations as $reservation) {
                $email = $reservation->client?->email ?? null;

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    Log::warning('proposition.rappel.no_email', [
                        'reservation_id' => $reservation->id,
                        'jour'           => $jour,
                    ]);
                    continue;
                }

                $result = $mailer->sendSilently(
                    $email,
                    new PropositionRappelMail($reservation, $jour),
                    context: [
                        'action'         => 'proposition.rappel',
                        'reservation_id' => $reservation->id,
                        'jour_rappel'    => $jour,
                    ]
                );

                if ($result) {
                    $sent++;
                    $this->info("J+{$jour} → {$reservation->reference} ({$email})");
                } else {
                    $skipped++;
                    $this->warn("J+{$jour} → {$reservation->reference} — échec envoi ({$email})");
                }
            }
        }

        $this->info("Rappels envoyés : {$sent} | Ignorés / KO : {$skipped}");
        return self::SUCCESS;
    }
}
