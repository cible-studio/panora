<?php

namespace App\Console\Commands;

use App\Mail\CampaignEndingCommercialMail;
use App\Models\Campaign;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notifie le COMMERCIAL assigné d'une campagne qui se termine dans N jours.
 *
 * ⚠ Ne pas confondre avec `campaigns:notify-ending` (destinataire = CLIENT).
 * Cette command cible SPÉCIFIQUEMENT le commercial pour qu'il prépare le
 * suivi post-campagne (satisfaction, upsell) — pas le client.
 *
 * Feedback user 2026-08-XX : le commercial doit être averti automatiquement,
 * il rédige lui-même son mail de suivi (pas de template auto).
 *
 * Idempotent : cache flag "commercial_ending_notified.{campaign_id}.{days}"
 * TTL 60j pour éviter les doublons si la commande tourne 2× le même jour.
 *
 * Skip si commercial non assigné (log info, pas d'erreur).
 * Kill-switch : respecte config('mail.staff_alerts_enabled') via
 * NotificationMailer standard.
 *
 * Schedule recommandé : --days=3 quotidien à 09h00.
 */
class NotifyEndingCommercial extends Command
{
    protected $signature = 'campaigns:notify-commercial-ending
                            {--days=3 : Nombre de jours avant fin de campagne}
                            {--dry-run : Affiche sans envoyer}';

    protected $description = "Envoie une alerte email au commercial assigné dont la campagne se termine dans N jours";

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $target = now()->addDays($days)->startOfDay();
        $end    = $target->copy()->endOfDay();

        $sent = 0; $skipped = 0; $noCommercial = 0;

        $campaigns = Campaign::where('status', 'actif')
            ->whereBetween('end_date', [$target, $end])
            ->whereNull('deleted_at')
            ->with(['client:id,name', 'user:id,name,email'])
            ->get();

        foreach ($campaigns as $campaign) {
            $commercial = $campaign->user;
            if (!$commercial || empty($commercial->email)) {
                $noCommercial++;
                Log::info('campaign.ending_commercial.skipped_no_commercial', [
                    'campaign_id' => $campaign->id,
                    'name'        => $campaign->name,
                ]);
                continue;
            }

            $cacheKey = "commercial_ending_notified.{$campaign->id}.{$days}";
            if (Cache::has($cacheKey)) { $skipped++; continue; }

            if ($dryRun) {
                $this->info("[DRY] #{$campaign->id} {$campaign->name} → {$commercial->email} (J-{$days})");
                continue;
            }

            try {
                app(NotificationMailer::class)->sendSilently(
                    $commercial->email,
                    new CampaignEndingCommercialMail($campaign, $days),
                    cc: null,
                    context: [
                        'campaign_id'    => $campaign->id,
                        'days_remaining' => $days,
                        'commercial_id'  => $commercial->id,
                    ],
                );
                Cache::put($cacheKey, true, now()->addDays(60));
                $sent++;
                $this->info("✓ #{$campaign->id} {$campaign->name} → {$commercial->email}");
            } catch (\Throwable $e) {
                Log::error('campaign.ending_commercial.failed', [
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->line('');
        $this->info("Bilan J-{$days} commercial : {$sent} envoyé(s) · {$skipped} ignoré(s) · {$noCommercial} sans commercial");
        return self::SUCCESS;
    }
}
