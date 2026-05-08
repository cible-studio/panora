<?php
namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AlertService;
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

    public function created(Reservation $reservation): void
    {
        AlertService::notify(
            'reservation_nouvelle',
            "Nouvelle réservation — " . ($reservation->client?->name ?? 'Client inconnu'),
            "Réservation {$reservation->reference} créée pour la période "
                . $reservation->start_date->format('d/m/Y') . " → "
                . $reservation->end_date->format('d/m/Y') . '.',
            $reservation,
            ['lien' => route('admin.reservations.show', $reservation)]
        );
    }

    public function updated(Reservation $reservation): void
    {
        // On agit uniquement sur les changements de statut
        if (!$reservation->wasChanged('status')) {
            return;
        }

        $newStatus = $reservation->status->value;

        // Confirmation → alerte ciblée
        if ($newStatus === ReservationStatus::CONFIRME->value) {
            AlertService::notify(
                'reservation_confirmee',
                "Réservation confirmée — " . ($reservation->client?->name ?? '—'),
                "La réservation {$reservation->reference} a été confirmée.",
                $reservation,
                ['lien' => route('admin.reservations.show', $reservation)]
            );
            return;
        }

        // Sortie de scope : ne traiter que l'annulation pour la cascade
        if ($newStatus !== ReservationStatus::ANNULE->value) {
            return;
        }

        AlertService::notify(
            'reservation_annulee',
            "Réservation annulée — " . ($reservation->client?->name ?? '—'),
            "La réservation {$reservation->reference} a été annulée"
                . ($reservation->cancel_reason ? " (motif : {$reservation->cancel_reason})" : '')
                . '.',
            $reservation,
            ['lien' => route('admin.reservations.show', $reservation)]
        );

        // ── Synchroniser les panneaux de la réservation ────────────
        // C'est ici la source de vérité : réservation annulée → panneaux libres.
        // Sync internes ET externes (les deux relations utilisent reservation_panels).
        $panelIds = $reservation->panels()->pluck('panels.id')->toArray();
        if (!empty($panelIds)) {
            $this->availability->syncPanelStatuses($panelIds);
        }

        $externalPanelIds = $reservation->externalPanels()->pluck('external_panels.id')->toArray();
        if (!empty($externalPanelIds)) {
            $this->availability->syncExternalPanelStatuses($externalPanelIds);
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