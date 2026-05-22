<?php
// ══════════════════════════════════════════════════════════════════════
// FICHIER 3 — app/Console/Commands/SyncExpiredCampaigns.php
// Campagnes actives dont end_date est passée → statut 'termine'
//
// Utilise CampaignService::terminate() (et NON un UPDATE direct) pour
// déclencher proprement :
//   - le passage des PoseTask non-faites en ANNULEE (sinon poses fantômes)
//   - l'alerte interne associée
//   - les hooks observer/log
//
// Bug historique : avant 2026-05, on faisait `$campaign->update(['status'])`
// qui sautait toute la logique métier de terminaison.
// ══════════════════════════════════════════════════════════════════════

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncExpiredCampaigns extends Command
{
    protected $signature   = 'campaigns:sync-expired';
    protected $description = 'Passe les campagnes actives expirées en "termine"';

    public function handle(CampaignService $service): int
    {
        $today = Carbon::today()->format('Y-m-d');

        $expired = Campaign::where('status', 'actif')
            ->where('end_date', '<', $today)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Aucune campagne expirée à traiter.');
            return Command::SUCCESS;
        }

        $count   = 0;
        $errors  = 0;
        foreach ($expired as $campaign) {
            try {
                // terminate() :
                //  - met status = termine
                //  - annule les pose_tasks non-faites (status → annulee)
                //  - termine la résa associée si is_technical
                //  - log + alerte
                $service->terminate($campaign, 'Auto-terminée — date de fin dépassée');

                Log::info('campaign.auto_expired', [
                    'campaign_id' => $campaign->id,
                    'name'        => $campaign->name,
                    'end_date'    => $campaign->end_date->format('Y-m-d'),
                ]);
                $count++;
            } catch (\Throwable $e) {
                $errors++;
                $this->error(" ! Échec pour campagne #{$campaign->id} ({$campaign->name}) : {$e->getMessage()}");
                Log::warning('campaign.auto_expired.failed', [
                    'campaign_id' => $campaign->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $msg = "{$count} campagne(s) expirée(s) terminée(s) proprement";
        if ($errors > 0) {
            $msg .= ", {$errors} en échec";
        }
        $this->info($msg . '.');
        return Command::SUCCESS;
    }
}
