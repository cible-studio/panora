<?php

namespace Tests\Unit;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\CommuneTaxPayment;
use App\Models\Panel;
use App\Models\PanelFormat;
use App\Models\User;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 — Tests unitaires du calcul ODP/TM.
 *
 * Couvre les 7 scénarios métier du brief + 1 scénario "CIBLE CI réel"
 * qui bloque la régression du bug ×12 (Plateau annuel = 3 690 000,
 * jamais 44 280 000).
 *
 * ⚠ markTestSkipped sur SQLite (DB par défaut de phpunit.xml) à cause
 *   des migrations historiques non sqlite-portables (cf. les autres
 *   tests Feature/Unit Panora qui font la même chose). À exécuter sur
 *   MySQL/MariaDB local pour la vraie couverture.
 */
class TaxCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaxCalculationService $service;

    protected function setUp(): void
    {
        if (env('DB_CONNECTION', 'sqlite') !== 'mysql') {
            $this->markTestSkipped('TaxCalculationServiceTest : nécessite MySQL (migrations historiques non sqlite-portable).');
        }
        parent::setUp();
        $this->service = app(TaxCalculationService::class);
    }

    // ─────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────

    protected function makeFormat(float $surface): PanelFormat
    {
        // Surface arbitraire — on choisit width=4, height = surface/4 pour
        // matcher le pattern factory (toujours width*height = surface).
        return PanelFormat::factory()->create([
            'name'    => $surface . 'm²',
            'width'   => 4.00,
            'height'  => round($surface / 4, 2),
            'surface' => $surface,
        ]);
    }

    protected function makePanel(Commune $commune, PanelFormat $format, ?Carbon $createdAt = null, ?Carbon $deletedAt = null): Panel
    {
        $panel = Panel::factory()->create([
            'commune_id' => $commune->id,
            'format_id'  => $format->id,
        ]);
        $update = [];
        if ($createdAt) $update['created_at'] = $createdAt;
        if ($update) $panel->forceFill($update)->saveQuietly();
        if ($deletedAt) {
            $panel->forceFill(['deleted_at' => $deletedAt])->saveQuietly();
        }
        return $panel->refresh();
    }

    protected function assignCampaign(Panel $panel, Carbon $start, Carbon $end, string $status = 'actif'): Campaign
    {
        $campaign = Campaign::factory()->create([
            'client_id'  => Client::factory()->create()->id,
            'start_date' => $start,
            'end_date'   => $end,
            'status'     => $status,
        ]);
        DB::table('campaign_panels')->insert([
            'campaign_id' => $campaign->id,
            'panel_id'    => $panel->id,
            'type'        => 'interne',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        return $campaign;
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 1 — ODP panneau existant toute l'année
    // ─────────────────────────────────────────────────────────────
    public function test_odp_panneau_existant_toute_lannee(): void
    {
        // Yopougon, panneau 12m², toute l'année 2026
        // Attendu : 1 000 × 12 × (12/12) = 12 000
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $this->makePanel($yopougon, $format, Carbon::create(2025, 1, 1));

        $odp = $this->service->calculODPCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(12000, $odp);
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 2 — ODP panneau démantelé fin juillet
    // ─────────────────────────────────────────────────────────────
    public function test_odp_panneau_demantele_en_juillet(): void
    {
        // Yopougon, panneau 12m², créé en 2025, démantelé fin juillet 2026
        // Attendu : 1 000 × 12 × (7/12) = 7 000
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $this->makePanel(
            $yopougon, $format,
            Carbon::create(2025, 1, 1),
            Carbon::create(2026, 7, 31)
        );

        $odp = $this->service->calculODPCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(7000, $odp);
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 3 — ODP panneau créé en mars
    // ─────────────────────────────────────────────────────────────
    public function test_odp_panneau_cree_en_mars(): void
    {
        // Yopougon, panneau 12m², créé le 15 mars 2026
        // Attendu : 1 000 × 12 × (10/12) = 10 000
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $this->makePanel($yopougon, $format, Carbon::create(2026, 3, 15));

        $odp = $this->service->calculODPCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(10000, $odp);
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 4 — TM panneau occupé 6 mois
    // ─────────────────────────────────────────────────────────────
    public function test_tm_panneau_occupe_6_mois(): void
    {
        // Yopougon, panneau 12m², occupé juin à novembre 2026
        // Attendu : 1 000 × 12 × (6/12) = 6 000
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $panel    = $this->makePanel($yopougon, $format, Carbon::create(2025, 1, 1));
        $this->assignCampaign($panel, Carbon::create(2026, 6, 1), Carbon::create(2026, 11, 30));

        $tm = $this->service->calculTMCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(6000, $tm);
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 5 — TM panneau jamais occupé
    //  Hotfix B canari BOUAFLE (commune avec 1 panneau libre depuis
    //  toujours qui affichait 1 667 FCFA en mensuel — bug TX-OCC-1).
    // ─────────────────────────────────────────────────────────────
    public function test_tm_panneau_jamais_occupe(): void
    {
        // Yopougon, panneau 12m², jamais en campagne
        // Attendu : 0
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $this->makePanel($yopougon, $format, Carbon::create(2025, 1, 1));

        $tm = $this->service->calculTMCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(0, $tm);
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 6 — Scénario Yopougon réaliste
    // ─────────────────────────────────────────────────────────────
    public function test_total_du_yopougon_scenario_realiste(): void
    {
        // Yopougon : 4 panneaux 12m² + 1 panneau 36m² = 84 m² total
        // Tous existants toute l'année 2026
        // 2 panneaux 12m² occupés 4 mois (jan-avr) → TM partielle
        // Attendu :
        //   ODP = 1 000 × 84 × (12/12) = 84 000
        //   TM  = 1 000 × (2 × 12) × (4/12) = 8 000
        //   Total = 92 000
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $f12 = $this->makeFormat(12);
        $f36 = $this->makeFormat(36);
        $cree = Carbon::create(2025, 1, 1);

        $p1 = $this->makePanel($yopougon, $f12, $cree);
        $p2 = $this->makePanel($yopougon, $f12, $cree);
        $this->makePanel($yopougon, $f12, $cree);
        $this->makePanel($yopougon, $f12, $cree);
        $this->makePanel($yopougon, $f36, $cree);

        // p1 + p2 occupés jan-avr 2026
        $this->assignCampaign($p1, Carbon::create(2026, 1, 1), Carbon::create(2026, 4, 30));
        $this->assignCampaign($p2, Carbon::create(2026, 1, 1), Carbon::create(2026, 4, 30));

        $debut = Carbon::create(2026, 1, 1);
        $fin   = Carbon::create(2026, 12, 31);

        $this->assertEquals(84000, $this->service->calculODPCommune($yopougon, $debut, $fin), 'ODP attendue 84 000');
        $this->assertEquals(8000,  $this->service->calculTMCommune($yopougon, $debut, $fin),  'TM attendue 8 000');
        $this->assertEquals(92000, $this->service->totalDuCommune($yopougon, $debut, $fin),   'Total dû attendu 92 000');
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 7 — Statut Partiel avec solde
    // ─────────────────────────────────────────────────────────────
    public function test_statut_partiel_avec_solde(): void
    {
        // Commune : 100 000 dû, 60 000 payé → partiel, solde 40 000, 60%
        // On force le dû via 1 panneau 100m² × tarif ODP 1 000 = 100 000
        $commune = Commune::factory()->create(['name' => 'TestPartiel', 'odp_rate' => 1000, 'tm_rate' => 0]);
        $f = $this->makeFormat(100);
        $this->makePanel($commune, $f, Carbon::create(2025, 1, 1));

        $debut = Carbon::create(2026, 1, 1);
        $fin   = Carbon::create(2026, 12, 31);

        // Enregistre 1 paiement de 60 000
        $admin = User::factory()->create();
        CommuneTaxPayment::create([
            'commune_id'   => $commune->id,
            'period_type'  => 'annuel',
            'period_year'  => 2026,
            'period_value' => 0,
            'odp_paye'     => 60000,
            'tm_paye'      => 0,
            'paid_at'      => Carbon::create(2026, 3, 15),
            'recorded_by'  => $admin->id,
        ]);

        $this->assertEquals('partiel', $this->service->statutCommune($commune, $debut, $fin));
        $this->assertEquals(40000,     $this->service->soldeRestant($commune, $debut, $fin));
        $this->assertEquals(60,        $this->service->tauxCouverture($commune, $debut, $fin));
    }

    // ─────────────────────────────────────────────────────────────
    //  TEST 8 — CAS RÉEL CIBLE CI : Plateau annuel = 3 690 000
    //  (bloque le retour du bug ×12 parasite TX-1)
    // ─────────────────────────────────────────────────────────────
    public function test_calcul_plateau_annuel_donne_3690000_pas_44280000(): void
    {
        // Plateau : 246 m² total, tarif ODP = 15 000 FCFA/m²/an
        // Tous existants toute l'année 2026
        // ATTENDU : 246 × 15 000 × (12/12) = 3 690 000 FCFA
        // PAS    : 44 280 000 (qui serait 246 × 15 000 × 12 = ×12 parasite)
        $plateau = Commune::factory()->create(['name' => 'Plateau', 'odp_rate' => 15000, 'tm_rate' => 1000]);

        // 246 m² total : on crée 1 format 246 et 1 panneau (le résultat
        // dépend de la surface totale, pas du nombre de panneaux).
        $format = $this->makeFormat(246);
        $this->makePanel($plateau, $format, Carbon::create(2025, 1, 1));

        $odp = $this->service->calculODPCommune(
            $plateau, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );

        $this->assertEquals(3_690_000, $odp);
        $this->assertNotEquals(44_280_000, $odp, 'Le bug ×12 est de retour !');
    }

    // ─────────────────────────────────────────────────────────────
    //  HOTFIX B (2026-06-22) — Bug TX-OCC-1 : TM ≠ 0 sur libre
    //  Le brief exige 3 sentinelles : libre / option seulement /
    //  annulée. Test 5 (libre) couvre déjà la cible BOUAFLE.
    //  Les 2 tests ci-dessous étendent à 'option' et 'annule'.
    // ─────────────────────────────────────────────────────────────

    /**
     * TEST 9 — Une campagne en statut "option" (ou tout statut ≠ actif)
     * ne doit JAMAIS compter dans le calcul d'occupation TM.
     * Sinon un panneau en simple option provisoire serait taxé alors
     * qu'aucun affichage n'a eu lieu.
     */
    public function test_tm_zero_si_reservation_option_seulement(): void
    {
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $panel    = $this->makePanel($yopougon, $format, Carbon::create(2025, 1, 1));

        // Campagne en OPTION (pas actif) sur juin-novembre 2026
        // → moisOccupationPanneau doit IGNORER cette campagne.
        $this->assignCampaign(
            $panel,
            Carbon::create(2026, 6, 1),
            Carbon::create(2026, 11, 30),
            'option'
        );

        $tm = $this->service->calculTMCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(0, $tm, 'TM doit être 0 quand le panneau n\'a qu\'une campagne en option (pas actif).');
    }

    /**
     * TEST 10 — Une campagne annulée (deleted_at non null OU statut
     * 'annule') ne doit JAMAIS compter dans le calcul d'occupation TM.
     */
    public function test_tm_zero_si_reservation_annulee(): void
    {
        $yopougon = Commune::factory()->create(['name' => 'Yopougon', 'odp_rate' => 1000, 'tm_rate' => 1000]);
        $format   = $this->makeFormat(12);
        $panel    = $this->makePanel($yopougon, $format, Carbon::create(2025, 1, 1));

        // Cas A — campagne soft-deleted (annulation logique).
        $campA = $this->assignCampaign(
            $panel,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 12, 31),
            'actif'
        );
        $campA->delete(); // soft-delete : deleted_at posé.

        // Cas B — campagne avec status='annule' (statut métier).
        $this->assignCampaign(
            $panel,
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 12, 31),
            'annule'
        );

        $tm = $this->service->calculTMCommune(
            $yopougon, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31)
        );
        $this->assertEquals(
            0,
            $tm,
            'TM doit être 0 si toutes les campagnes du panneau sont annulées ou soft-deleted.'
        );
    }
}
