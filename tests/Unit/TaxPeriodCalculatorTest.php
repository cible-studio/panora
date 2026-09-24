<?php

namespace Tests\Unit;

use App\Services\TaxPeriodCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du helper TaxPeriodCalculator.
 * Règles métier validées par écrit par la patronne le 2026-07-29.
 */
class TaxPeriodCalculatorTest extends TestCase
{
    protected TaxPeriodCalculator $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new TaxPeriodCalculator();
    }

    // ═══════════════════ TM · Mois anniversaire entamés ═══════════════════

    /** @test */
    public function tm_courte_campagne_5_jours_egale_1_mois()
    {
        // 01/03 → 05/03 = 1 mois (anniv 01/04, fin < anniv)
        $this->assertEquals(1, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 3, 1),
            Carbon::create(2026, 3, 5)
        ));
    }

    /** @test */
    public function tm_campagne_pile_1_mois_de_date_a_date_egale_1_mois()
    {
        // 16/03 → 16/04 = 1 mois (fin = anniversaire, PAS strictement dépassé)
        $this->assertEquals(1, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 3, 16),
            Carbon::create(2026, 4, 16)
        ));
    }

    /** @test */
    public function tm_campagne_1_mois_plus_1_jour_egale_2_mois()
    {
        // 16/03 → 17/04 = 2 mois (fin > anniv 16/04)
        $this->assertEquals(2, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 3, 16),
            Carbon::create(2026, 4, 17)
        ));
    }

    /** @test */
    public function tm_15_mars_30_avril_egale_2_mois()
    {
        // 15/03 → 30/04 = 2 mois (anniv 15/04, fin > anniv)
        $this->assertEquals(2, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 3, 15),
            Carbon::create(2026, 4, 30)
        ));
    }

    /** @test */
    public function tm_fevrier_court_5fev_5mars_egale_1_mois()
    {
        // 05/02 → 05/03 = 1 mois (anniv 05/03, fin = anniv)
        $this->assertEquals(1, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 2, 5),
            Carbon::create(2026, 3, 5)
        ));
    }

    /** @test */
    public function tm_fevrier_court_5fev_7mars_egale_2_mois()
    {
        // 05/02 → 07/03 = 2 mois (anniv 05/03, fin > anniv)
        $this->assertEquals(2, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 2, 5),
            Carbon::create(2026, 3, 7)
        ));
    }

    /** @test */
    public function tm_trimestre_15jan_15avr_egale_3_mois()
    {
        // 15/01 → 15/04 = 3 mois anniversaires (15/02, 15/03, 15/04)
        $this->assertEquals(3, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 1, 15),
            Carbon::create(2026, 4, 15)
        ));
    }

    /** @test */
    public function tm_meme_jour_debut_fin_egale_1_mois()
    {
        // 15/06 → 15/06 = 1 mois (une campagne d'1 jour = mini 1 mois)
        $this->assertEquals(1, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 6, 15),
            Carbon::create(2026, 6, 15)
        ));
    }

    /** @test */
    public function tm_fin_avant_debut_egale_0()
    {
        // Cas dégénéré : fin < début → 0 (pas de facturation)
        $this->assertEquals(0, $this->c->moisAnniversaireEntames(
            Carbon::create(2026, 4, 15),
            Carbon::create(2026, 3, 10)
        ));
    }

    // ═══════════════════ ODP · Trimestres calendaires touchés ═══════════════════

    /** @test */
    public function odp_1_jour_dans_t1_egale_1_trimestre()
    {
        // 15/02 → 15/02 = seulement dans T1 → 1 trimestre
        $this->assertEquals(1, $this->c->trimestresCalendairesTouches(
            Carbon::create(2026, 2, 15),
            Carbon::create(2026, 2, 15)
        ));
    }

    /** @test */
    public function odp_trimestre_complet_t1_egale_1_trimestre()
    {
        // 01/01 → 31/03 = T1 pile → 1 trimestre
        $this->assertEquals(1, $this->c->trimestresCalendairesTouches(
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 3, 31)
        ));
    }

    /** @test */
    public function odp_chevauchement_t1_t2_egale_2_trimestres()
    {
        // 25/03 → 05/04 (1 jour dans T1 + 5 jours dans T2) → 2 trimestres
        $this->assertEquals(2, $this->c->trimestresCalendairesTouches(
            Carbon::create(2026, 3, 25),
            Carbon::create(2026, 4, 5)
        ));
    }

    /** @test */
    public function odp_annee_complete_egale_4_trimestres()
    {
        // 01/01 → 31/12 = 4 trimestres
        $this->assertEquals(4, $this->c->trimestresCalendairesTouches(
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 12, 31)
        ));
    }

    /** @test */
    public function odp_creation_15mars_annee_complete_egale_4_trimestres()
    {
        // Panneau créé le 15/03, existe jusqu'au 31/12 → touche T1, T2, T3, T4
        $this->assertEquals(4, $this->c->trimestresCalendairesTouches(
            Carbon::create(2026, 3, 15),
            Carbon::create(2026, 12, 31)
        ));
    }

    // ═══════════════════ Méthodes composites (avec période filtre) ═══════════════════

    /** @test */
    public function tm_dans_periode_semestre_s1()
    {
        // Campagne 16/03 → 16/04 (1 mois) filtre semestre S1 = 01/01 → 30/06
        // Intersection = 16/03 → 16/04 (entièrement dans S1) → 1 mois
        $this->assertEquals(1, $this->c->moisTMDansPeriode(
            Carbon::create(2026, 3, 16),
            Carbon::create(2026, 4, 16),
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }

    /** @test */
    public function tm_dans_periode_debordement_hors_filtre()
    {
        // Campagne 01/06 → 31/08 (3 mois) filtre semestre S1 = 01/01 → 30/06
        // Intersection = 01/06 → 30/06 → 1 mois (juin seulement)
        $this->assertEquals(1, $this->c->moisTMDansPeriode(
            Carbon::create(2026, 6, 1),
            Carbon::create(2026, 8, 31),
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }

    /** @test */
    public function tm_hors_periode_egale_0()
    {
        // Campagne 15/07 → 30/07, filtre S1 = 01/01 → 30/06 → 0 mois TM
        $this->assertEquals(0, $this->c->moisTMDansPeriode(
            Carbon::create(2026, 7, 15),
            Carbon::create(2026, 7, 30),
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }

    /** @test */
    public function odp_dans_periode_semestre()
    {
        // Panneau créé 01/01, jamais démonté, filtre S1 = T1 + T2 → 2 trimestres
        $this->assertEquals(2, $this->c->trimestresODPDansPeriode(
            Carbon::create(2026, 1, 1),
            null,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }

    /** @test */
    public function odp_panneau_demonte_au_milieu_t2()
    {
        // Panneau créé 01/01, démonté 10/05, filtre S1 → T1 + T2 (10/05 dans T2) → 2
        $this->assertEquals(2, $this->c->trimestresODPDansPeriode(
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 5, 10),
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }

    // ═══════════════ ODP · Mois calendaires (règle en vigueur) ═══════════════
    //
    // TX-14 (2026-09-24). Établie sur docs/ODP 2024 SAN PEDRO.xlsx, la
    // déclaration réelle de CIBLE : « surface × 12 × quantité × tarif »,
    // colonne « NB MOIS » = 12 pour l'année. Les tests trimestres ci-dessus
    // restent verts (les méthodes existent toujours) mais ne portent plus
    // la règle appliquée.

    /** @test */
    public function odp_annee_complete_egale_12_mois()
    {
        // Le cas du fichier CIBLE : une année pleine = 12 mois.
        $this->assertEquals(12, $this->c->moisCalendairesTouches(
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 12, 31)
        ));
    }

    /** @test */
    public function odp_un_seul_jour_compte_le_mois_entier()
    {
        $this->assertEquals(1, $this->c->moisCalendairesTouches(
            Carbon::create(2026, 3, 17),
            Carbon::create(2026, 3, 17)
        ));
    }

    /** @test */
    public function odp_a_cheval_sur_deux_mois_egale_2_mois()
    {
        // 15/03 → 02/04 : mars et avril sont touchés.
        $this->assertEquals(2, $this->c->moisCalendairesTouches(
            Carbon::create(2026, 3, 15),
            Carbon::create(2026, 4, 2)
        ));
    }

    /** @test */
    public function odp_fin_de_mois_ne_deborde_pas_sur_le_suivant()
    {
        // Garde-fou addMonthsNoOverflow : 31/01 → 31/01 ne doit pas
        // sauter en mars (31 février n'existe pas).
        $this->assertEquals(1, $this->c->moisCalendairesTouches(
            Carbon::create(2026, 1, 31),
            Carbon::create(2026, 1, 31)
        ));
        $this->assertEquals(2, $this->c->moisCalendairesTouches(
            Carbon::create(2026, 1, 31),
            Carbon::create(2026, 2, 28)
        ));
    }

    /** @test */
    public function odp_fin_avant_debut_egale_0()
    {
        $this->assertEquals(0, $this->c->moisCalendairesTouches(
            Carbon::create(2026, 5, 10),
            Carbon::create(2026, 5, 1)
        ));
    }

    /** @test */
    public function odp_mois_dans_periode_panneau_pose_en_cours_dannee()
    {
        // Panneau posé le 15/06, filtre année pleine → juin..décembre = 7 mois.
        $this->assertEquals(7, $this->c->moisODPDansPeriode(
            Carbon::create(2026, 6, 15),
            null,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 12, 31)
        ));
    }

    /** @test */
    public function odp_mois_dans_periode_panneau_demonte()
    {
        // Panneau démonté le 10/05, filtre S1 → janvier..mai = 5 mois.
        $this->assertEquals(5, $this->c->moisODPDansPeriode(
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 5, 10),
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }

    /** @test */
    public function odp_mois_dans_periode_panneau_hors_periode_egale_0()
    {
        // Panneau posé en novembre, filtre S1 → aucun mois.
        $this->assertEquals(0, $this->c->moisODPDansPeriode(
            Carbon::create(2026, 11, 1),
            null,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 6, 30)
        ));
    }
}
