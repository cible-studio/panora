<?php
namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Log;

/**
 * ReservationObserver
 *
 * RÔLE UNIQUE : quand une Reservation est annulée MANUELLEMENT
 * (par l'utilisateur, pas par CampaignService::cancel()),
 * cascader l'annulation vers la Campaign liée.
 *
 * ⚠️  CampaignService::cancel() utilise withoutObservers() pour
 *     annuler la réservation → cet observer n'est PAS déclenché
 *     dans ce cas → pas de boucle infinie possible.
 *
 * Flux :
 *   ANNULATION CAMPAGNE  : CampaignController → CampaignService::cancel()
 *                          → withoutObservers() → annule réservation
 *                          → syncPanelStatuses() → panneaux libres
 *                          (Observer NON déclenché)
 *
 *   ANNULATION RÉSERVATION : ReservationController → ReservationService::cancel()
 *                            → reservation.update(annule)
 *                            → Observer déclenché
 *                            → CampaignService::cancel() sur la campagne liée
 *                            → syncPanelStatuses()
 */
class ReservationObserver
{
    public function __construct(
        protected AvailabilityService $availability,
        protected CampaignService     $campaignService
    ) {}

    public function updated(Reservation $reservation): void
    {
        // On n'agit que sur un changement de statut vers ANNULE
        if (!$reservation->wasChanged('status')) {
            return;
        }

        if ($reservation->status->value !== ReservationStatus::ANNULE->value) {
            return;
        }

        // ── Synchroniser les panneaux de la réservation ────────────
        // C'est ici la source de vérité : réservation annulée → panneaux libres
        $panelIds = $reservation->panels()->pluck('panels.id')->toArray();
        if (!empty($panelIds)) {
            $this->availability->syncPanelStatuses($panelIds);
        }

        // ── Cascader vers la Campaign si elle existe et est active ──
        $linkedCampaign = $reservation->campaign;

        if (!$linkedCampaign) {
            return;
        }

        if ($linkedCampaign->status->isTerminal()) {
            Log::debug('reservation_observer.campaign_already_terminal', [
                'reservation_id'  => $reservation->id,
                'campaign_id'     => $linkedCampaign->id,
                'campaign_status' => $linkedCampaign->status->value,
            ]);
            return;
        }

        // CampaignService::cancel() appellera withoutObservers()
        // sur la réservation → pas de boucle
        $this->campaignService->cancel(
            $linkedCampaign,
            'Cascade — réservation liée annulée (' . $reservation->reference . ')'
        );
    }
}