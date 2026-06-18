<?php

namespace Tests\Unit;

use App\Services\CaRealService;
use App\Services\FinancialDashboardService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires CaRealService — Bloc 4 (CA réel), Famille B (2026-06-18).
 *
 * Couvre la partie « pure » du service (sans hit base) :
 *   - hasIncompatibleCaFilters : matrice complète
 *   - INCOMPATIBLE_FILTER_KEYS : contient bien les 3 clés métier
 *
 * Le test de cohérence Finance ↔ Rapports (Garde-fou 3 patronne) est dans
 * tests/Feature/CaRealServiceConsistencyTest.php — il a besoin de la base.
 */
class CaRealServiceTest extends TestCase
{
    // ── hasIncompatibleCaFilters ────────────────────────────────────

    public function test_filters_vides_ne_sont_pas_incompatibles(): void
    {
        $this->assertFalse(CaRealService::hasIncompatibleCaFilters([]));
    }

    public function test_filtre_client_seul_n_est_pas_incompatible(): void
    {
        $this->assertFalse(CaRealService::hasIncompatibleCaFilters(['client_id' => 42]));
    }

    public function test_filtre_commercial_seul_n_est_pas_incompatible(): void
    {
        $this->assertFalse(CaRealService::hasIncompatibleCaFilters(['commercial_id' => 7]));
    }

    public function test_filtre_commune_est_incompatible(): void
    {
        $this->assertTrue(CaRealService::hasIncompatibleCaFilters(['commune_id' => 12]));
    }

    public function test_filtre_zone_est_incompatible(): void
    {
        $this->assertTrue(CaRealService::hasIncompatibleCaFilters(['zone' => 'abidjan']));
    }

    public function test_filtre_categorie_est_incompatible(): void
    {
        $this->assertTrue(CaRealService::hasIncompatibleCaFilters(['category_id' => 3]));
    }

    public function test_combinaison_compat_et_incompat_est_incompatible(): void
    {
        $this->assertTrue(CaRealService::hasIncompatibleCaFilters([
            'client_id'  => 42,
            'commune_id' => 12,
        ]));
    }

    public function test_valeurs_falsy_ne_declenchent_pas_incompat(): void
    {
        // Les filtres "vides" (null, '', 0) ne doivent pas être comptés
        // comme actifs — sinon le bandeau s'afficherait à tort dès qu'un
        // utilisateur a remis une option à "—".
        $this->assertFalse(CaRealService::hasIncompatibleCaFilters([
            'commune_id'  => null,
            'zone'        => '',
            'category_id' => 0,
        ]));
    }

    // ── INCOMPATIBLE_FILTER_KEYS ────────────────────────────────────

    public function test_les_3_cles_metier_sont_declarees(): void
    {
        $this->assertSame(
            ['commune_id', 'zone', 'category_id'],
            CaRealService::INCOMPATIBLE_FILTER_KEYS,
            'Les clés incompatibles doivent rester exactement ces 3 — sinon le '
            . 'bandeau d\'info côté Rapports cachera des incohérences à l\'utilisateur.'
        );
    }
}
