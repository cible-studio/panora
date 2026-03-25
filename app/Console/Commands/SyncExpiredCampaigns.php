<?php
// ══════════════════════════════════════════════════════════════════════
// FICHIER 3 — app/Console/Commands/SyncExpiredCampaigns.php
// Campagnes actives dont end_date est passée → statut 'termine'
// ══════════════════════════════════════════════════════════════════════

namespace App\Console\Commands;

use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncExpiredCampaigns extends Command
{
    protected $signature   = 'campaigns:sync-expired';
    protected $description = 'Passe les campagnes actives expirées en "termine"';

    public function handle(): int
    {
        $today = Carbon::today()->format('Y-m-d');

        $expired = Campaign::whereIn('status', ['actif', 'pose'])
            ->where('end_date', '<', $today)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Aucune campagne expirée à traiter.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $campaign) {
            $campaign->update(['status' => 'termine']);

            Log::info('campaign.auto_expired', [
                'campaign_id' => $campaign->id,
                'name'        => $campaign->name,
                'end_date'    => $campaign->end_date,
            ]);

            $count++;
        }

        $this->info("$count campagne(s) expirée(s) terminée(s).");
        return Command::SUCCESS;
    }
}