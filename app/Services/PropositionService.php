<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\ReservationStatus;
use App\Mail\PropositionMail;
use App\Models\Campaign;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * PropositionService — Version finale sans expires_at.
 *
 * LOGIQUE D'EXPIRATION :
 * - PAS de timer arbitraire
 * - Proposition valide TANT QUE :
 *     1. La réservation est en_attente
 *     2. La end_date de la réservation n'est pas dépassée
 * - Expiration manuelle par l'admin : reinitialiser()
 * - Expiration automatique : job nuit vérifie end_date < today
 */
class PropositionService
{
    public function __construct(
        protected AvailabilityService $availability
    ) {}

    // ══════════════════════════════════════════════════════════════
    // ENVOYER
    // ══════════════════════════════════════════════════════════════

    public function envoyer(Reservation $reservation): Reservation
    {
        if ($reservation->status->value !== ReservationStatus::EN_ATTENTE->value) {
            throw new \RuntimeException(
                'INVALID_STATUS:Seules les réservations en attente peuvent être proposées. ' .
                'Statut actuel : ' . $reservation->status->label()
            );
        }

        if ($reservation->client?->trashed()) {
            throw new \RuntimeException('CLIENT_DELETED:Ce client a été supprimé.');
        }

        $email = $reservation->client?->email ?? null;
        if (empty($email)) {
            throw new \RuntimeException(
                'NO_EMAIL:Le client "' . ($reservation->client?->name ?? '?') .
                '" n\'a pas d\'email renseigné.'
            );
        }

        // Vérifier que la période n'est pas dépassée
        if ($reservation->end_date->isPast()) {
            throw new \RuntimeException(
                'PERIOD_PAST:La période de réservation est dépassée (' .
                $reservation->end_date->format('d/m/Y') . '). Créez une nouvelle réservation.'
            );
        }

        $token = $this->generateToken();

        $reservation->update([
            'proposition_token'    => $token,
            'proposition_sent_at'  => now(),
            'proposition_viewed_at'=> null, // reset si renvoi
            // PAS de proposition_expires_at — expiration = end_date dépassée
        ]);

        try {
            Mail::to($email)->send(new PropositionMail($reservation->fresh([
                'client', 'panels.photos', 'panels.commune', 'panels.format',
            ])));
        } catch (\Exception $e) {
            // Rollback si email échoue
            $reservation->update([
                'proposition_token'   => null,
                'proposition_sent_at' => null,
            ]);

            Log::error('proposition.mail_failed', [
                'reservation_id' => $reservation->id,
                'email'          => $email,
                'error'          => $e->getMessage(),
            ]);

            throw new \RuntimeException('MAIL_FAILED:Erreur email : ' . $e->getMessage());
        }

        Log::info('proposition.sent', [
            'reservation_id' => $reservation->id,
            'client_id'      => $reservation->client_id,
            'email'          => $email,
            'valid_until'    => $reservation->end_date->toDateString(),
            'user_id'        => auth()->id(),
        ]);

        return $reservation->fresh();
    }

    // ══════════════════════════════════════════════════════════════
    // VALIDER TOKEN — SOURCE DE VÉRITÉ
    // Expiration = end_date dépassée (pas de timer arbitraire)
    // ══════════════════════════════════════════════════════════════

    public function validerToken(string $token): Reservation
    {
        $reservation = Reservation::where('proposition_token', $token)
            ->with([
                'client',
                'panels.photos',
                'panels.commune',
                'panels.format',
                'panels.zone',
                'panels.category',
            ])
            ->first();

        if (!$reservation) {
            throw new \RuntimeException('TOKEN_INVALID');
        }

        // Expirée si la période de campagne est dépassée
        if ($reservation->end_date->isPast()) {
            throw new \RuntimeException('TOKEN_EXPIRED');
        }

        if ($reservation->status->value === ReservationStatus::CONFIRME->value) {
            throw new \RuntimeException('ALREADY_CONFIRMED');
        }

        if (in_array($reservation->status->value, [
            ReservationStatus::ANNULE->value,
            ReservationStatus::REFUSE->value,
        ])) {
            throw new \RuntimeException('ALREADY_REFUSED');
        }

        return $reservation;
    }

    // ══════════════════════════════════════════════════════════════
    // CONFIRMER — VERROU PESSIMISTE
    // ══════════════════════════════════════════════════════════════

