<?php
namespace App\Observers;

use App\Models\ExternalPanel;

class ExternalPanelObserver
{
    public function saving(ExternalPanel $panel): void
    {
        // Si une campagne est liée → occupé
        if ($panel->campaign_id) {
            $campaign = \App\Models\Campaign::find($panel->campaign_id);
            if ($campaign) {
                $status = $campaign->status->value ?? $campaign->status;
                if (in_array($status, ['actif', 'pose', 'confirme'])) {
                    $panel->availability_status = 'occupe';
                } elseif (in_array($status, ['termine', 'annule'])) {
                    $panel->availability_status = 'a_verifier';
                }
            }
        } else {
            // Pas de campagne → à vérifier (sauf si déjà disponible)
            if ($panel->availability_status === 'occupe') {
                $panel->availability_status = 'a_verifier';
            }
        }
    }
}
