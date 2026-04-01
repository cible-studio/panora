<?php
namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Log;

class ReservationObserver
{
    public function __construct(
        protected AvailabilityService $availability,
        protected CampaignService     $campaignService
    ) {}

    /**
     * Déclenché après chaque UPDATE sur Reservation.
     *
     * RESPONSABILITÉ UNIQUE de cet observer :
     * Cascader l'annulation vers la Campaign liée.
     *
     * La synchronisation des panneaux (syncPanelStatuses) est déjà
     * effectuée par le controller AVANT d'appeler update().
     * L'observer ne la répète PAS pour éviter les doublons.
     *
     * Exception : si l'observer est déclenché par un update()
     * interne (ex: CampaignService), la synchronisation est gérée
     * à ce niveau-là — pas ici.
     */
    public function updated(Reservation $reservation): void
    {
        if (!$reservation->wasChanged('status')) {
            return;
        }

        $newStatus = $reservation->status->value;

        // ── Seul cas qui déclenche une action de l'observer ──────
        // Annulation → cascader vers la Campaign si elle est active
        if ($newStatus !== ReservationStatus::ANNULE->value) {
            return;
        }

        $linkedCampaign = $reservation->campaign;

        // Pas de campagne liée → rien à faire
        if (!$linkedCampaign) {
            return;
        }

        // Campagne déjà dans un état terminal → idempotent, on skip
        if ($linkedCampaign->status->isTerminal()) {
            Log::debug('reservation_observer.campaign_already_terminal', [
                'reservation_id' => $reservation->id,
                'campaign_id'    => $linkedCampaign->id,
                'campaign_status'=> $linkedCampaign->status->value,
            ]);
            return;
        }

        // CASCADE : annuler la campagne
        // CampaignService::cancel() gère son propre syncPanelStatuses()
        // pour les panneaux de la campagne qui ne sont pas dans la réservation
        $this->campaignService->cancel(
            $linkedCampaign,
            'Cascade — réservation liée annulée (' . $reservation->reference . ')'
        );
    }
}