    public function confirmer(Reservation $reservation): array
    {
        return DB::transaction(function () use ($reservation) {

            // Verrou pessimiste — bloque les confirmations simultanées
            $reservation = Reservation::where('id', $reservation->id)
                ->lockForUpdate()
                ->first();

            if ($reservation->status->value !== ReservationStatus::EN_ATTENTE->value) {
                return [
                    'ok'          => false,
                    'reason'      => 'already_processed',
                    'reservation' => $reservation,
                    'campaign'    => null,
                    'conflicts'   => [],
                ];
            }

            // Re-vérifier disponibilité après verrou
            $panelIds  = $reservation->panels()->pluck('panels.id')->toArray();
            $conflicts = $this->availability->getUnavailablePanelIds(
                $panelIds,
                $reservation->start_date->format('Y-m-d'),
                $reservation->end_date->format('Y-m-d'),
                $reservation->id
            );

            if (!empty($conflicts)) {
                $reservation->updateWithoutObservers([
                    'status' => ReservationStatus::ANNULE->value,
                    'notes'  => trim(($reservation->notes ?? '') .
                        "\n[Auto] Annulée — panneaux pris par un autre client le " .
                        now()->format('d/m/Y H:i')),
                ]);

                $this->availability->syncPanelStatuses($panelIds);

                Log::warning('proposition.conflict_on_confirm', [
                    'reservation_id'  => $reservation->id,
                    'conflict_panels' => $conflicts,
                ]);

                return [
                    'ok'        => false,
                    'reason'    => 'panels_taken',
                    'reservation' => $reservation,
                    'campaign'  => null,
                    'conflicts' => $conflicts,
                ];
            }

            $reservation->updateWithoutObservers([
                'status'       => ReservationStatus::CONFIRME->value,
                'confirmed_at' => now(),
                'type'         => 'ferme',
                'notes'        => trim(($reservation->notes ?? '') .
                    "\n[Client] Proposition confirmée le " . now()->format('d/m/Y H:i')),
            ]);

            $campaign = $this->creerCampagne($reservation->fresh(['client', 'panels']));
            $this->availability->syncPanelStatuses($panelIds);

            Log::info('proposition.confirmed', [
                'reservation_id' => $reservation->id,
                'campaign_id'    => $campaign?->id,
                'client_id'      => $reservation->client_id,
            ]);

            return [
                'ok'          => true,
                'reason'      => null,
                'reservation' => $reservation->fresh(),
                'campaign'    => $campaign,
                'conflicts'   => [],
            ];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // REFUSER
    // ══════════════════════════════════════════════════════════════

    public function refuser(Reservation $reservation, string $motif = ''): void
    {
        DB::transaction(function () use ($reservation, $motif) {
            $panelIds = $reservation->panels()->pluck('panels.id')->toArray();

            $reservation->updateWithoutObservers([
                'status' => ReservationStatus::ANNULE->value,
                'notes'  => trim(($reservation->notes ?? '') .
                    "\n[Client] Proposition refusée le " . now()->format('d/m/Y H:i') .
                    ($motif ? " — Motif : {$motif}" : '')),
            ]);

            $this->availability->syncPanelStatuses($panelIds);

            Log::info('proposition.refused', [
                'reservation_id' => $reservation->id,
                'client_id'      => $reservation->client_id,
                'motif'          => $motif,
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // MARQUER VUE
    // ══════════════════════════════════════════════════════════════

    public function marquerVue(Reservation $reservation): void
    {
        if (!$reservation->proposition_viewed_at) {
            $reservation->updateWithoutObservers([
                'proposition_viewed_at' => now(),
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // RÉINITIALISER (admin retire manuellement la proposition)
    // ══════════════════════════════════════════════════════════════

    public function reinitialiser(Reservation $reservation): void
    {
        $reservation->update([
            'proposition_token'     => null,
            'proposition_sent_at'   => null,
            'proposition_viewed_at' => null,
        ]);

        Log::info('proposition.reset', [
            'reservation_id' => $reservation->id,
            'user_id'        => auth()->id(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // EXPIRATION EN BATCH (job nuit)
    // Expire les propositions dont la période est dépassée
    // ══════════════════════════════════════════════════════════════

    public function expireEnBatch(): int
    {
        $count = 0;

        Reservation::where('status', ReservationStatus::EN_ATTENTE->value)
            ->whereNotNull('proposition_token')
            ->where('end_date', '<', now()->toDateString()) // ← end_date, pas expires_at
            ->with('panels')
            ->chunkById(100, function ($reservations) use (&$count) {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation) {
                        $panelIds = $reservation->panels()->pluck('panels.id')->toArray();

                        $reservation->updateWithoutObservers([
                            'status' => ReservationStatus::ANNULE->value,
                            'notes'  => trim(($reservation->notes ?? '') .
                                "\n[Auto] Expirée — période dépassée le " . now()->format('d/m/Y')),
                        ]);

                        if (!empty($panelIds)) {
                            $this->availability->syncPanelStatuses($panelIds);
                        }
                    });
                    $count++;
                }
            });

        if ($count > 0) {
            Log::info('proposition.batch_expired', ['count' => $count]);
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    private function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32)); // 64 chars hex
        } while (Reservation::where('proposition_token', $token)->exists());

        return $token;
    }

    private function creerCampagne(Reservation $reservation): ?Campaign
    {
        if ($reservation->campaign()->exists()) {
            return $reservation->campaign;
        }

        $panelIds    = $reservation->panels->pluck('id')->toArray();
        $nomCampagne = sprintf(
            'Campagne %s — %s',
            $reservation->client->name ?? 'Client',
            $reservation->start_date->translatedFormat('M Y')
        );

        $campaign = Campaign::create([
            'name'           => $nomCampagne,
            'client_id'      => $reservation->client_id,
            'reservation_id' => $reservation->id,
            'user_id'        => $reservation->user_id,
            'start_date'     => $reservation->start_date,
            'end_date'       => $reservation->end_date,
            'status'         => CampaignStatus::ACTIF->value,
            'total_panels'   => count($panelIds),
            'total_amount'   => $reservation->total_amount,
            'notes'          => '[Auto] Créée suite à confirmation de proposition client.',
        ]);

        $campaign->panels()->sync($panelIds);

        Log::info('campaign.created_from_proposition', [
            'campaign_id'    => $campaign->id,
            'reservation_id' => $reservation->id,
        ]);

        return $campaign;
    }
}