<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\RelanceFinCampagne;
use App\Services\Diffusion\RelanceFinCampagneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Mail « Votre campagne se termine » (J-7) — actions de l'écran
 * « Diffusion dispos » (2026-10-21). Admin seul.
 *
 * Aucune action ici n'écrit à un client : l'envoi de test part toujours
 * vers les adresses de test, même en mode production.
 */
class RelanceFinCampagneController extends Controller
{
    public function __construct(private RelanceFinCampagneService $service) {}

    /** Le mail tel que le client le recevrait. */
    public function apercu(Campaign $campaign): Response
    {
        abort_unless($campaign->end_date, 404);
        $campaign->loadMissing(['client.contacts', 'commercial', 'user']);

        return response($this->service->mail($campaign)->render());
    }

    public function tester(Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->end_date, 404);
        $campaign->loadMissing(['client.contacts', 'commercial', 'user']);

        $ligne = $this->service->traiter($campaign, forcerTest: true);

        return back()->with(
            $ligne->statut === RelanceFinCampagne::STATUT_ENVOYE ? 'success' : 'error',
            $ligne->statut === RelanceFinCampagne::STATUT_ENVOYE
                ? "Mail de test envoyé pour « {$campaign->name} ». {$ligne->motif}"
                : "Pas d'envoi pour « {$campaign->name} » : {$ligne->motif}"
        );
    }
}
