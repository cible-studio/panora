<?php
namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncExpiredCampaigns extends Command
{
    protected $signature   = 'campaigns:sync-expired';
    protected $description = 'Passe les campagnes expirées au statut termine';

    public function handle(): int
    {
        $expired = Campaign::whereIn('status', [
                CampaignStatus::ACTIF->value,
            ])
            ->where('end_date', '<', now()->startOfDay())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('✓ Aucune campagne expirée.');
            return self::SUCCESS;
        }

        foreach ($expired as $campaign) {
            $campaign->update(['status' => CampaignStatus::TERMINE->value]);

            Log::info('campaign.auto_expired', [
                'campaign_id' => $campaign->id,
                'name'        => $campaign->name,
                'end_date'    => $campaign->end_date->toDateString(),
            ]);

            $this->line("  → {$campaign->name} terminée");
        }

        $this->info("✓ {$expired->count()} campagne(s) passée(s) en terminée.");
        return self::SUCCESS;
    }
}