<?php

namespace Tests\Unit;

use App\Services\TaxPeriodCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * TM — chaque mois de campagne va dans la période où il commence.
 *
 * ═══ RÈGLE VALIDÉE PAR ÉCRIT — 2026-11-16 ═══
 * « TM : toutes les campagnes d'un panneau sont comptées, et chaque mois de
 *   campagne va dans le trimestre où il commence. »
 *
 * Ce que ces tests verrouillent : les périodes s'ADDITIONNENT. Douze mois
 * = quatre trimestres = l'année = le nombre de mois de la campagne
 * (moisAnniversaireEntames, règle du 2026-07-29). Avant, une campagne du
 * 15/03 au 14/05 faisait 3 mois en additionnant mars, avril et mai, et 2
 * mois sur l'année.
 */
class TaxeTmAttributionTest extends TestCase
{
    private TaxPeriodCalculator $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = new TaxPeriodCalculator();
    }

    private function mois(int $annee, int $m): array
    {
        $d = Carbon::create($annee, $m, 1)->startOfDay();
        return [$d, $d->copy()->endOfMonth()];
    }

    private function trimestre(int $annee, int $t): array
    {
        $d = Carbon::create($annee, ($t - 1) * 3 + 1, 1)->startOfDay();
        return [$d, $d->copy()->addMonths(2)->endOfMonth()];
    }

    private function annee(int $annee): array
    {
        return [Carbon::create($annee, 1, 1), Carbon::create($annee, 12, 31)];
    }

    public function test_le_cas_qui_ne_s_additionnait_pas(): void
    {
        $debut = Carbon::create(2027, 3, 15);
        $fin   = Carbon::create(2027, 5, 14);

        $this->assertSame(2, $this->p->moisAnniversaireEntames($debut, $fin), 'la campagne fait 2 mois');
        $this->assertSame(1, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->mois(2027, 3)), 'mars');
        $this->assertSame(1, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->mois(2027, 4)), 'avril');
        $this->assertSame(0, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->mois(2027, 5)), 'mai : aucun mois ne commence');
        $this->assertSame(1, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->trimestre(2027, 1)), 'T1');
        $this->assertSame(1, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->trimestre(2027, 2)), 'T2');
        $this->assertSame(2, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->annee(2027)), 'année');
    }

    public function test_une_annee_pleine_donne_un_mois_par_mois(): void
    {
        $debut = Carbon::create(2027, 1, 1);
        $fin   = Carbon::create(2027, 12, 31);
        for ($m = 1; $m <= 12; $m++) {
            $this->assertSame(1, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->mois(2027, $m)), "mois $m");
        }
        $this->assertSame(12, $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->annee(2027)));
    }

    public function test_une_campagne_a_cheval_sur_deux_annees_n_est_comptee_qu_une_fois(): void
    {
        $debut = Carbon::create(2026, 12, 15);
        $fin   = Carbon::create(2027, 2, 14);

        $total = $this->p->moisAnniversaireEntames($debut, $fin);
        $a2026 = $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->annee(2026));
        $a2027 = $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->annee(2027));

        $this->assertSame(2, $total);
        $this->assertSame(1, $a2026, 'le mois du 15/12 est en 2026');
        $this->assertSame(1, $a2027, 'le mois du 15/01 est en 2027');
        $this->assertSame($total, $a2026 + $a2027);
    }

    /**
     * Toutes sortes de campagnes, y compris un départ un 31 : la somme des
     * mois, la somme des trimestres et l'année donnent toujours le nombre de
     * mois de la campagne.
     */
    public function test_mois_trimestres_et_annee_se_raccordent_toujours(): void
    {
        $cas = [
            ['2027-01-31', '2027-03-30'], ['2027-01-31', '2027-12-31'], ['2027-02-28', '2027-03-01'],
            ['2027-03-15', '2027-03-15'], ['2027-06-10', '2027-11-09'], ['2027-06-10', '2027-11-10'],
            ['2027-04-01', '2027-06-30'], ['2027-07-29', '2027-10-02'], ['2027-10-01', '2027-12-31'],
            ['2027-05-31', '2027-08-30'], ['2027-01-01', '2027-01-01'], ['2027-09-30', '2027-12-30'],
        ];
        foreach ($cas as [$d, $f]) {
            $debut = Carbon::parse($d);
            $fin   = Carbon::parse($f);
            $attendu = $this->p->moisAnniversaireEntames($debut, $fin);

            $parMois = 0;
            for ($m = 1; $m <= 12; $m++) {
                $parMois += $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->mois(2027, $m));
            }
            $parTrim = 0;
            for ($t = 1; $t <= 4; $t++) {
                $parTrim += $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->trimestre(2027, $t));
            }
            $annee = $this->p->moisTMCommencantDansPeriode($debut, $fin, ...$this->annee(2027));

            $this->assertSame($attendu, $parMois, "$d → $f : somme des mois");
            $this->assertSame($attendu, $parTrim, "$d → $f : somme des trimestres");
            $this->assertSame($attendu, $annee,   "$d → $f : année");
            $this->assertCount($attendu, $this->p->debutsMoisTM($debut, $fin), "$d → $f : débuts de mois");
        }
    }

    public function test_dates_inversees(): void
    {
        $this->assertSame(0, $this->p->moisTMCommencantDansPeriode(
            Carbon::create(2027, 5, 1), Carbon::create(2027, 4, 1), ...$this->annee(2027)
        ));
        $this->assertSame([], $this->p->debutsMoisTM(Carbon::create(2027, 5, 1), Carbon::create(2027, 4, 1)));
    }
}
