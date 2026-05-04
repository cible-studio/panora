<?php
namespace App\Observers;

use App\Jobs\SendCampaignEndedMail;
use App\Jobs\SendSatisfactionSurvey;
use App\Models\Campaign;
use Illuminate\Support\Facades\Log;

/**
 * CampaignObserver — déclencheurs métier sur les transitions de campagne.
 *
 * Quand une campagne passe en statut TERMINE :
 *   J+0 : SendCampaignEndedMail — annonce la fin + lien questionnaire
 *   J+3 : SendSatisfactionSurvey — rappel si questionnaire non encore rempli
 */
class CampaignObserver
{
    public function updated(Campaign $campaign): void
    {
        if ($campaign->wasChanged('status')
            && $campaign->status->value === 'termine'
            && $campaign->client_id
        ) {
            // J+0 : email d'annonce de fin de campagne avec lien questionnaire
            SendCampaignEndedMail::dispatch($campaign->id);

            // J+3 : rappel si le questionnaire n'est pas encore complété
            SendSatisfactionSurvey::dispatch($campaign->id)
                ->delay(now()->addDays(3));

            Log::info('campaign.ended.emails_scheduled', [
                'campaign_id'    => $campaign->id,
                'client_id'      => $campaign->client_id,
                'reminder_at'    => now()->addDays(3)->toIso8601String(),
            ]);
        }
    }
}
