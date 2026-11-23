<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Campaign;
use App\Models\Quote;

/**
 * Campagne issue d'un devis accepté (2026-11-23) — source unique de la
 * question « cette campagne est-elle encore celle du devis ? ».
 *
 * Règles validées le 2026-11-23 :
 *   1. Réservation et campagne issues d'un devis portent le HT NET des
 *      panneaux (après remise) ; le total à payer du devis est rappelé.
 *   2. La facture FNE générée depuis la campagne COPIE le devis : mêmes
 *      lignes, même remise, mêmes services avec leur TVA.
 *   3. Avec les taux ODP / TM FIGÉS sur le devis.
 *   4. Si la campagne a changé de dates ou de panneaux depuis le devis, la
 *      facture suit la campagne réelle (prix nets du devis), et le bandeau
 *      d'écart signale la différence.
 *
 * Utilisé par CampaignAmountConsistency (contrôle d'écart) et
 * InvoiceFromCampaignBuilder (facture FNE).
 */
class CampagneDevis
{
    /** Le devis accepté dont la campagne est issue (via sa réservation), ou null. */
    public function devis(Campaign $campaign): ?Quote
    {
        if (!$campaign->reservation_id) {
            return null;
        }

        return Quote::query()
            ->where('converted_reservation_id', $campaign->reservation_id)
            ->whereIn('status', [QuoteStatus::ACCEPTE->value, QuoteStatus::ACCEPTE_AVEC_CONFLIT->value])
            ->with(['lines', 'services'])
            ->first();
    }

    /**
     * La campagne a-t-elle gardé les dates ET les panneaux du devis ?
     * Panneaux du devis : le panneau de chaque ligne, ou ses pieds pour un
     * axe ; les panneaux de régie à part.
     */
    public function correspond(Campaign $campaign, Quote $quote): bool
    {
        if (!$quote->period_start || !$quote->period_end || !$campaign->start_date || !$campaign->end_date) {
            return false;
        }
        if (!$campaign->start_date->isSameDay($quote->period_start) || !$campaign->end_date->isSameDay($quote->period_end)) {
            return false;
        }

        [$internesDevis, $externesDevis] = self::panneauxDuDevis($quote);

        $campaign->loadMissing(['panels:id', 'externalPanels:id']);
        $internesCampagne = $campaign->panels->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $externesCampagne = $campaign->externalPanels->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        return $internesDevis === $internesCampagne && $externesDevis === $externesCampagne;
    }

    /**
     * Taux ODP / TM figés sur le devis, par panneau (règle 3) :
     * ['int_12' => ['odp' => 5000, 'tm' => 1000], 'ext_7' => …].
     * Pour un axe, chaque pied reprend les taux de la ligne de l'axe.
     */
    public static function tauxFiges(Quote $quote): array
    {
        $taux = [];
        foreach ($quote->lines as $l) {
            $t = ['odp' => (float) $l->odp_rate_applique, 'tm' => (float) $l->tm_rate_applique];
            foreach ($l->idsPieds() ?: array_filter([(int) $l->panel_id]) as $id) {
                $taux['int_' . $id] = $t;
            }
            if ($l->external_panel_id) {
                $taux['ext_' . (int) $l->external_panel_id] = $t;
            }
        }

        return $taux;
    }

    /** @return array{0: list<int>, 1: list<int>} panneaux internes, panneaux de régie */
    private static function panneauxDuDevis(Quote $quote): array
    {
        $internes = [];
        $externes = [];
        foreach ($quote->lines as $l) {
            foreach ($l->idsPieds() ?: array_filter([(int) $l->panel_id]) as $id) {
                $internes[] = (int) $id;
            }
            if ($l->external_panel_id) {
                $externes[] = (int) $l->external_panel_id;
            }
        }
        $internes = array_values(array_unique($internes));
        $externes = array_values(array_unique($externes));
        sort($internes);
        sort($externes);

        return [$internes, $externes];
    }
}
