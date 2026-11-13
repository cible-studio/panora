<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Taxes communales — maintenance, démantèlement et pieds Lumipub.
 *
 * ═══ RÈGLES VALIDÉES PAR ÉCRIT — 2026-11-13 ═══
 *
 * 1. MAINTENANCE — « l'ODP reste due tant que le mât occupe le sol ».
 *    Une panne ne déplante pas le mât : le panneau reste dans la liste et
 *    reste taxé. Avant, le filtre lisait `panels.status`, l'état DU JOUR :
 *    un panneau passé en maintenance aujourd'hui disparaissait de janvier,
 *    février, mars… où il était pourtant debout et occupé.
 *    La TM se régule seule : elle exige une campagne sur la période.
 *
 * 2. DÉMANTÈLEMENT — le mât quitte le sol : l'ODP s'arrête à cette date,
 *    au prorata. Panora le modélise par le soft delete (deleted_at), et le
 *    prorata existait déjà (moisExistencePanneau / moisODPDansPeriode) ;
 *    c'est `whereNull('deleted_at')` dans generateLines() qui empêchait la
 *    ligne d'apparaître. Les totaux par commune (panneauxPourCalcul, en
 *    withTrashed) la comptaient pourtant : les deux écrans ne se
 *    raccordaient pas.
 *
 * 3. LUMIPUB — une ligne par PIED, jamais la ligne d'axe (elle ne
 *    correspond à aucun mât planté : la taxer en plus de ses pieds, c'est
 *    le double comptage du 2026-11-12, un axe de 11 pieds compté 12 fois).
 *    Décoché par défaut : aucun total déjà déclaré ne bouge tout seul.
 *
 * Tests sur le code source, sans base de données : ils tournent sur le
 * poste à base vide. Les montants réels se vérifient en production
 * (docs/A_TESTER.md § 23).
 */
class TaxesMaintenanceEtLumipubTest extends TestCase
{
    private function src(string $chemin): string
    {
        return file_get_contents(__DIR__ . '/../../' . $chemin);
    }

    // ══════════════════════════════════════════════════════════════
    // 1 — MAINTENANCE
    // ══════════════════════════════════════════════════════════════

    public function test_la_maintenance_n_ecarte_plus_le_panneau_par_defaut(): void
    {
        $src = $this->src('app/Services/TaxCalculationService.php');

        $this->assertStringContainsString("if (!empty(\$filters['exclure_maintenance'])) {", $src,
            "L'exclusion doit être une option explicite, plus le défaut.");
        $this->assertStringNotContainsString("if (empty(\$filters['include_maintenance'])) {", $src,
            "L'ancien défaut « maintenance exclue » ne doit plus exister.");
    }

    public function test_le_dashboard_et_le_detail_repondent_au_meme_filtre(): void
    {
        $ctrl = $this->src('app/Http/Controllers/Admin/TaxController.php');

        // Les deux écrans doivent lire exclure_maintenance et inclure_lumipub,
        // sinon leurs totaux divergent — c'est le défaut que TX-12 corrigeait.
        $this->assertStringContainsString("\$request->boolean('exclure_maintenance')", $ctrl);
        $this->assertStringContainsString("\$request->boolean('inclure_lumipub')", $ctrl);
        $this->assertStringContainsString("'exclure_maintenance' => \$exclureMaintenance", $ctrl,
            "Le dashboard doit passer le filtre à generateLines().");
        $this->assertStringContainsString("'inclure_lumipub'     => \$inclureLumipub", $ctrl);
    }

    public function test_les_deux_ecrans_exposent_les_memes_cases(): void
    {
        $detail = $this->src('resources/views/admin/taxes/details.blade.php');
        $index  = $this->src('resources/views/admin/taxes/index.blade.php');

        $this->assertStringContainsString('name="exclure_maintenance"', $detail);
        $this->assertStringContainsString('name="inclure_lumipub"', $detail);
        $this->assertStringContainsString('id="exclure-maintenance"', $index);
        $this->assertStringContainsString('id="inclure-lumipub"', $index);

        // L'ancienne case ne doit plus traîner : elle n'a plus d'effet et
        // ferait croire que la maintenance est encore exclue par défaut.
        $this->assertStringNotContainsString('id="include-maintenance"', $index);
        $this->assertStringNotContainsString('name="include_maintenance"', $detail);
    }

