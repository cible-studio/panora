<?php

namespace App\Console\Commands;

use App\Mail\CampaignEndingSoonMail;
use App\Models\Campaign;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notifie les clients dont la campagne arrive à échéance dans X jours.
 *
 * Idempotent : utilise un flag cache "ending_notified.{campaign_id}.{days}"
 * (TTL 60 jours) pour éviter de spammer si la commande tourne plusieurs
 * fois la même journée.
 *
 * Schedule recommandé : --days=7 quotidien à 10h00.
 */
class NotifyEndingCampaigns extends Command
{
    protected $signature = 'campaigns:notify-ending
                            {--days=7 : Nombre de jours avant fin de campagne}
                            {--dry-run : Affiche sans envoyer}';

    protected $description = "Envoie une relance email aux clients dont la campagne se termine dans N jours";

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $target = now()->addDays($days)->startOfDay();
        $end    = $target->copy()->endOfDay();

        $sent = 0; $skipped = 0;

        $campaigns = Campaign::where('status', 'actif')
            ->whereBetween('end_date', [$target, $end])
            ->whereNull('deleted_at')
            ->with('client')
            ->get();

        foreach ($campaigns as $campaign) {
            $email = $campaign->client?->email;
            if (!$email) { $skipped++; continue; }

            $cacheKey = "ending_notified.{$campaign->id}.{$days}";
            if (Cache::has($cacheKey)) { $skipped++; continue; }

            if ($dryRun) {
                $this->info("[DRY] #{$campaign->id} {$campaign->name} → {$email} (J-{$days})");
                continue;
            }

            try {
                app(NotificationMailer::class)->sendSilently(
                    $email,
                    new CampaignEndingSoonMail($campaign, $days),
                    cc: null,
                    context: ['campaign_id' => $campaign->id, 'days_remaining' => $days],
                );
                Cache::put($cacheKey, true, now()->addDays(60));
                $sent++;
                $this->info("✓ #{$campaign->id} {$campaign->name} → {$email}");
            } catch (\Throwable $e) {
                Log::error('campaign.ending_notify.failed', [
                    'campaign_id' => $campaign->id, 'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->line('');
        $this->info("Bilan J-{$days} : {$sent} envoyé(s) · {$skipped} ignoré(s)");
        return self::SUCCESS;
    }
}
