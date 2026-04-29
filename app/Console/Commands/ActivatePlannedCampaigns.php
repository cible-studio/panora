<?php
namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Console\Command;

class ActivatePlannedCampaigns extends Command
{
    protected $signature   = 'campaigns:activate-planned';
    protected $description = 'Passe les campagnes planifiées en actif si start_date <= aujourd\'hui';

    public function handle(): void
    {
        $count = Campaign::where('status', CampaignStatus::PLANIFIE->value)
            ->where('start_date', '<=', now()->toDateString())
            ->update(['status' => CampaignStatus::ACTIF->value]);

        $this->info("$count campagne(s) activée(s).");
    }
}