    // ══════════════════════════════════════════════════════════════
    // 2 — DÉMANTÈLEMENT AU PRORATA
    // ══════════════════════════════════════════════════════════════

    public function test_la_liste_reprend_les_panneaux_demanteles(): void
    {
        $src = $this->src('app/Services/TaxCalculationService.php');

        $this->assertStringContainsString('Panel::withTrashed()', $src,
            "Sans withTrashed(), un mât démantelé disparaît de la période où il existait encore.");
        $this->assertStringContainsString("->orWhere('deleted_at', '>=', \$periodStart->toDateString())", $src,
            "On reprend les démantelés dont la vie croise encore la période.");
    }

    /**
     * Le prorata lui-même existait déjà — on vérifie qu'on ne l'a pas
     * contourné en recopiant une formule à côté.
     */
    public function test_le_prorata_reste_delegue_au_calculateur_de_periode(): void
    {
        $src = $this->src('app/Services/TaxCalculationService.php');

        $this->assertStringContainsString('moisODPDansPeriode(', $src);
        $this->assertStringContainsString('$panel->deleted_at', $src,
            "La date de démantèlement doit borner le calcul ODP.");
    }

    public function test_la_ligne_signale_le_demantelement_et_le_pied(): void
    {
        $src    = $this->src('app/Services/TaxCalculationService.php');
        $detail = $this->src('resources/views/admin/taxes/details.blade.php');

        $this->assertStringContainsString("'demantele_le'   => \$panel->deleted_at", $src);
        $this->assertStringContainsString("'est_pied'       => \$colonnesAxe", $src);
        $this->assertStringContainsString('DÉMANTELÉ', $detail);
        $this->assertStringContainsString('PIED LUMIPUB', $detail);
    }

    // ══════════════════════════════════════════════════════════════
    // 3 — LUMIPUB : UNE LIGNE PAR PIED
    // ══════════════════════════════════════════════════════════════

    /**
     * La ligne d'axe ne doit JAMAIS revenir dans le calcul, même quand on
     * coche « inclure les pieds » : axe + pieds = le double comptage du
     * 2026-11-12 (11 pieds comptés 12 fois).
     */
    public function test_la_ligne_d_axe_n_est_jamais_taxee(): void
    {
        $src = $this->src('app/Models/Panel.php');

        $this->assertStringContainsString("\$query->where('est_axe', false);", $src);
        $this->assertStringContainsString(
            "return \$inclureLumipub ? \$query : \$query->whereNull('axe_id');",
            $src,
            "Seuls les PIEDS deviennent optionnels ; la ligne d'axe reste exclue dans tous les cas."
        );
    }

    public function test_les_pieds_restent_exclus_par_defaut(): void
    {
        $src = $this->src('app/Models/Panel.php');

        $this->assertStringContainsString(
            'public function scopeAvecTaxesAutomatiques($query, bool $inclureLumipub = false)',
            $src,
            "Le défaut doit rester « exclus » : aucun total déjà déclaré ne bouge tout seul."
        );
    }

    public function test_tous_les_points_de_calcul_passent_par_le_meme_filtre(): void
    {
        // Si un seul écran oublie le filtre, axe et pieds se recomptent.
        foreach ([
            'app/Services/TaxCalculationService.php',
            'app/Services/TaxAutoService.php',
            'app/Http/Controllers/Admin/TaxController.php',
        ] as $fichier) {
            $this->assertStringContainsString('avecTaxesAutomatiques(', $this->src($fichier),
                "{$fichier} doit passer par le scope commun.");
        }
    }

    public function test_les_exports_annoncent_les_filtres_appliques(): void
    {
        $ctrl = $this->src('app/Http/Controllers/Admin/TaxController.php');

        // Un PDF remis à la mairie doit dire sur quelle base il a été bâti.
        $this->assertStringContainsString('maintenance=exclue', $ctrl);
        $this->assertStringContainsString('lumipub=inclus', $ctrl);
        $this->assertStringNotContainsString('maintenance=incluse', $ctrl,
            "L'ancien libellé décrirait le contraire du défaut actuel.");
    }
}
