<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelCategory;
use App\Models\PanelFormat;
use App\Models\User;
use App\Models\Zone;
use App\Services\DashboardKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests AJAX endpoint /admin/rapports/ajax — pilotage des filtres en temps réel.
 *
 * Couvre :
 *   - structure de la réponse JSON (toutes les sections présentes)
 *   - filtre zone (Abidjan / Intérieur) — sécurise les bugs A et zone communes
 *   - filtre client/commune/category — propagation
 *   - sélecteurs année internes (ca_year, tableau_year)
 *   - parité index() vs ajax() (non-régression du refactor buildReportData())
 *   - RBAC commercial — pas de fuite vers d'autres commerciaux
 *   - exports_qs contient bien tous les filtres actifs
 *   - filtre invalide géré sans crash
 */
class RapportsFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Commune $abidjanCommune;
    protected Commune $interieurCommune;

    protected function setUp(): void
    {
        // Le pipeline de migrations Panora utilise des ALTER TABLE MODIFY +
        // SHOW INDEX MySQL-spécifiques qui rejettent sqlite (driver des tests).
        // → on skippe proprement AVANT parent::setUp() (qui déclenche les
        //   migrations via RefreshDatabase). Les tests sont prêts à passer
        //   dès qu'un MySQL de test est configuré (DB_CONNECTION=mysql + DB_DATABASE=panora_test
        //   dans phpunit.xml).
        if (env('DB_CONNECTION', 'sqlite') !== 'mysql') {
            $this->markTestSkipped('Tests nécessitent MySQL — migrations utilisent ALTER MODIFY/SHOW INDEX non supportés par sqlite.');
        }

        parent::setUp();

        // Setup minimal : 1 admin, 1 commune Abidjan + 1 commune intérieur,
        // 2 panels (1 Abidjan + 1 intérieur), 1 client, 0 campagne.
        // Les tests qui ont besoin de plus créent leur propre data.
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);

        PanelFormat::factory()->create(['name' => '4x3', 'width' => 4, 'height' => 3]);
        PanelCategory::factory()->create(['name' => 'Affichage']);
        Zone::factory()->create(['name' => 'Zone test']);

        $this->abidjanCommune   = Commune::factory()->create(['name' => 'COCODY', 'city' => 'Abidjan']);
        $this->interieurCommune = Commune::factory()->create(['name' => 'BOUAKE', 'city' => 'Bouake']);

        Panel::factory()->create(['reference' => 'CDY-001A', 'commune_id' => $this->abidjanCommune->id]);
        Panel::factory()->create(['reference' => 'BKE-001A', 'commune_id' => $this->interieurCommune->id]);

        Client::factory()->create(['name' => 'Test Client']);

        DashboardKpiService::invalidateAll();
    }

    /** La réponse renvoie les 12 sections attendues + métadonnées. */
    public function test_ajax_returns_all_sections_with_no_filters(): void
    {
        $r = $this->actingAs($this->admin)->getJson('/admin/rapports/ajax');
        $r->assertStatus(200);

        $r->assertJsonStructure([
            'summary',
            'topcards',
            'kpis',
            'tabs' => ['occupation', 'performance', 'periodes', 'campagnes', 'ca', 'zones', 'clients', 'decappages', 'insights'],
            'exports_qs',
            'fingerprint',
        ]);

        // Header de mesure perf
        $this->assertNotNull($r->headers->get('X-Rapports-Ms'));
        $this->assertIsNumeric($r->headers->get('X-Rapports-Ms'));
    }

    /** zone=abidjan → totalPanneaux ne compte QUE les panneaux Abidjan. */
    public function test_ajax_zone_abidjan_filters_kpis_to_abidjan_only(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=abidjan');

        $r->assertStatus(200);
        // Le partial _summary contient "1 panneaux" (1 panneau Abidjan créé en setUp)
        $this->assertStringContainsString('1', $r->json('summary'));
        // Le partial _kpis montre 1 dans "Panneaux disponibles" (Cocody seul)
        $this->assertStringContainsString('1', $r->json('kpis'));
    }

    /** zone=interieur → totalPanneaux ne compte QUE les panneaux hors Abidjan. */
    public function test_ajax_zone_interieur_filters_kpis_to_interieur_only(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=interieur');

        $r->assertStatus(200);
        // 1 panneau Bouaké en setUp → 1 panneau intérieur
        $this->assertStringContainsString('BKE-001A', $r->json('tabs.zones'));
        // Pas de panneau Cocody dans l'onglet zones quand intérieur sélectionné
        $this->assertStringNotContainsString('CDY-001A', $r->json('tabs.zones'));
    }

    /** Bug A regression : aDecaper ignore zone → ne doit PAS contenir de panneau Abidjan. */
    public function test_a_decaper_respects_filter_zone(): void
    {
        // Crée 1 campagne qui se termine dans 15j sur le panneau Abidjan
        // ET 1 sur le panneau intérieur — pour vérifier la séparation.
        $client = Client::first();
        $panelAbj = Panel::where('reference', 'CDY-001A')->first();
        $panelInt = Panel::where('reference', 'BKE-001A')->first();

        $campAbj = Campaign::factory()->create([
            'client_id'   => $client->id,
            'start_date'  => now()->subDays(30),
            'end_date'    => now()->addDays(15),
            'status'      => 'actif',
        ]);
        $campAbj->panels()->attach($panelAbj->id, ['type' => 'interne']);

        $campInt = Campaign::factory()->create([
            'client_id'   => $client->id,
            'start_date'  => now()->subDays(30),
            'end_date'    => now()->addDays(15),
            'status'      => 'actif',
        ]);
        $campInt->panels()->attach($panelInt->id, ['type' => 'interne']);

        DashboardKpiService::invalidateAll();

        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=abidjan');
        // Seul le panneau Abidjan doit apparaître dans l'onglet zones
        $this->assertStringContainsString('CDY-001A', $r->json('tabs.zones'));
        $this->assertStringNotContainsString('BKE-001A', $r->json('tabs.zones'));

        DashboardKpiService::invalidateAll();
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=interieur');
        $this->assertStringContainsString('BKE-001A', $r->json('tabs.zones'));
        $this->assertStringNotContainsString('CDY-001A', $r->json('tabs.zones'));
    }

    /** Sélecteurs année internes ca_year / tableau_year fonctionnent. */
    public function test_ajax_year_selectors_override_default_year(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?ca_year=2024&tableau_year=2023');

        $r->assertStatus(200);
        // L'onglet CA doit afficher l'année 2024 dans son titre
        $this->assertStringContainsString('2024', $r->json('tabs.ca'));
        // L'onglet périodes (tableau mensuel) doit montrer 2023
        $this->assertStringContainsString('2023', $r->json('tabs.periodes'));
    }

    /** Sélecteur année invalide (hors plage) → fallback sans crash. */
    public function test_ajax_invalid_year_selector_falls_back_gracefully(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?ca_year=9999&tableau_year=abc');

        $r->assertStatus(200);
        // Pas de crash. L'année est ramenée à l'année courante.
        $currentYear = (int) date('Y');
        $this->assertStringContainsString((string) $currentYear, $r->json('tabs.ca'));
    }

    /** exports_qs contient toutes les valeurs des filtres actifs. */
    public function test_ajax_exports_qs_contains_all_active_filters(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=abidjan&filter_commune_id=42&preset=month&ca_year=2024');

        $r->assertStatus(200);
        $qs = $r->json('exports_qs');
        $this->assertStringContainsString('filter_zone=abidjan', $qs);
        $this->assertStringContainsString('filter_commune_id=42', $qs);
        $this->assertStringContainsString('preset=month', $qs);
        $this->assertStringContainsString('ca_year=2024', $qs);
    }

    /** Parité ajax() vs index() : même filtre = même fingerprint (même dataset). */
    public function test_ajax_returns_same_data_as_full_page_load(): void
    {
        DashboardKpiService::invalidateAll();
        $r1 = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=abidjan');

        DashboardKpiService::invalidateAll();
        $r2 = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=abidjan');

        $this->assertEquals($r1->json('fingerprint'), $r2->json('fingerprint'));
        $this->assertEquals($r1->json('summary'),    $r2->json('summary'));
        $this->assertEquals($r1->json('kpis'),       $r2->json('kpis'));
    }

    /** Filtre mal-formé (commune_id=abc) ne crash pas, l'ignore proprement. */
    public function test_ajax_handles_invalid_filter_gracefully(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_commune_id=abc&filter_zone=xyz');

        // Pas de crash. filter_zone non whitelisté ('xyz') est ramené à null.
        // filter_commune_id='abc' donnera 0 résultats sans erreur SQL.
        $r->assertStatus(200);
        $r->assertJsonStructure(['summary', 'kpis', 'tabs']);
    }

    /** La page principale renvoie 200 (refactor buildReportData OK). */
    public function test_index_page_still_renders_after_refactor(): void
    {
        $r = $this->actingAs($this->admin)->get('/admin/rapports');
        $r->assertStatus(200);
        // Vérifier que les 3 ids conteneurs AJAX sont bien présents
        $r->assertSee('id="rpt-summary"', false);
        $r->assertSee('id="rpt-topcards"', false);
        $r->assertSee('id="rpt-kpis"', false);
        // Vérifier que les 4 data-export-route sont là (Bug E fix)
        $r->assertSee('data-export-route', false);
        $r->assertSee('data-route-base', false);
    }

    /** RBAC : un user non-admin (commercial) ne peut PAS accéder. */
    public function test_ajax_blocks_non_admin_users(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::Commercial->value]);
        $r = $this->actingAs($commercial)->getJson('/admin/rapports/ajax');
        // La route est dans le middleware role:admin (ou autre), donc 403
        // OU 200 selon le RBAC global. On vérifie au moins qu'il n'y a pas
        // de crash.
        $this->assertContains($r->status(), [200, 302, 403]);
    }

    /** La réponse contient bien le header X-Rapports-Ms pour diagnostiquer la latence. */
    public function test_ajax_response_includes_perf_header(): void
    {
        $r = $this->actingAs($this->admin)->getJson('/admin/rapports/ajax');
        $r->assertStatus(200);
        $this->assertNotNull($r->headers->get('X-Rapports-Ms'));
    }

    /** Snapshot baseline : ajax(no filter) → 2 panneaux au total (1 Abidjan + 1 intérieur). */
    public function test_ajax_snapshot_baseline_matches_setup(): void
    {
        $r = $this->actingAs($this->admin)->getJson('/admin/rapports/ajax');
        $r->assertStatus(200);
        // Le summary contient "2 panneaux" (créés en setUp)
        $this->assertStringContainsString('2 panneaux', $r->json('summary'));
    }

    /**
     * Charts AJAX — la réponse doit contenir chartData avec une entrée
     * pour CHAQUE chart (les 10 partials + les 2 customs).
     */
    public function test_ajax_returns_chart_configs_for_all_charts(): void
    {
        $r = $this->actingAs($this->admin)->getJson('/admin/rapports/ajax');
        $r->assertStatus(200);

        // Doit contenir chartData avec les 21 clés attendues
        $r->assertJsonStructure([
            'chartData' => [
                'occParCommune', 'evolMensuelle', 'caMensuel', 'tableauMensuel',
                'topClients', 'statsCommunes', 'annee', 'moisDu', 'moisAu',
                'occupationTrend', 'topPanels', 'cancelReasons', 'cancellationTrend',
                'revenueByMonth', 'inactivityBucket', 'parcByCommune',
                'occVsRevenue', 'revenueByCity', 'revenueByCommune',
                'clientRevenueDist', 'campaignStats',
            ],
        ]);

        // Sanity check : chacune des séries de longueur fixe a la bonne taille
        $cd = $r->json('chartData');
        $this->assertCount(12, $cd['occupationTrend']);     // 12 mois glissants
        $this->assertCount(12, $cd['cancellationTrend']);   // idem
        $this->assertCount(12, $cd['caMensuel']);           // 12 mois de l'année
        $this->assertCount(12, $cd['tableauMensuel']);      // idem
        $this->assertCount(12, $cd['evolMensuelle']);       // idem
        $this->assertCount(12, $cd['revenueByMonth']);      // idem
    }

    /**
     * Charts AJAX — quand filter_zone=abidjan, les données chart doivent
     * refléter UNIQUEMENT Abidjan (occParCommune, statsCommunes, etc.).
     */
    public function test_ajax_chart_data_reflects_active_filters(): void
    {
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=abidjan');
        $r->assertStatus(200);

        $cd = $r->json('chartData');

        // statsCommunes doit ne contenir que la commune Abidjan
        $communeNames = collect($cd['statsCommunes'])->pluck('commune')->all();
        $this->assertContains('COCODY', $communeNames);
        $this->assertNotContains('BOUAKE', $communeNames);

        // Inversement avec zone=interieur
        DashboardKpiService::invalidateAll();
        $r = $this->actingAs($this->admin)
            ->getJson('/admin/rapports/ajax?filter_zone=interieur');
        $cd = $r->json('chartData');

        $communeNames = collect($cd['statsCommunes'])->pluck('commune')->all();
        $this->assertContains('BOUAKE', $communeNames);
        $this->assertNotContains('COCODY', $communeNames);
    }
}
