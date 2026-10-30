<?php

namespace Tests\Unit;

use App\Exceptions\BookingConflictException;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\PropositionService;
use App\Services\ReservationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Panneau déjà pris FERMEMENT (règle validée le 2026-10-30) : confirmer une
 * réservation, accepter une proposition ou importer une campagne est refusé
 * si une réservation confirmée ou une campagne planifiée / active / en pause
 * d'un autre dossier tient le panneau sur la période. Une option ne bloque pas.
 *
 * Base : P-100 tenu par la campagne « Lancement » (MOOV, importée sans
 * réservation), P-101 par la réservation confirmée RES-ORANGE, P-102 par une
 * simple option. La réservation RES-ESSAI (en attente) veut les trois.
 */
class DisponibiliteFermeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('status')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('client_id'); $t->string('status');
            $t->string('type')->nullable(); $t->timestamp('confirmed_at')->nullable();
            $t->date('start_date'); $t->date('end_date'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservation_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reservation_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('source')->default('interne');
            $t->unsignedBigInteger('unit_price')->default(0); $t->unsignedBigInteger('total_price')->default(0); $t->date('panel_start_date')->nullable(); $t->timestamps();
        });
        Schema::create('external_panels', function (Blueprint $t) {
            $t->id(); $t->string('reference')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('reservation_id')->nullable();
            $t->string('status'); $t->date('start_date'); $t->date('end_date'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });

        DB::table('clients')->insert([['id' => 1, 'name' => 'ESSAI'], ['id' => 2, 'name' => 'MOOV'], ['id' => 3, 'name' => 'ORANGE CI']]);
        DB::table('panels')->insert([['id' => 100, 'reference' => 'P-100'], ['id' => 101, 'reference' => 'P-101'], ['id' => 102, 'reference' => 'P-102']]);
        DB::table('campaigns')->insert(['id' => 50, 'name' => 'Lancement', 'client_id' => 2, 'status' => 'actif', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        DB::table('campaign_panels')->insert(['campaign_id' => 50, 'panel_id' => 100]);
        DB::table('reservations')->insert([
            ['id' => 60, 'reference' => 'RES-ORANGE', 'client_id' => 3, 'status' => 'confirme', 'start_date' => '2026-11-15', 'end_date' => '2026-12-15'],
            ['id' => 61, 'reference' => 'RES-OPTION', 'client_id' => 3, 'status' => 'en_attente', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30'],
            ['id' => 70, 'reference' => 'RES-ESSAI', 'client_id' => 1, 'status' => 'en_attente', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20'],
        ]);
        DB::table('reservation_panels')->insert([
            ['reservation_id' => 60, 'panel_id' => 101], ['reservation_id' => 61, 'panel_id' => 102],
            ['reservation_id' => 70, 'panel_id' => 100], ['reservation_id' => 70, 'panel_id' => 101], ['reservation_id' => 70, 'panel_id' => 102],
        ]);
    }

    private function dispo(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    public function test_campagne_et_reservation_confirmee_bloquent_pas_l_option(): void
    {
        $conflits = collect($this->dispo()->conflitsFermes([100, 101, 102], '2026-11-10', '2026-11-20', 70))->keyBy('panel_id');

        $this->assertSame([100, 101], $conflits->keys()->sort()->values()->all());
        $this->assertSame(['campaign', 'Lancement — MOOV'], [$conflits[100]['blocking_source'], $conflits[100]['blocking_ref']]);
        $this->assertSame(['reservation', 'RES-ORANGE — ORANGE CI', '16/12/2026'], [$conflits[101]['blocking_source'], $conflits[101]['blocking_ref'], $conflits[101]['next_free']]);
        $this->assertSame(
            'Panneaux déjà pris sur cette période : P-100 (campagne Lancement — MOOV), P-101 (réservation RES-ORANGE — ORANGE CI).',
            AvailabilityService::messageConflitsFermes($conflits->sortKeys()->values()->all())
        );
    }

    public function test_ni_soi_meme_ni_un_dossier_supprime_ou_hors_periode(): void
    {
        // La campagne liée à la réservation exclue ne compte pas (pas de conflit avec soi-même).
        DB::table('campaigns')->where('id', 50)->update(['reservation_id' => 70]);
        $this->assertSame([101], array_column($this->dispo()->conflitsFermes([100, 101], '2026-11-10', '2026-11-20', 70, 50), 'panel_id'));

        // Campagne à la corbeille ou terminée : le panneau est libre.
        DB::table('campaigns')->where('id', 50)->update(['reservation_id' => null, 'deleted_at' => now()]);
        $this->assertSame([], $this->dispo()->conflitsFermes([100], '2026-11-10', '2026-11-20'));
        DB::table('campaigns')->where('id', 50)->update(['deleted_at' => null, 'status' => 'termine']);
        $this->assertSame([], $this->dispo()->conflitsFermes([100], '2026-11-10', '2026-11-20'));

        // Hors période (fin inclusive : le 30/11 compte encore).
        DB::table('campaigns')->where('id', 50)->update(['status' => 'actif']);
        $this->assertSame([], $this->dispo()->conflitsFermes([100], '2026-12-01', '2026-12-10'));
        $this->assertCount(1, $this->dispo()->conflitsFermes([100], '2026-11-30', '2026-12-10'));
    }

    public function test_confirmer_une_reservation_est_refuse(): void
    {
        $resa = Reservation::findOrFail(70);
        try {
            app(ReservationService::class)->changeStatus($resa, 'confirme');
            $this->fail('Confirmation acceptée sur des panneaux déjà pris.');
        } catch (BookingConflictException $e) {
            $this->assertStringContainsString('P-100 (campagne Lancement — MOOV)', $e->getMessage());
        }
        $this->assertSame('en_attente', DB::table('reservations')->where('id', 70)->value('status'));
    }

    public function test_accepter_une_proposition_est_refuse_meme_contre_une_campagne_seule(): void
    {
        // Seul P-100 (campagne importée, sans réservation) : l'ancien contrôle le laissait passer.
        DB::table('reservation_panels')->where('reservation_id', 70)->where('panel_id', '!=', 100)->delete();

        $this->expectException(BookingConflictException::class);
        try {
            app(PropositionService::class)->confirmer(Reservation::findOrFail(70));
        } finally {
            $this->assertSame('en_attente', DB::table('reservations')->where('id', 70)->value('status'));
        }
    }
}
