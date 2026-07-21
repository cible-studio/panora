<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\ReservationStatus;
use App\Models\Quote;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuoteConverter — transforme un devis accepté en réservation ferme.
 *
 * Étapes :
 *   1. Verrouillage transactionnel (pour éviter le double-clic client)
 *   2. Double-check disponibilité des panneaux via AvailabilityService
 *   3. Cas nominal : création Reservation type=ferme + attach panneaux,
 *      statut ACCEPTE sur le devis, converted_reservation_id renseigné
 *   4. Cas conflit : statut ACCEPTE_AVEC_CONFLIT sur le devis, alerte au
 *      commercial, aucune réservation créée (il devra ajuster à la main)
 *
 * Note : on ne crée PAS la campagne ici — le workflow Panora existant la
 * crée automatiquement quand une Reservation passe en CONFIRME, via
 * CampaignService (déjà déclenché par ReservationService::confirm).
 */
class QuoteConverter
{
    public function __construct(
        protected AvailabilityService $availability,
        protected ReservationService  $reservationService,
    ) {}

    /**
     * Convertit un devis accepté en Reservation ferme + campagne.
     *
     * @return array{status: 'converted'|'conflict', reservation?: Reservation, conflicts?: array}
     */
    public function convertFromQuote(Quote $quote, ?string $decidedBy = null): array
    {
        if (!in_array($quote->status, [QuoteStatus::ENVOYE, QuoteStatus::ACCEPTE_AVEC_CONFLIT], true)) {
            throw new \LogicException("Devis {$quote->reference} n'est pas dans un état acceptable.");
        }
        if ($quote->converted_reservation_id) {
            throw new \LogicException("Devis {$quote->reference} déjà converti.");
        }

        return DB::transaction(function () use ($quote, $decidedBy) {
            $quote->loadMissing(['lines', 'services']);

            // 1) Récupérer les panneaux internes + externes des lignes
            $panelIds    = $quote->lines->pluck('panel_id')->filter()->unique()->all();
            $extPanelIds = $quote->lines->pluck('external_panel_id')->filter()->unique()->all();

            // 2) Verrouillage pessimiste des panneaux (anti-double-booking)
            if (!empty($panelIds)) {
                \App\Models\Panel::whereIn('id', $panelIds)->lockForUpdate()->get();
            }

            // 3) Double-check disponibilité sur la période visée
            $periodStart = $quote->period_start?->toDateString() ?? now()->toDateString();
            $periodEnd   = $quote->period_end?->toDateString()
                ?? now()->addMonths((int) ceil($quote->lines->max('duree_mois') ?? 1))->toDateString();

            $conflicts = [];
            if (!empty($panelIds)) {
                $unavailable = $this->availability->getUnavailablePanelIds($panelIds, $periodStart, $periodEnd);
                if (!empty($unavailable)) {
                    $conflicts['internal'] = $unavailable;
                }
            }

            // Cas conflit : on marque le devis mais on ne crée rien.
            if (!empty($conflicts)) {
                $quote->update([
                    'status'          => QuoteStatus::ACCEPTE_AVEC_CONFLIT->value,
                    'decision_at'     => now(),
                    'decision_reason' => 'Panneaux indisponibles au moment de la conversion : '
                        . implode(', ', array_map('strval', $unavailable)),
                ]);

                AlertService::create('devis', 'danger',
                    '⚠️ Conflit conversion devis — ' . $quote->reference,
                    'Le client a accepté le devis ' . $quote->reference
                    . ' mais ' . count($unavailable) . ' panneau(x) ne sont plus disponibles.'
                    . ' Le commercial ' . ($quote->commercial?->name ?? '—') . ' doit ajuster.',
                    $quote
                );

                Log::warning('quote.convert.conflict', [
                    'quote_id'    => $quote->id,
                    'unavailable' => $unavailable,
                    'decided_by'  => $decidedBy,
                ]);

                return ['status' => 'conflict', 'conflicts' => $conflicts];
            }

            // 4) Cas nominal : création de la réservation ferme
            // Génération de référence RES-XXXXXXXX (aligné avec ReservationService)
            do {
                $ref = 'RES-' . strtoupper(\Illuminate\Support\Str::random(8));
            } while (Reservation::where('reference', $ref)->exists());

            $reservation = Reservation::create([
                'reference'          => $ref,
                'client_id'          => $quote->client_id,
                'commercial_user_id' => $quote->commercial_user_id,
                'user_id'            => $quote->commercial_user_id,
                'quote_id'           => $quote->id,
                'type'               => 'ferme',
                'status'             => ReservationStatus::CONFIRME->value,
                'start_date'         => $periodStart,
                'end_date'           => $periodEnd,
                'total_amount'       => $quote->total_a_payer,
                'confirmed_at'       => now(),
                'origin'             => 'devis',
                'notes'              => 'Créée automatiquement à partir du devis ' . $quote->reference,
            ]);

            // Attach panneaux avec prix négocié
            foreach ($quote->lines as $line) {
                if ($line->panel_id) {
                    $reservation->panels()->attach($line->panel_id, [
                        'unit_price' => $line->pu_ht_mensuel,
                        'quantity'   => $line->quantite,
                    ]);
                }
                if ($line->external_panel_id) {
                    $reservation->externalPanels()->attach($line->external_panel_id, [
                        'unit_price' => $line->pu_ht_mensuel,
                        'quantity'   => $line->quantite,
                    ]);
                }
            }

            // Sync statuts panneaux (bloqués maintenant)
            if (!empty($panelIds))    $this->availability->syncPanelStatuses($panelIds);
            if (!empty($extPanelIds)) $this->availability->syncExternalPanelStatuses($extPanelIds);

            // Marquer le devis converti
            $quote->update([
                'status'                    => QuoteStatus::ACCEPTE->value,
                'decision_at'               => now(),
                'converted_reservation_id'  => $reservation->id,
            ]);

            AlertService::create('devis', 'info',
                '✅ Devis accepté — ' . $quote->reference,
                ($quote->client?->name ?? 'Client') . ' a accepté le devis ' . $quote->reference
                . '. Réservation ' . $reservation->reference . ' créée automatiquement.',
                $quote
            );

            Log::info('quote.convert.success', [
                'quote_id'       => $quote->id,
                'reservation_id' => $reservation->id,
                'decided_by'     => $decidedBy,
            ]);

            return ['status' => 'converted', 'reservation' => $reservation];
        });
    }
}
