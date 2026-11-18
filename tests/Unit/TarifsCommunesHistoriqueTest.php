<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tarifs communaux ODP / TM (2026-11-18, constaté avant la présentation à la DG).
 *
 * 1. « Historique » affichait « Erreur de chargement » et « Programmer »
 *    échouait : la page appelait /admin/communes/…, l'adresse réelle est
 *    /admin/settings/communes/….
 * 2. Une commune encore sans historique (ADIAKE-ASSINIE, AUTOROUTE DU NORD)
 *    rendait une modification au crayon RÉTROACTIVE : Commune::ratesAt()
 *    retombait sur le tarif courant pour toutes les dates passées.
 */
class TarifsCommunesHistoriqueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');

        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('code')->nullable(); $t->string('city')->nullable(); $t->string('region')->nullable();
            $t->unsignedInteger('odp_rate')->default(0); $t->unsignedInteger('tm_rate')->default(0); $t->timestamps();
        });
        Schema::create('commune_tax_rate_history', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commune_id'); $t->unsignedInteger('odp_rate'); $t->unsignedInteger('tm_rate');
            $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->string('notes', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable();
                $t->string('role')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
            });
        }
        DB::table('communes')->insert([
            ['id' => 1, 'name' => 'ABENGOUROU', 'odp_rate' => 1000, 'tm_rate' => 1000],
            ['id' => 2, 'name' => 'ADIAKE-ASSINIE', 'odp_rate' => 1000, 'tm_rate' => 1000],
        ]);
        DB::table('commune_tax_rate_history')->insert([
            'commune_id' => 1, 'odp_rate' => 1000, 'tm_rate' => 1000, 'effective_from' => '2025-01-01', 'effective_to' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_page_appelle_les_vraies_adresses(): void
    {
        $vue = file_get_contents(resource_path('views/admin/settings/communes-tariffs.blade.php'));

        $this->assertStringNotContainsString('/admin/communes/', $vue, 'adresse inexistante (404)');
        $this->assertStringContainsString("route('admin.settings.communes.tariffs.history'", $vue);
        $this->assertStringContainsString("route('admin.settings.communes.tariffs.schedule'", $vue);
        $this->assertStringContainsString('echapper(h.notes)', $vue, 'notes saisies à la main : échappées');
    }

    public function test_l_historique_repond(): void
    {
        $admin = new User();
        $admin->forceFill(['id' => 1, 'name' => 'Admin', 'email' => 'admin@regie.test', 'is_active' => true]);
        $admin->role = UserRole::ADMIN;
        $this->actingAs($admin);

        $this->getJson(route('admin.settings.communes.tariffs.history', 1))
            ->assertOk()
            ->assertJsonPath('commune.name', 'ABENGOUROU')
            ->assertJsonPath('history.0.odp_rate', 1000);
    }

    public function test_une_commune_sans_historique_garde_son_ancien_tarif_pour_le_passe(): void
    {
        Commune::find(2)->update(['odp_rate' => 2500]);
        $c = Commune::find(2);

        $this->assertSame(['odp' => 1000.0, 'tm' => 1000.0], $c->ratesAt('2026-06-15'), 'le passé garde l\'ancien tarif');
        $this->assertSame(['odp' => 1000.0, 'tm' => 1000.0], $c->ratesAt('2026-11-17'));
        $this->assertSame(['odp' => 2500.0, 'tm' => 1000.0], $c->ratesAt('2026-11-18'), 'le nouveau, dès aujourd\'hui');
        $this->assertSame(['odp' => 2500.0, 'tm' => 1000.0], $c->ratesAt('2027-03-01'));
        $this->assertSame(2, CommuneTaxRateHistory::where('commune_id', 2)->count());
    }

    public function test_une_commune_deja_historisee_ne_change_pas_de_comportement(): void
    {
        Commune::find(1)->update(['odp_rate' => 1500]);
        $c = Commune::find(1);

        $this->assertSame(1000.0, $c->ratesAt('2026-06-15')['odp']);
        $this->assertSame(1500.0, $c->ratesAt('2026-11-18')['odp']);
        $this->assertSame(2, CommuneTaxRateHistory::where('commune_id', 1)->count(), 'pas de ligne « d\'origine » en plus');
        $this->assertSame('2026-11-17', CommuneTaxRateHistory::where('commune_id', 1)->orderBy('effective_from')->first()->effective_to->toDateString());
    }
}
