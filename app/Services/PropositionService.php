<?php
// ══════════════════════════════════════════════════════════════════
// app/Services/PropositionService.php
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropositionService
{
    // ── VALIDER LE TOKEN ────────────────────────────────────────────

    /**
     * Valide un token de proposition.
     * Lance une RuntimeException si expiré ou déjà traité.
     */
    public function validerToken(string $token): Reservation
    {
        $reservation = Reservation::with(['client', 'panels.photos', 'panels.commune', 'panels.format', 'panels.category'])
            ->where('proposition_token', $token)
            ->firstOrFail();

        // Vérifier expiration (end_date dépassée = proposition caduque)
        if ($reservation->end_date < now()->startOfDay()) {
            throw new \RuntimeException('Cette proposition a expiré.');
        }

        // Vérifier statut
        if (in_array($reservation->status->value, ['confirme', 'annule', 'refuse'])) {
            throw new \RuntimeException("Cette proposition a déjà été {$reservation->status->value}.");
        }

        return $reservation;
    }

    // ── MARQUER VUE ─────────────────────────────────────────────────

    public function marquerVue(Reservation $reservation): void
    {
        if (!$reservation->proposition_viewed_at) {
            $reservation->update(['proposition_viewed_at' => now()]);
        }
    }

    // ── CONFIRMER ───────────────────────────────────────────────────

    /**
     * Confirme une proposition → crée une campagne si pas encore créée.
     * Retourne la Campaign créée ou existante.
     */
    public function confirmer(Reservation $reservation): ?Campaign
    {
        $campaign = null;

        DB::transaction(function () use ($reservation, &$campaign) {

            // Changer statut → confirmé
            $reservation->update([
                'status'       => ReservationStatus::CONFIRME,
                'confirmed_at' => now(),
            ]);

            // Libérer le statut panneaux
            $panelIds = $reservation->panels->pluck('id')->toArray();
            if (!empty($panelIds)) {
                app(AvailabilityService::class)->syncPanelStatuses($panelIds);
            }

            // Créer campagne si elle n'existe pas
            if (!$reservation->campaign) {
                $campaign = Campaign::create([
                    'name'           => "Campagne {$reservation->reference}",
                    'client_id'      => $reservation->client_id,
                    'reservation_id' => $reservation->id,
                    'start_date'     => $reservation->start_date,
                    'end_date'       => $reservation->end_date,
                    'status'         => CampaignStatus::ACTIF->value,
                    'total_panels'   => $reservation->panels->count(),
                    'total_amount'   => $reservation->total_amount,
                    'user_id'        => $reservation->user_id,
                ]);

                $campaign->panels()->sync($panelIds);
            } else {
                $campaign = $reservation->campaign;
            }

            Log::info('proposition.confirmed', [
                'reservation_id' => $reservation->id,
                'campaign_id'    => $campaign->id,
                'client_id'      => $reservation->client_id,
            ]);
        });

        return $campaign;
    }

    // ── REFUSER ─────────────────────────────────────────────────────

    public function refuser(Reservation $reservation, ?string $motif = null): void
    {
        $reservation->update([
            'status' => ReservationStatus::ANNULE,
            'notes'  => $motif
                ? ($reservation->notes ? $reservation->notes . "\n\nRefus client : " . $motif : "Refus client : " . $motif)
                : $reservation->notes,
        ]);

        // Libérer les panneaux
        $panelIds = $reservation->panels->pluck('id')->toArray();
        if (!empty($panelIds)) {
            app(AvailabilityService::class)->syncPanelStatuses($panelIds);
        }

        Log::info('proposition.refused', [
            'reservation_id' => $reservation->id,
            'motif'          => $motif,
            'client_id'      => $reservation->client_id,
        ]);
    }

    // ── EXPIRER EN BATCH ────────────────────────────────────────────

    /**
     * Expire les propositions dont la date de fin est dépassée.
     * Appelé par un Job/Scheduler.
     */
    public function expireEnBatch(): int
    {
        $expired = Reservation::where('status', ReservationStatus::EN_ATTENTE->value)
            ->whereNotNull('proposition_token')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($expired as $r) {
            $r->update(['status' => ReservationStatus::ANNULE->value]);
            $panelIds = $r->panels->pluck('id')->toArray();
            if (!empty($panelIds)) {
                app(AvailabilityService::class)->syncPanelStatuses($panelIds);
            }
            $count++;
        }

        return $count;
    }
}