<?php
namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Console\Command;

use App\Services\AlertService;

class ActivatePlannedCampaigns extends Command
{
    protected $signature   = 'campaigns:activate-planned';
    protected $description = 'Passe les campagnes planifiées en actif si start_date <= aujourd\'hui';

    public function handle(): void
    {
        $campaigns = Campaign::where('status', CampaignStatus::PLANIFIE->value)
            ->where('start_date', '<=', now()->toDateString())
            ->get();

        $count = 0;
        
        foreach ($campaigns as $campaign) {
            $campaign->status = CampaignStatus::ACTIF->value;
            $campaign->save();
            
            // Alerte activation auto
            AlertService::create(
                'campagne',
                'info',
                '🎯 Campagne activée automatiquement — ' . $campaign->name,
                'La campagne "' . $campaign->name . '" a débuté automatiquement aujourd\'hui.',
                $campaign
            );
            
            $count++;
        }

        $this->info("$count campagne(s) activée(s).");
    }
}