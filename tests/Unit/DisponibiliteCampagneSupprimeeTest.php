<?php

namespace Tests\Unit;

use App\Services\AvailabilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AvailabilityService::getPanelAvailabilityData (2026-10-30) : le nom
 * d'une campagne supprimée (soft delete) ne s'affiche plus sur
 * l'occupation de sa réservation ; la réservation, elle, bloque toujours.
 */
class DisponibiliteCampagneSupprimeeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->string('status');
            $t->date('start_date'); $t->date('end_date'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id'); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('reservation_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });

        DB::table('clients')->insert(['id' => 10, 'name' => 'ORANGE CI']);
        DB::table('reservations')->insert([
            ['id' => 1, 'reference' => 'RES-0001', 'client_id' => 10, 'status' => 'confirme', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30'],
            ['id' => 2, 'reference' => 'RES-0002', 'client_id' => 10, 'status' => 'confirme', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30'],
        ]);
        DB::table('reservation_panels')->insert([
            ['reservation_id' => 1, 'panel_id' => 100],
            ['reservation_id' => 2, 'panel_id' => 101],
        ]);
    }

    private function occupations(int $panelId): array
    {
        return app(AvailabilityService::class)
            ->getPanelAvailabilityData([$panelId], '2026-11-10', '2026-11-20')
            ->get($panelId)['occupations'];
    }

    public function test_le_nom_d_une_campagne_supprimee_ne_s_affiche_pas(): void
    {
        DB::table('campaigns')->insert(['name' => 'Ancienne campagne', 'reservation_id' => 1, 'deleted_at' => now()]);

        $occupations = $this->occupations(100);

        $this->assertCount(1, $occupations); // la réservation bloque toujours
        $this->assertSame('RES-0001', $occupations[0]['reservation_ref']);
        $this->assertNull($occupations[0]['campaign_name']);
    }

    public function test_seule_la_campagne_en_vie_est_retenue(): void
    {
        DB::table('campaigns')->insert([
            ['name' => 'Ancienne campagne', 'reservation_id' => 2, 'deleted_at' => now()],
            ['name' => 'Campagne Noël', 'reservation_id' => 2, 'deleted_at' => null],
        ]);

        $occupations = $this->occupations(101);

        $this->assertCount(1, $occupations); // plus de doublon dû à la campagne supprimée
        $this->assertSame('Campagne Noël', $occupations[0]['campaign_name']);
    }
}
