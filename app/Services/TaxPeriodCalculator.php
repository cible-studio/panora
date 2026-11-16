<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * TaxPeriodCalculator — helpers pour calculer les unités de facturation
 * TM (mois de date à date) et ODP (mois calendaires touchés).
 *
 * ⚠ TX-14 (2026-09-24) : l'ODP se compte en MOIS. Les méthodes
 *   trimestres* restent présentes (rien ne les casse) mais ne portent
 *   plus la règle en vigueur — cf. moisODPDansPeriode().
 *
 * ═══ Règles métier (validées par écrit le 2026-07-29) ═══
 *
 * TM (Taxe Municipale)
 * --------------------
 * Facturée SEULEMENT si une campagne est active sur le panneau.
 * L'unité est le "mois de date à date" ENTAMÉ STRICTEMENT.
 *
 *   Mois facturés = 1 si date_fin ≤ (date_début + 1 mois)
 *   Sinon +1 pour chaque anniversaire mensuel dépassé strictement.
 *
 * Exemples validés par la patronne :
 *   01/03 → 05/03  = 1 mois  (anniv 01/04, fin < anniv)
 *   16/03 → 16/04  = 1 mois  (anniv 16/04, fin = anniv, pas strictement dépassé)
 *   15/03 → 30/04  = 2 mois  (anniv 15/04, fin > anniv)
 *   05/02 → 05/03  = 1 mois  (anniv 05/03, fin = anniv)
 *   05/02 → 07/03  = 2 mois  (anniv 05/03, fin > anniv)
 *   15/01 → 15/04  = 3 mois  (anniv1 15/02, anniv2 15/03, anniv3 15/04 = fin)
 *
 * ODP (Occupation Domaine Public)
 * -------------------------------
 * Facturée dès que le panneau EXISTE physiquement sur le sol ivoirien,
 * qu'il soit occupé par une campagne ou pas. Facturation trimestrielle
 * calendaire : T1 (Jan-Mar), T2 (Avr-Jun), T3 (Juil-Sep), T4 (Oct-Déc).
 *
 *   [OBSOLÈTE depuis TX-14 — conservé pour mémoire]
 *   Trimestres facturés = nombre de trimestres calendaires TOUCHÉS par
 *                         [existence_panneau] ∩ [période_filtre]
 *   1 seul jour du panneau dans un trimestre = trimestre entier compté.
 *
 * [OBSOLÈTE] Le tarif trimestriel = tarif_mensuel_stocké × 3 (le tarif stocké dans
 * communes.odp_rate reste en FCFA/m²/mois, on convertit à la volée).
 */
class TaxPeriodCalculator
{
    /**
     * Compte les mois de facturation TM entre deux dates.
     *
     * Règle "mois de date à date entamé strictement".
     * min 1 (si les dates sont valides et start ≤ end).
     */
    public function moisAnniversaireEntames(CarbonInterface $start, CarbonInterface $end): int
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        // Cas dégénéré : dates inversées → 0.
        if ($e->lessThan($s)) return 0;

        $mois = 1;
        $anniversaire = $s->copy()->addMonthNoOverflow();

        while ($e->greaterThan($anniversaire)) {
            $mois++;
            $anniversaire->addMonthNoOverflow();
        }

