<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ExternalPanel;
use App\Models\Panel;
use Carbon\Carbon;

/**
 * Détecte les divergences entre :
 *   - le montant facturé qu'on stocke (réservation liée ou campagne directe)
 *   - le montant qu'on devrait facturer pour la période RÉELLE de la campagne
 *
 * Contexte métier : quand l'admin crée/modifie une campagne avec des dates
 * différentes de celles de la réservation source (ex : résa 07/06→25/07 = 2
 * mois = 180 000, mais campagne 03/06→21/08 = 3 mois = 270 000), le système
 * ne facture plus le bon montant. On veut prévenir l'admin proactivement —
 * sans rien recalculer automatiquement (décision métier réservée à l'admin).
 *
 * Source de vérité pour le calcul attendu :
 *   - si campagne liée à une résa → Reservation::estimateAmountForDates()
 *     (respecte les unit_price négociés du pivot reservation_panels)
 *   - sinon → tarif catalogue × billableMonths(campagne)
 */
class CampaignAmountConsistency
{
    public const EPSILON = 1.0; // 1 FCFA

    /**
     * @return array{
     *   matches: bool,
     *   expected: float,
     *   stored: float,
     *   diff: float,
     *   source: string,
     *   period: array{start: string, end: string},
     *   overridden: bool
     * }|null  null si la comparaison n'a pas de sens (pas de panneau, dates manquantes)
     */
    public function check(Campaign $campaign): ?array
    {
        $campaign->loadMissing(['reservation', 'panels', 'externalPanels']);

        if (!$campaign->start_date || !$campaign->end_date) {
            return null;
        }

        $start = $campaign->start_date;
        $end   = $campaign->end_date;
        $reservation = $campaign->reservation;

        $expected = 0.0;
        if ($reservation) {
            $expected = $reservation->estimateAmountForDates($start, $end);
            $source   = 'reservation';
        } else {
            $months = $campaign->billableMonths();
            $sum = 0.0;
            foreach ($campaign->panels as $p) {
                $sum += (float) ($p->monthly_rate ?? 0);
            }
            foreach ($campaign->externalPanels as $p) {
                $sum += (float) ($p->monthly_rate ?? 0);
            }
            $expected = round($sum * $months, 2);
            $source   = 'campaign';
        }

        if ($expected <= 0) {
            return null;
        }

        // ⚠ Le "stored" doit TOUJOURS être le montant facturé de la campagne
        // (c'est ce qui apparaît sur la facture, dans la card MONTANT TOTAL).
        // Avant : on lisait $reservation->total_amount → afficher 0 quand la
        // résa technique liée n'avait pas de pivot (cas campagne directe à
        // laquelle on ajoute des panneaux). Le bandeau permanent affichait
        // alors "0 FCFA" alors que la campagne facturait bien 45 000 FCFA,
        // ce qui contredisait le flash warning + la card "Montant total".
        $stored = (float) $campaign->total_amount;

        $diff       = round($expected - $stored, 2);
        $matches    = abs($diff) < self::EPSILON;
        $overridden = $campaign->total_amount_overridden_at !== null;

        return [
            'matches'    => $matches,
            'expected'   => round($expected, 2),
            'stored'     => round($stored, 2),
            'diff'       => $diff,
            'source'     => $source,
            'period'     => [
                'start' => $start->format('Y-m-d'),
                'end'   => $end->format('Y-m-d'),
            ],
            'overridden' => $overridden,
        ];
    }

    /**
     * Message lisible à flasher en session('warning') ou afficher en bandeau.
     * Format compact pour tenir dans un toast comme dans un cartouche admin.
     */
    public function humanMessage(array $check): string
    {
        $fmt   = fn(float $n): string => number_format($n, 0, ',', ' ');
        $start = Carbon::parse($check['period']['start'])->format('d/m/Y');
        $end   = Carbon::parse($check['period']['end'])->format('d/m/Y');
        $exp   = $fmt($check['expected']);
        $sto   = $fmt($check['stored']);

        if ($check['matches']) {
            return "Montant cohérent : {$exp} FCFA facturés sur la période {$start} → {$end}.";
        }

        $sign = $check['diff'] > 0 ? '+' : '−';
        $abs  = $fmt(abs($check['diff']));
        $sourceHint = $check['source'] === 'reservation'
            ? 'Pour réaligner : ajustez la période de la réservation ou modifiez son montant.'
            : 'Pour réaligner : ajustez la période de la campagne ou modifiez les prix négociés des panneaux.';

        $overrideHint = $check['overridden']
            ? ' (un montant manuel est actuellement en vigueur — ne sera pas écrasé automatiquement)'
            : '';

        return "⚠ Écart de facturation sur la période {$start} → {$end} : "
            . "montant actuel {$sto} FCFA, montant attendu {$exp} FCFA ({$sign}{$abs} FCFA){$overrideHint}. "
            . $sourceHint;
    }
}
