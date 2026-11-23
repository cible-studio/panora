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
                // 2026-11-19 (choix 3a) — Le devis devient une OPTION : une simple
                // option concurrente ne bloque pas (le premier BC gagne).
                $unavailable = $this->availability->panneauxPrisFermement(
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

            // 4) Cas nominal : création de la réservation EN OPTION, en attente du
            //    bon de commande (2026-11-19, choix 2a — règle écrite du 13/11 :
            //    « devis accepté → les panneaux sont tenus en option »). C'est le
            //    BC reçu qui la confirmera (PropositionService::confirmerReservation).
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
                'type'               => 'option',
                'status'             => ReservationStatus::EN_ATTENTE->value,
                'start_date'         => $periodStart,
                'end_date'           => $periodEnd,
                'total_amount'       => $quote->total_a_payer,
                'confirmed_at'       => null,
                'origin'             => 'devis',
                'notes'              => 'Créée automatiquement à partir du devis ' . $quote->reference,
            ]);

            // Attach panneaux avec prix négocié.
            // 2026-11-23 — Colonnes RÉELLES de reservation_panels : unit_price,
            // total_price, source (+ panel_start_date). Il n'y a pas de colonne
            // `quantity` : son écriture faisait échouer TOUTE acceptation de
            // devis depuis le 21/07 (« Unknown column 'quantity' »).
            // total_price = PU × quantité × durée, la formule de la ligne de
            // devis (InvoiceCalculator::calculateLine, montant_ht_ligne).
            // source = 'externe' pour un panneau de régie : sinon la ligne
            // prenait la valeur par défaut 'interne'.
            foreach ($quote->lines as $line) {
                $totalLigne = fn (int $pu) => (int) round($pu * max(1, (int) $line->quantite) * max(0, (float) $line->duree_mois));
                if ($pieds = $line->idsPieds()) {
                    // Le prix de la ligne (somme des pieds, éventuellement
                    // négocié) est réparti sur ses pieds ; le reste de la
                    // division va au premier, pour que la somme tombe juste.
                    $n     = count($pieds);
                    $base  = intdiv((int) $line->pu_ht_mensuel, $n);
                    $reste = (int) $line->pu_ht_mensuel - $base * $n;
                    foreach ($pieds as $k => $piedId) {
                        $pu = $base + ($k === 0 ? $reste : 0);
                        $reservation->panels()->attach($piedId, [
                            'unit_price'  => $pu,
                            'total_price' => $totalLigne($pu),
                            'source'      => 'interne',
                        ]);
                    }
                } elseif ($line->panel_id) {
                    $reservation->panels()->attach($line->panel_id, [
                        'unit_price'  => (int) $line->pu_ht_mensuel,
                        'total_price' => $totalLigne((int) $line->pu_ht_mensuel),
                        'source'      => 'interne',
                    ]);
                }
                if ($line->external_panel_id) {
                    $reservation->externalPanels()->attach($line->external_panel_id, [
                        'unit_price'  => (int) $line->pu_ht_mensuel,
                        'total_price' => $totalLigne((int) $line->pu_ht_mensuel),
                        'source'      => 'externe',
                    ]);
                }
            }

            // ═══ L'option de renouvellement laisse la place (2026-11-16) ═══
            // Ce devis a été généré depuis un dossier de renouvellement dont
            // l'option tenait déjà ces panneaux en attente de BC. La
            // réservation issue du devis vient d'être créée : garder les deux
            // laisserait deux dossiers vivants sur le même emplacement pour
            // un seul engagement du client.
            //
            // C'est ici, et pas à la création du devis, que l'option tombe :
            // tant que le client n'a pas accepté, un devis ne bloque rien, et
            // la fermer plus tôt aurait rouvert le réseau à un autre client.
            $panelIdsOption = $this->libererOptionRemplacee($quote, $reservation);

            // Attente du bon de commande (30 j), relances J-15 / J-7 / J-2.
            app(BonDeCommandeService::class)->ouvrirAttente($reservation);

            // Sync statuts panneaux (en option maintenant)
            $aResynchroniser = array_values(array_unique(array_merge($panelIds, $panelIdsOption)));
            if (!empty($aResynchroniser)) $this->availability->syncPanelStatuses($aResynchroniser);
            if (!empty($extPanelIds))     $this->availability->syncExternalPanelStatuses($extPanelIds);

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
                . '. Réservation ' . $reservation->reference . ' créée en option : bon de commande attendu'
                . ($reservation->bc_attendu_jusqu_au ? ' jusqu\'au ' . \Carbon\Carbon::parse($reservation->bc_attendu_jusqu_au)->format('d/m/Y') : '') . '.',
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

    /**
     * Annule l'option de renouvellement que ce devis remplace (2026-11-16).
     *
     * Rien à faire dans l'immense majorité des cas : seuls les devis
     * générés depuis un dossier de renouvellement portent ce lien, posé
     * par QuoteController::rattacherOptionRemplacee().
     *
     * L'option est annulée, jamais supprimée : elle reste lisible dans
     * l'historique du client, avec la raison et la référence du devis.
     *
     * @return array<int> identifiants des panneaux à resynchroniser
     */
    private function libererOptionRemplacee(Quote $quote, Reservation $remplacante): array
    {
        $option = Reservation::query()
            ->where('remplacee_par_quote_id', $quote->id)
            ->whereNull('deleted_at')
            ->whereNotIn('status', [
                ReservationStatus::ANNULE->value,
                ReservationStatus::REFUSE->value,
                ReservationStatus::TERMINE->value,
            ])
            ->first();

        if (!$option) {
            return [];
        }

        $panelIds = $option->panels()->pluck('panels.id')->all();

        $motif = 'Remplacée par le devis ' . $quote->reference
            . ' accepté par le client — réservation ' . $remplacante->reference . '.';

        $option->forceFill([
            'status' => ReservationStatus::ANNULE->value,
            'notes'  => trim((string) $option->notes . "\n" . $motif),
        ])->save();

        Log::info('quote.convert.option_renouvellement_liberee', [
            'quote_id'            => $quote->id,
            'quote_ref'           => $quote->reference,
            'option_id'           => $option->id,
            'option_ref'          => $option->reference,
            'reservation_id'      => $remplacante->id,
            'panneaux_resynchro'  => count($panelIds),
        ]);

        return $panelIds;
    }
}
