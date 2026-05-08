<?php
namespace App\Observers;

use App\Jobs\SendCampaignEndedMail;
use App\Jobs\SendSatisfactionSurvey;
use App\Models\Campaign;
use App\Services\AlertService;
use Illuminate\Support\Facades\Log;

/**
 * CampaignObserver — déclencheurs métier sur les transitions de campagne.
 *
 * Évènements gérés :
 *   - created  : alerte 'campagne_creee'
 *   - status PLANIFIE → ACTIF : alerte 'campagne_active'
 *   - status → TERMINE : alerte 'campagne_terminee' + emails (annonce + survey)
 *   - status → ANNULE  : alerte 'reservation_annulee' (côté campagne)
 */
class CampaignObserver
{
    public function created(Campaign $campaign): void
    {
        AlertService::notify(
            'campagne_creee',
            "Campagne créée — {$campaign->name}",
            "La campagne « {$campaign->name} » a été créée"
                . ($campaign->client_id && $campaign->client?->name
                    ? " pour {$campaign->client->name}"
                    : '')
                . ".",
            $campaign,
            ['lien' => route('admin.campaigns.show', $campaign)]
        );
    }

    public function updated(Campaign $campaign): void
    {
        // Pas un changement de statut → on n'agit pas
        if (!$campaign->wasChanged('status')) return;

        $newStatus = $campaign->status->value;
        $oldStatus = $campaign->getOriginal('status');
        $oldStatus = is_object($oldStatus) ? $oldStatus->value : $oldStatus;

        switch ($newStatus) {
            case 'actif':
                if ($oldStatus === 'planifie') {
                    AlertService::notify(
                        'campagne_active',
                        "Campagne lancée — {$campaign->name}",
                        "La campagne « {$campaign->name} » est désormais active.",
                        $campaign,
                        ['lien' => route('admin.campaigns.show', $campaign)]
                    );
                }
                break;

            case 'termine':
                if ($campaign->client_id) {
                    SendCampaignEndedMail::dispatch($campaign->id);
                    SendSatisfactionSurvey::dispatch($campaign->id)
                        ->delay(now()->addDays(3));
                    Log::info('campaign.ended.emails_scheduled', [
                        'campaign_id' => $campaign->id,
                        'client_id'   => $campaign->client_id,
                    ]);
                }

                AlertService::notify(
                    'campagne_terminee',
                    "Campagne terminée — {$campaign->name}",
                    "La campagne « {$campaign->name} » est terminée. Pensez à déclencher la facturation finale.",
                    $campaign,
                    ['lien' => route('admin.campaigns.show', $campaign)]
                );
                break;

            // 'annule' est déjà géré côté CampaignService::cancel() qui crée
            // l'alerte spécifique avec le motif → pas de doublon ici.
        }
    }
}