        return $mois;
    }

    /**
     * Compte le nombre de trimestres calendaires touchés par [start, end].
     * "1 seul jour dans un trimestre suffit à le compter en entier."
     *
     * Trimestres calendaires (année civile) :
     *   T1 = janvier-février-mars
     *   T2 = avril-mai-juin
     *   T3 = juillet-août-septembre
     *   T4 = octobre-novembre-décembre
     */
    public function trimestresCalendairesTouches(CarbonInterface $start, CarbonInterface $end): int
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        if ($e->lessThan($s)) return 0;

        // "Ancre" au 1er jour du trimestre de start (soit le 1er janvier,
        // 1er avril, 1er juillet ou 1er octobre). On avance de trimestre
        // en trimestre tant qu'on n'a pas dépassé la date de fin.
        $ancre = $s->copy()->firstOfQuarter();
        $trimestres = 0;

        while ($ancre->lessThanOrEqualTo($e)) {
            $trimestres++;
            $ancre->addMonthsNoOverflow(3);
        }

        return $trimestres;
    }

    /**
     * Renvoie l'intersection [start, end] avec [periodStart, periodEnd].
     * Si aucune intersection → [null, null].
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function intersection(
        CarbonInterface $start,
        CarbonInterface $end,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): array {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();
        $ps = Carbon::parse($periodStart)->startOfDay();
        $pe = Carbon::parse($periodEnd)->startOfDay();

        $debut = $s->greaterThan($ps) ? $s : $ps;
        $fin   = $e->lessThan($pe)    ? $e : $pe;

        if ($fin->lessThan($debut)) return [null, null];
        return [$debut, $fin];
    }

    /**
     * Compte les mois de facturation TM pour une campagne, restreints à
     * une période filtre (ex. semestre S1, trimestre T3). Utile pour la
     * vue théorique admin/taxes.
     *
     * Retourne 0 si la campagne ne recouvre pas la période.
     */
    public function moisTMDansPeriode(
        CarbonInterface $campaignStart,
        CarbonInterface $campaignEnd,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): int {
        [$d, $f] = $this->intersection($campaignStart, $campaignEnd, $periodStart, $periodEnd);
        if (!$d || !$f) return 0;
        return $this->moisAnniversaireEntames($d, $f);
    }

    /**
     * TM — mois de campagne qui COMMENCENT dans la période (2026-11-16).
     *
     * ═══ RÈGLE VALIDÉE PAR ÉCRIT — 2026-11-16 ═══
     * « TM : toutes les campagnes d'un panneau sont comptées, et chaque mois
     *   de campagne va dans le trimestre où il commence. »
     *
     * La campagne se découpe en mois de date à date depuis SON début — la
     * règle de moisAnniversaireEntames(), validée le 2026-07-29 (« mois
     * entamés strictement depuis le début de la campagne »). Chaque mois est
     * ensuite rangé dans la période où il commence.
     *
     * Pourquoi pas moisTMDansPeriode() : elle recoupe d'abord la campagne
     * avec la période, puis recompte les mois à partir du 1er jour de la
     * période. Les périodes ne s'additionnaient donc pas : une campagne du
     * 15/03 au 14/05 (2 mois) faisait 1 mois en mars + 1 en avril + 1 en mai
     * = 3 en mensuel, et 2 en annuel. Ici : mars 1, avril 1, mai 0 → 2,
     * comme l'année. T1 + T2 + T3 + T4 = l'année, à l'unité près.
     *
     *   15/03 → 14/05 : mois 1 commence le 15/03 (T1), mois 2 le 15/04 (T2)
     *   01/01 → 31/12 : 12 mois, un par mois calendaire
     */
    public function moisTMCommencantDansPeriode(
        CarbonInterface $campaignStart,
        CarbonInterface $campaignEnd,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): int {
        $ps = Carbon::parse($periodStart)->startOfDay();
        $pe = Carbon::parse($periodEnd)->startOfDay();

        $n = 0;
        foreach ($this->debutsMoisTM($campaignStart, $campaignEnd) as $debut) {
            if ($debut->greaterThanOrEqualTo($ps) && $debut->lessThanOrEqualTo($pe)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Date de début de chaque mois de campagne, dans l'ordre. Même pas que
     * moisAnniversaireEntames() — même nombre d'éléments, garanti par test :
     * le 1er mois commence au début de la campagne, chaque suivant à
     * l'anniversaire que la fin dépasse strictement.
     *
     * @return array<int, Carbon>
     */
    public function debutsMoisTM(CarbonInterface $campaignStart, CarbonInterface $campaignEnd): array
    {
        $s = Carbon::parse($campaignStart)->startOfDay();
        $e = Carbon::parse($campaignEnd)->startOfDay();
        if ($e->lessThan($s)) return [];

        $debuts = [$s->copy()];
        $anniversaire = $s->copy()->addMonthNoOverflow();
        while ($e->greaterThan($anniversaire)) {
            $debuts[] = $anniversaire->copy();
            $anniversaire->addMonthNoOverflow();
        }

        return $debuts;
    }

    /**
     * Compte les trimestres ODP pour un panneau, restreints à une période
     * filtre. Utilisé pour la vue théorique et les rapports.
     *
     * Retourne 0 si le panneau n'existait pas pendant la période.
     */
    public function trimestresODPDansPeriode(
        CarbonInterface $panelCreatedAt,
        ?CarbonInterface $panelDeletedAt,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): int {
        $endEffective = $panelDeletedAt ? Carbon::parse($panelDeletedAt) : Carbon::parse($periodEnd);
        [$d, $f] = $this->intersection($panelCreatedAt, $endEffective, $periodStart, $periodEnd);
        if (!$d || !$f) return 0;
        return $this->trimestresCalendairesTouches($d, $f);
    }

    // ══════════════════════════════════════════════════════════════
    //  TX-14 (2026-09-24) — L'ODP se compte en MOIS, pas en trimestres.
    //
    //  Source : le fichier de déclaration réel de CIBLE,
    //  `docs/ODP 2024 SAN PEDRO.xlsx` (daté du 13/12/2024). Ses formules
    //  sont saisies en dur et disent, colonne « NB MOIS » à l'appui :
    //      = surface × 12 × quantité × tarif_mensuel
    //  soit 12 MOIS pour une année pleine. Aucune notion de trimestre
    //  n'apparaît dans le document.
    //
    //  Le paiement, lui, se fait chaque trimestre : 4 versements couvrant
    //  3 mois chacun. C'est une cadence de règlement, pas un forfait.
    // ══════════════════════════════════════════════════════════════

    /**
     * Compte les mois calendaires TOUCHÉS par [start, end].
     *
     * Même logique que trimestresCalendairesTouches(), au mois près :
     * un seul jour dans un mois suffit à compter le mois entier — le
     * panneau occupe le domaine public ce mois-là.
     *
     *   01/01 → 31/12  = 12 mois
     *   15/03 → 02/04  = 2 mois (mars + avril)
     *   05/06 → 20/06  = 1 mois
     */
    public function moisCalendairesTouches(CarbonInterface $start, CarbonInterface $end): int
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        if ($e->lessThan($s)) return 0;

        // Ancre au 1er du mois de start, puis on avance mois par mois.
        // addMonthsNoOverflow évite le saut du 31 janvier au 3 mars.
        $ancre = $s->copy()->startOfMonth();
        $mois  = 0;

        while ($ancre->lessThanOrEqualTo($e)) {
            $mois++;
            $ancre->addMonthsNoOverflow(1);
        }

        return $mois;
    }

    /**
     * Compte les mois ODP d'un panneau, restreints à une période filtre.
     * Retourne 0 si le panneau n'occupait pas le sol pendant la période.
     *
     * Pendant de trimestresODPDansPeriode(), qui reste en place pour ne
     * rien casser mais n'est plus la règle ODP en vigueur.
     */
    public function moisODPDansPeriode(
        CarbonInterface $panelStart,
        ?CarbonInterface $panelDeletedAt,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): int {
        $endEffective = $panelDeletedAt ? Carbon::parse($panelDeletedAt) : Carbon::parse($periodEnd);
        [$d, $f] = $this->intersection($panelStart, $endEffective, $periodStart, $periodEnd);
        if (!$d || !$f) return 0;
        return $this->moisCalendairesTouches($d, $f);
    }
}
