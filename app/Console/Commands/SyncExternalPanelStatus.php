<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExternalPanel;

class SyncExternalPanelStatus extends Command
{
    protected $signature   = 'external-panels:sync-status';
    protected $description = 'Synchronise le statut des panneaux externes selon leurs campagnes';

    public function handle(): int
    {
        $panels = ExternalPanel::with('campaign')->whereNotNull('campaign_id')->get();
        $updated = 0;

        foreach ($panels as $panel) {
            $campaign = $panel->campaign;
            if (!$campaign) {
                $panel->update(['availability_status' => 'a_verifier', 'campaign_id' => null]);
                $updated++;
                continue;
            }

            $status = $campaign->status->value ?? $campaign->status;
            $newStatus = in_array($status, ['actif', 'pose', 'confirme'])
                ? 'occupe'
                : 'a_verifier';

            if ($panel->availability_status !== $newStatus) {
                $panel->updateQuietly(['availability_status' => $newStatus]);
                $updated++;
            }
        }

        $this->info("$updated panneau(x) externe(s) mis à jour.");
        return Command::SUCCESS;
    }
}
