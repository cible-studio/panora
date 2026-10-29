<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Reservation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Estimation du montant d'une réservation pour d'autres dates (2026-10-29) :
 * même règle que la facture (Campaign::computeBillableMonths). Sert à
 * l'estimation d'un changement de dates sur la proposition et à l'alerte
 * « le montant de la campagne diffère de la réservation ».
 */
class EstimationReservationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->decimal('unit_price', 12, 2)->nullable(); $t->string('source')->default('interne');
        });
        foreach (['panels', 'external_panels'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->decimal('monthly_rate', 12, 2)->nullable(); $t->softDeletes(); $t->timestamps(); });
        }
        DB::table('reservations')->insert(['id' => 1, 'start_date' => '2026-10-29', 'end_date' => '2026-12-01']);
        DB::table('panels')->insert(['id' => 7, 'monthly_rate' => 80000]);
        DB::table('reservation_panels')->insert([
            ['reservation_id' => 1, 'panel_id' => 5, 'unit_price' => 100000, 'source' => 'interne'],
            ['reservation_id' => 1, 'panel_id' => 6, 'unit_price' => 50000, 'source' => 'interne'],
        ]);
    }

    private function estimer(string $debut, string $fin): float
    {
        return Reservation::findOrFail(1)->estimateAmountForDates($debut, $fin);
    }

    public function test_meme_regle_que_la_facture(): void
    {
        $mois = Campaign::computeBillableMonths(Carbon::parse('2026-10-29'), Carbon::parse('2026-12-01'));

        $this->assertSame(round(150000 * $mois, 2), $this->estimer('2026-10-29', '2026-12-01'));
        $this->assertSame(160000.0, $this->estimer('2026-10-29', '2026-12-01')); // 1 mois + 2 jours ; avant : 225 000 (1,5 mois)
    }

    public function test_periode_courte_demi_mois_minimum(): void
    {
        $this->assertSame(75000.0, $this->estimer('2026-11-01', '2026-11-10'));
    }

    public function test_tarif_du_catalogue_si_pas_de_prix_negocie(): void
    {
        DB::table('reservation_panels')->insert(['reservation_id' => 1, 'panel_id' => 7, 'unit_price' => 0, 'source' => 'interne']);

        $this->assertSame(round((150000 + 80000) * 1.0, 2), $this->estimer('2026-11-01', '2026-12-01'));
    }
}
