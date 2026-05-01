<?php
namespace App\Observers;

use App\Jobs\SendSatisfactionSurvey;
use App\Models\Campaign;
use Illuminate\Support\Facades\Log;

/**
 * CampaignObserver — déclencheurs métier sur les transitions de campagne.
 *
 * Hook actuel : envoi auto de l'enquête de satisfaction 3 jours après
 * le passage au statut TERMINE.
 */
class CampaignObserver
{
    public function updated(Campaign $campaign): void
    {
        // T9 : Envoi enquête de satisfaction après passage en "termine"
        if ($campaign->wasChanged('status')
            && $campaign->status->value === 'termine'
            && $campaign->client_id
        ) {
            // Délai 3 jours — laisse le temps au client de "digérer" la fin
            SendSatisfactionSurvey::dispatch($campaign->id)
                ->delay(now()->addDays(3));

            Log::info('satisfaction.scheduled', [
                'campaign_id' => $campaign->id,
                'client_id'   => $campaign->client_id,
                'will_send_at'=> now()->addDays(3)->toIso8601String(),
            ]);
        }
    }
}
