<?php
namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\AlertService;
use App\Services\CampaignService;
use Illuminate\Console\Command;

class ActivatePlannedCampaigns extends Command
{
    protected $signature   = 'campaigns:activate-planned';
    protected $description = 'Passe les campagnes planifiées en actif si start_date <= aujourd\'hui (envoie le mail au client)';

    public function handle(CampaignService $service): int
    {
        $campaigns = Campaign::where('status', CampaignStatus::PLANIFIE->value)
            ->where('start_date', '<=', now()->toDateString())
            ->get();

        $activated = 0;
        $blocked   = 0;

        foreach ($campaigns as $campaign) {
            // CampaignService::activate vérifie la garde "au moins 1 panneau",
            // bascule en ACTIF et envoie le mail au client.
            $result = $service->activate($campaign);

            if (!$result['ok']) {
                $this->warn(" ! Campagne #{$campaign->id} ({$campaign->name}) : " . $result['error']);

                // Alerte admin pour signaler une campagne planifiée
                // sans panneau (l'admin doit intervenir).
                AlertService::create(
                    'campagne',
                    'warning',
                    '⚠️ Campagne planifiée sans panneau — ' . $campaign->name,
                    'La date de début est atteinte mais aucun panneau n\'est attaché. Ajoutez des panneaux puis activez manuellement.',
                    $campaign
                );
                $blocked++;
                continue;
            }

            AlertService::create(
                'campagne',
                'info',
                '🎯 Campagne activée automatiquement — ' . $campaign->name,
                'La campagne "' . $campaign->name . '" a débuté aujourd\'hui'
                    . ($result['mail_sent'] ? ' (mail envoyé au client).' : ' (mail non envoyé — vérifier les coordonnées).'),
                $campaign
            );
            $activated++;
        }

        $this->info("$activated campagne(s) activée(s)" . ($blocked ? ", $blocked bloquée(s) sans panneau." : '.'));
        return self::SUCCESS;
    }
}
