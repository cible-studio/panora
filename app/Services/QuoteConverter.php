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
            // Axe Lumipub (2026-11-12) : une ligne d'axe réserve SES PIEDS
            // proposés, pas la ligne de l'axe (qui tiendrait tous les pieds
            // et échapperait au contrôle des pieds déjà pris).
            $panelIds    = $quote->lines
                ->flatMap(fn ($l) => $l->idsPieds() ?: array_filter([$l->panel_id]))
                ->unique()->values()->all();
            $extPanelIds = $quote->lines->pluck('external_panel_id')->filter()->unique()->all();

            // 2) Verrouillage pessimiste des panneaux (anti-double-booking)
            if (!empty($panelIds)) {
                \App\Models\Panel::whereIn('id', $panelIds)->lockForUpdate()->get();
            }

            // ═══ Période absente — on n'invente plus (2026-11-13) ═══
            // Avant : « aujourd'hui → aujourd'hui + durée max des lignes ».
            // Ces dates inventées ne servaient pas qu'au contrôle de
            // disponibilité : elles étaient écrites dans start_date/end_date
            // de la réservation FERME créée plus bas. Un devis sans dates
            // produisait donc un engagement contractuel sur une période que
            // personne n'avait choisie — et le client accepte seul, depuis
            // son espace ou le lien public.
            //
            // Dès qu'un panneau est engagé, on refuse et on alerte le
            // commercial. La saisie et l'envoi l'exigent déjà (QuoteController) :
            // ce verrou ne sert plus qu'aux devis créés avant cette règle.
            $engagePanneaux = !empty($panelIds) || !empty($extPanelIds);
            if ($engagePanneaux && (!$quote->period_start || !$quote->period_end)) {
                $quote->update([
                    'status'          => QuoteStatus::ACCEPTE_AVEC_CONFLIT->value,
                    'decision_at'     => now(),
                    'decision_reason' => 'Période de campagne absente du devis : '
                        . 'la réservation ne peut pas être créée sans dates.',
                ]);

                AlertService::notify(
                    'devis_converti_avec_conflit',
                    '⚠️ Devis accepté sans période — ' . $quote->reference,
                    'Le client a accepté le devis ' . $quote->reference
                    . ' mais aucune période de campagne n\'y est renseignée. Aucune réservation'
                    . ' n\'a été créée. Renseigne les dates, puis reprends la conversion.',
                    $quote,
                    [
                        'user_id' => $quote->commercial_user_id,
                        'lien'    => route('admin.quotes.show', $quote->id),
                    ]
                );

                Log::warning('quote.convert.periode_absente', [
                    'quote_id'   => $quote->id,
                    'decided_by' => $decidedBy,
                ]);

                return ['status' => 'conflict', 'motif' => 'periode_absente', 'conflicts' => []];
            }

            // 3) Double-check disponibilité sur la période visée.
            // Le fallback ne subsiste que pour un devis SANS panneau
            // (services seuls) : il ne bloque aucun emplacement.
            $periodStart = $quote->period_start?->toDateString() ?? now()->toDateString();
            $periodEnd   = $quote->period_end?->toDateString()
                ?? now()->addMonths((int) ceil($quote->lines->max('duree_mois') ?? 1))->toDateString();

            $conflicts = [];
            if (!empty($panelIds)) {
                // Son propre renouvellement confirmé ne bloque pas le client (2026-11-11).
                $unavailable = $this->availability->getUnavailablePanelIds(
                    $panelIds, $periodStart, $periodEnd, null, null,
                    $quote->client_id ? (int) $quote->client_id : null
                );
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

                AlertService::notify(
                    'devis_converti_avec_conflit',
                    '⚠️ Conflit conversion devis — ' . $quote->reference,
                    'Le client a accepté le devis ' . $quote->reference
                    . ' mais ' . count($unavailable) . ' panneau(x) ne sont plus disponibles.'
                    . ' Le commercial ' . ($quote->commercial?->name ?? '—') . ' doit ajuster manuellement.',
                    $quote,
                    [
                        'user_id' => $quote->commercial_user_id,
                        'lien'    => route('admin.quotes.show', $quote->id),
                    ]
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
                'client_contact_id'  => $quote->client_contact_id, // interlocuteur (2026-10-29)
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
                if ($pieds = $line->idsPieds()) {
                    // Le prix de la ligne (somme des pieds, éventuellement
                    // négocié) est réparti sur ses pieds ; le reste de la
                    // division va au premier, pour que la somme tombe juste.
                    $n     = count($pieds);
                    $base  = intdiv((int) $line->pu_ht_mensuel, $n);
                    $reste = (int) $line->pu_ht_mensuel - $base * $n;
                    foreach ($pieds as $k => $piedId) {
                        $reservation->panels()->attach($piedId, [
                            'unit_price' => $base + ($k === 0 ? $reste : 0),
                            'quantity'   => $line->quantite,
                        ]);
                    }
                } elseif ($line->panel_id) {
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

            AlertService::notify(
                'devis_accepte',
                '🎉 Devis accepté — ' . $quote->reference,
                ($quote->client?->name ?? 'Client') . ' a accepté le devis ' . $quote->reference
                . '. Réservation ' . $reservation->reference . ' créée automatiquement.',
                $quote,
                [
                    'user_id' => $quote->commercial_user_id,
                    'lien'    => route('admin.quotes.show', $quote->id),
                ]
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
