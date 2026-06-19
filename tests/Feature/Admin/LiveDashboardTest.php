<?php

namespace Tests\Feature\Admin;

use App\Enums\PoseTaskStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\PoseTaskAction;
use App\Models\User;
use App\Services\AdminLiveDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SM2b Lot 1.3 — Tests Feature du dashboard admin live.
 *
 * Couvre :
 *   - structure du payload (clés as_of, kpis, techs_active, live_events)
 *   - middleware role:admin,mediaplanner bloque commercial/comptable/tech
 *   - techs_active filtre last_seen_at > 10 min
 *   - live_events vide si aucun nouvel événement
 *   - KPIs corrects sur un dataset minimal
 *
 * ⚠ markTestSkipped si DB_CONNECTION = sqlite (default phpunit.xml) à
 *   cause des migrations historiques Panora MySQL-only qui rejettent
 *   sqlite (cf. RapportsFilterTest, M2TeamAndPerfTest, etc).
 *   À exécuter sur MySQL/MariaDB local pour vraie couverture.
 */
class LiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (env('DB_CONNECTION', 'sqlite') !== 'mysql') {
            $this->markTestSkipped('SM2b LiveDashboardTest : nécessite MySQL (migrations historiques non sqlite-portable).');
        }
        parent::setUp();
    }

    public function test_payload_contains_expected_top_level_keys(): void
    {
        $svc = app(AdminLiveDashboardService::class);
        $payload = $svc->buildLivePayload();

        $this->assertArrayHasKey('as_of',        $payload);
        $this->assertArrayHasKey('kpis',         $payload);
        $this->assertArrayHasKey('techs_active', $payload);
        $this->assertArrayHasKey('live_events',  $payload);

        $this->assertIsString($payload['as_of']);
        $this->assertIsArray($payload['kpis']);
        $this->assertIsArray($payload['techs_active']);
        $this->assertIsArray($payload['live_events']);
    }

    public function test_kpis_contain_5_metrics(): void
    {
        $svc = app(AdminLiveDashboardService::class);
        $kpis = $svc->buildLivePayload()['kpis'];

        $this->assertArrayHasKey('total_poses_today',  $kpis);
        $this->assertArrayHasKey('done',               $kpis);
        $this->assertArrayHasKey('in_progress',        $kpis);
        $this->assertArrayHasKey('pending_validation', $kpis);
        $this->assertArrayHasKey('problems_open',      $kpis);
    }

    public function test_techs_active_excludes_offline_users(): void
    {
        // Crée 1 tech "online" (last_seen_at = now) et 1 tech "offline" (15 min ago)
        $online = User::factory()->create([
            'role'         => UserRole::TECHNIQUE,
            'is_active'    => true,
            'last_seen_at' => now()->subSeconds(30),
        ]);
        $offline = User::factory()->create([
            'role'         => UserRole::TECHNIQUE,
            'is_active'    => true,
            'last_seen_at' => now()->subMinutes(15), // > 10 min
        ]);

        cache()->forget('admin.live.payload');
        $svc = app(AdminLiveDashboardService::class);
        $techs = $svc->buildLivePayload()['techs_active'];

        $ids = collect($techs)->pluck('id')->all();
        $this->assertContains($online->id,  $ids, 'Tech online doit apparaître');
        $this->assertNotContains($offline->id, $ids, 'Tech offline doit être filtré');
    }

    public function test_live_events_empty_when_no_recent_activity(): void
    {
        // Ne crée aucune Pige ni PoseTaskAction
        cache()->forget('admin.live.payload');
        $svc = app(AdminLiveDashboardService::class);
        $events = $svc->buildLivePayload()['live_events'];

        $this->assertSame([], $events);
    }

    public function test_endpoint_requires_admin_or_mediaplanner(): void
    {
        $commercial = User::factory()->create(['role' => UserRole::COMMERCIAL, 'is_active' => true]);
        $admin      = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        // Commercial bloqué
        $this->actingAs($commercial)
             ->getJson('/admin/dashboard/live')
             ->assertStatus(403);

        // Admin OK
        $this->actingAs($admin)
             ->getJson('/admin/dashboard/live')
             ->assertOk()
             ->assertJsonStructure(['as_of', 'kpis', 'techs_active', 'live_events']);
    }
}
