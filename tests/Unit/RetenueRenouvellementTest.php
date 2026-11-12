<?php

namespace Tests\Unit;

use App\Exceptions\BookingConflictException;
use App\Http\Controllers\Admin\ReservationController;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\Diffusion\RenouvellementService;
use App\Services\ReservationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Retenue des panneaux d'un renouvellement confirmé (règle validée le
 * 2026-11-11, réponses « 1a 2a 3oui ») :
 *   · du lendemain de la fin de la campagne jusqu'à 30 jours après ;
 *   · refus pour un autre client, comme un panneau occupé ; le même client
 *     passe (« Renouveler la campagne ») ;
 *   · « Veut renouveler » : information seule, rien n'est bloqué.
 *
 * Base : la campagne annuelle de BEM (client 2) finit le 31/12/2026 sur
 * P-100 et P-101, renouvellement confirmé. P-102 : campagne de SIB (client
 * 4), qui « veut renouveler ». ORANGE (client 3) cherche des panneaux.
 */
class RetenueRenouvellementTest extends TestCase
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
        (require base_path('database/migrations/2026_10_22_100000_create_renouvellements_tables.php'))->up();
        (require base_path('database/migrations/2026_10_23_100000_add_rappel_le_to_renouvellements.php'))->up();

        Carbon::setTestNow('2026-11-11 10:00:00');
        config(['diffusion.renouvellement.retenue_jours' => 30]);

        DB::table('clients')->insert([
            ['id' => 2, 'name' => 'BEM'], ['id' => 3, 'name' => 'ORANGE CI'], ['id' => 4, 'name' => 'SIB'],
        ]);
        DB::table('panels')->insert([
            ['id' => 100, 'reference' => 'P-100'], ['id' => 101, 'reference' => 'P-101'],
            ['id' => 102, 'reference' => 'P-102'], ['id' => 103, 'reference' => 'P-103'],
        ]);
        DB::table('campaigns')->insert([
            ['id' => 50, 'name' => 'Annuel BEM', 'client_id' => 2, 'status' => 'actif', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
            ['id' => 51, 'name' => 'Annuel SIB', 'client_id' => 4, 'status' => 'actif', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
        ]);
        DB::table('campaign_panels')->insert([
            ['campaign_id' => 50, 'panel_id' => 100], ['campaign_id' => 50, 'panel_id' => 101],
            ['campaign_id' => 51, 'panel_id' => 102],
        ]);
        DB::table('renouvellements')->insert([
            ['campaign_id' => 50, 'client_id' => 2, 'fin_campagne' => '2026-12-31', 'statut' => 'confirme', 'token' => 'a'],
            ['campaign_id' => 51, 'client_id' => 4, 'fin_campagne' => '2026-12-31', 'statut' => 'renouveler', 'token' => 'b'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dispo(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    // ── La règle ──────────────────────────────────────────────────

    public function test_la_retenue_court_du_lendemain_de_la_fin_a_30_jours_apres(): void
    {
        $r = RenouvellementService::retenues([100, 101, 102, 103], '2027-01-01', '2027-01-31');

        $this->assertSame([100, 101], array_keys($r));
        $this->assertSame(['confirme', 2, 'BEM', '2027-01-01', '2027-01-30'],
            [$r[100]['statut'], $r[100]['client_id'], $r[100]['client'], $r[100]['du'], $r[100]['jusqu_au']]);
        $this->assertSame('Renouvellement confirmé · BEM', RenouvellementService::libelleRetenue($r[100]));

        // Bornes incluses : le 30/01 compte encore, le 31/01 non ; la
        // campagne elle-même (jusqu'au 31/12) n'est pas une retenue.
        $this->assertCount(2, RenouvellementService::retenues([100, 101], '2027-01-30', '2027-02-15'));
        $this->assertSame([], RenouvellementService::retenues([100, 101], '2027-01-31', '2027-02-15'));
        $this->assertSame([], RenouvellementService::retenues([100, 101], '2026-12-01', '2026-12-31'));
    }

    public function test_veut_renouveler_est_seulement_une_information(): void
    {
        $this->assertArrayNotHasKey(102, RenouvellementService::retenues([102], '2027-01-01', '2027-01-31'));

        $info = $this->dispo()->retenuesRenouvellement([102], '2027-01-01', '2027-01-31', true);
        $this->assertSame('Renouvellement demandé · SIB', RenouvellementService::libelleRetenue($info[102]));
        $this->assertSame([], $this->dispo()->getUnavailablePanelIds([102], '2027-01-01', '2027-01-31', null, null, 3));
    }

    public function test_le_catalogue_garde_sa_regle(): void
    {
        $this->assertEqualsCanonicalizing([100, 101], RenouvellementService::panneauxRetenus());

        Carbon::setTestNow('2027-01-30 10:00:00');
        $this->assertEqualsCanonicalizing([100, 101], RenouvellementService::panneauxRetenus());
        Carbon::setTestNow('2027-01-31 10:00:00');
        $this->assertSame([], RenouvellementService::panneauxRetenus());
    }

    // ── Le contrôle : un autre client refusé, le même client accepté ──

    public function test_un_autre_client_est_refuse_le_meme_client_passe(): void
    {
        $periode = ['2027-01-01', '2027-03-31'];

        $this->assertEqualsCanonicalizing([100, 101], $this->dispo()->getUnavailablePanelIds([100, 101, 103], ...$periode, ...[null, null, 3]));
        $this->assertSame([], $this->dispo()->getUnavailablePanelIds([100, 101], ...$periode, ...[null, null, 2]));
        // Client inconnu : prudence, la retenue vaut.
        $this->assertEqualsCanonicalizing([100, 101], $this->dispo()->getUnavailablePanelIds([100, 101], ...$periode));

        $conflits = $this->dispo()->conflitsFermes([100], ...$periode, ...[null, null, 3]);
        $this->assertSame(['renouvellement', 'BEM', '31/01/2027'], [$conflits[0]['blocking_source'], $conflits[0]['blocking_ref'], $conflits[0]['next_free']]);
        $this->assertSame(
            'Panneau déjà pris sur cette période : P-100 (retenu pour le renouvellement de BEM).',
            AvailabilityService::messageConflitsFermes($conflits)
        );
        $this->assertSame([], $this->dispo()->conflitsFermes([100], ...$periode, ...[null, null, 2]));
    }

    public function test_confirmation_depuis_l_ecran_decale_ou_exclut(): void
    {
        $carte = $this->dispo()->getInternalConflictMap([100, 103], '2027-01-01', '2027-03-31', null, null, 3);

        $this->assertSame([100], $carte->keys()->all());
        $this->assertSame(['renouvellement', 'BEM', '2027-01-30'],
            [$carte[100]->blocking_status, $carte[100]->conflicting_ref, $carte[100]->release_date]);
        $this->assertTrue($this->dispo()->getInternalConflictMap([100], '2027-01-01', '2027-03-31', null, null, 2)->isEmpty());

        // Une option d'ORANGE sur P-101 : la retenue prime, et sa date aussi.
        DB::table('reservations')->insert(['id' => 80, 'reference' => 'RES-OPT', 'client_id' => 3, 'status' => 'en_attente', 'start_date' => '2027-01-05', 'end_date' => '2027-01-10']);
        DB::table('reservation_panels')->insert(['reservation_id' => 80, 'panel_id' => 101]);
        $c = $this->dispo()->getInternalConflictMap([101], '2027-01-01', '2027-03-31', null, null, 4)[101];
        $this->assertSame(['renouvellement', '2027-01-30'], [$c->blocking_status, $c->release_date]);
    }

    public function test_confirmer_la_reservation_d_un_autre_client_est_refuse(): void
    {
        DB::table('reservations')->insert(['id' => 70, 'reference' => 'RES-ORANGE', 'client_id' => 3, 'status' => 'en_attente', 'start_date' => '2027-01-15', 'end_date' => '2027-02-15']);
        DB::table('reservation_panels')->insert(['reservation_id' => 70, 'panel_id' => 100]);

        try {
            app(ReservationService::class)->changeStatus(Reservation::findOrFail(70), 'confirme');
            $this->fail('Confirmation acceptée sur un panneau retenu pour BEM.');
        } catch (BookingConflictException $e) {
            $this->assertStringContainsString('P-100 (retenu pour le renouvellement de BEM)', $e->getMessage());
            $this->assertStringContainsString('Retenu pour le renouvellement de <strong>BEM</strong>', $e->userMessage());
        }
        $this->assertSame('en_attente', DB::table('reservations')->where('id', 70)->value('status'));
    }

    public function test_les_selecteurs_de_panneaux(): void
    {
        $data = $this->dispo()->getPanelAvailabilityData([100, 103], '2027-01-01', '2027-03-31', null, true, null, 3);

        $this->assertFalse($data[100]['available']);
        $this->assertSame(['renouvellement', '2027-01-30'], [$data[100]['blocking_status'], $data[100]['release_date']]);
        $this->assertSame('BEM', $data[100]['occupations'][0]['client_name']);
        $this->assertTrue($data[103]['available']);

        $this->assertTrue($this->dispo()->getPanelAvailabilityData([100], '2027-01-01', '2027-03-31', null, true, null, 2)[100]['available']);
    }

    // ── Affichage ─────────────────────────────────────────────────

    public function test_ecran_et_exports(): void
    {
        // Écran : statut à part ; l'occupation et la maintenance priment.
        $this->assertSame('renouvellement', ReservationController::displayStatusForPeriod('libre', true, false, true, true));
        $this->assertSame('occupe', ReservationController::displayStatusForPeriod('libre', true, true, false, true));
        $this->assertSame('maintenance', ReservationController::displayStatusForPeriod('maintenance', true, false, false, true));
        $this->assertSame('libre', ReservationController::displayStatusForPeriod('libre', true, false, false));

        // Exports (PDF, Excel) : « occupé » jusqu'à la fin de la retenue,
        // sans nom de client.
        $carte = $this->dispo()->getInternalPanelBookingMap([100, 103], '2027-01-01', '2027-03-31');
        $this->assertSame([100], $carte->keys()->all());
        $this->assertSame([1, '2027-01-30'], [$carte[100]->has_confirmed, $carte[100]->release_date]);
    }
}
