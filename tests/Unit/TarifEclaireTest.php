<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use App\Models\User;
use App\Services\Taxes\TarifsCommunes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tarif des panneaux éclairés et date d'effet passée (2026-11-18).
 *
 * Règle validée par écrit le 18/11 : une commune peut avoir un tarif ODP
 * et/ou TM différent pour les panneaux éclairés (case « Éclairé » de la
 * fiche, `panels.is_lit`) ; vide = tarif normal. Exemple : Adjamé ODP
 * 1 000 / 3 000, TM 1 000 / 3 000, au 01/01/2026 — saisi À L'ÉCRAN
 * (« Programmer »), date passée permise avec un motif. Les factures
 * émises ne bougent jamais ; une ligne sans panneau est « non éclairée ».
 */
class TarifEclaireTest extends TestCase
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
        (require base_path('database/migrations/2026_11_18_120000_add_tarif_eclaire_to_communes.php'))->up();

        DB::table('communes')->insert([
            ['id' => 1, 'name' => 'ADJAME', 'odp_rate' => 3000, 'tm_rate' => 1000],
            ['id' => 2, 'name' => 'PORT-BOUET', 'odp_rate' => 1000, 'tm_rate' => 1000],
            ['id' => 3, 'name' => 'ADIAKE-ASSINIE', 'odp_rate' => 1000, 'tm_rate' => 1000],
        ]);
        DB::table('commune_tax_rate_history')->insert([
            ['commune_id' => 1, 'odp_rate' => 3000, 'tm_rate' => 1000, 'effective_from' => '2025-01-01', 'effective_to' => null],
            ['commune_id' => 2, 'odp_rate' => 1000, 'tm_rate' => 1000, 'effective_from' => '2025-01-01', 'effective_to' => null],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enregistrer(int $commune, string $effet, array $tarif, ?string $notes = 'Tarif confirmé par la mairie'): void
    {
        app(TarifsCommunes::class)->enregistrer(Commune::find($commune), Carbon::parse($effet), $tarif, $notes);
    }

    public function test_sans_tarif_eclaire_rien_ne_change(): void
    {
        $c = Commune::find(2);
        $this->assertSame($c->ratesAt('2026-06-01'), $c->ratesAt('2026-06-01', true));
        $this->assertFalse($c->aUnTarifEclaire());
    }

    public function test_adjame_au_01_01_2026_avec_date_passee(): void
    {
        // Règle du 18/11 : ODP 1 000 / 3 000, TM 1 000 / 3 000, au 01/01/2026.
        $this->enregistrer(1, '2026-01-01', ['odp' => 1000, 'tm' => 1000, 'odp_eclaire' => 3000, 'tm_eclaire' => 3000]);
        $c = Commune::find(1);

        $this->assertSame(['odp' => 3000.0, 'tm' => 1000.0], $c->ratesAt('2025-12-31'), '2025 garde l\'ancien tarif');
        $this->assertSame(['odp' => 3000.0, 'tm' => 1000.0], $c->ratesAt('2025-12-31', true), 'pas de tarif éclairé avant');
        $this->assertSame(['odp' => 1000.0, 'tm' => 1000.0], $c->ratesAt('2026-01-01'), 'non éclairé dès janvier');
        $this->assertSame(['odp' => 3000.0, 'tm' => 3000.0], $c->ratesAt('2026-03-15', true), 'éclairé dès janvier');
        $this->assertTrue($c->aUnTarifEclaire());

        $lignes = CommuneTaxRateHistory::where('commune_id', 1)->orderBy('effective_from')->get();
        $this->assertSame(['2025-12-31', null], $lignes->map(fn ($l) => $l->effective_to?->toDateString())->all(), 'pas de chevauchement');
        // Le tarif courant de la fiche suit (la période couvre aujourd'hui).
        $this->assertSame([1000, 1000, 3000, 3000], [$c->odp_rate, $c->tm_rate, $c->odp_rate_eclaire, $c->tm_rate_eclaire]);
    }

    public function test_port_bouet_odp_unique_tm_selon_eclairage(): void
    {
        // Règle du 18/11 : ODP 3 000 pour tous ; TM 1 000 / 3 000.
        $this->enregistrer(2, '2026-01-01', ['odp' => 3000, 'tm' => 1000, 'odp_eclaire' => null, 'tm_eclaire' => 3000]);
        $c = Commune::find(2);

        $this->assertSame(['odp' => 3000.0, 'tm' => 1000.0], $c->ratesAt('2026-05-01'));
        $this->assertSame(['odp' => 3000.0, 'tm' => 3000.0], $c->ratesAt('2026-05-01', true), 'ODP éclairé vide = ODP normal');
    }

    public function test_une_date_passee_s_arrete_avant_le_tarif_suivant_deja_connu(): void
    {
        // Un tarif posé au crayon aujourd'hui, puis une correction au 01/01/2026.
        Commune::find(1)->update(['odp_rate' => 2000]);
        $this->enregistrer(1, '2026-01-01', ['odp' => 1000, 'tm' => 1000, 'odp_eclaire' => 3000, 'tm_eclaire' => 3000]);
        $c = Commune::find(1);

        $this->assertSame(1000.0, $c->ratesAt('2026-06-01')['odp']);
        $this->assertSame(2000.0, $c->ratesAt('2026-11-18')['odp'], 'le tarif d\'aujourd\'hui reste en place');
        $this->assertSame(2000, $c->odp_rate, 'la fiche garde le tarif courant');
        $periodes = CommuneTaxRateHistory::where('commune_id', 1)->orderBy('effective_from')->get()
            ->map(fn ($l) => $l->effective_from->toDateString() . '→' . ($l->effective_to?->toDateString() ?? '∞'))->all();
        $this->assertSame(['2025-01-01→2025-12-31', '2026-01-01→2026-11-17', '2026-11-18→∞'], $periodes);
    }

    public function test_une_commune_sans_historique_garde_son_passe(): void
    {
        $this->enregistrer(3, '2026-01-01', ['odp' => 2000, 'tm' => 1000]);
        $c = Commune::find(3);

        $this->assertSame(1000.0, $c->ratesAt('2025-06-01')['odp'], 'tarif d\'origine conservé');
        $this->assertSame(2000.0, $c->ratesAt('2026-06-01')['odp']);
    }

    public function test_une_date_future_ne_touche_pas_le_tarif_courant(): void
    {
        $this->enregistrer(1, '2027-01-01', ['odp' => 4000, 'tm' => 1000, 'tm_eclaire' => 2500], null);
        $c = Commune::find(1);

        $this->assertSame(3000, $c->odp_rate);
        $this->assertSame(3000.0, $c->ratesAt('2026-12-31')['odp']);
        $this->assertSame(['odp' => 4000.0, 'tm' => 2500.0], $c->ratesAt('2027-02-01', true));
    }

    public function test_le_crayon_historise_aussi_le_tarif_eclaire(): void
    {
        Commune::find(2)->update(['tm_rate_eclaire' => 3000]);
        $c = Commune::find(2);

        $this->assertSame(1000.0, $c->ratesAt('2026-11-17', true)['tm'], 'hier : pas de tarif éclairé');
        $this->assertSame(3000.0, $c->ratesAt('2026-11-18', true)['tm']);
    }

    public function test_l_ecran_exige_un_motif_pour_une_date_passee(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable();
                $t->string('role')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
            });
        }
        $mp = new User();
        $mp->forceFill(['id' => 5, 'name' => 'MP', 'email' => 'mp@regie.test', 'is_active' => true]);
        $mp->role = UserRole::MEDIAPLANNER;
        $this->actingAs($mp);

        $url = route('admin.settings.communes.tariffs.schedule', 1);
        $champs = ['effective_from' => '2026-01-01', 'odp_rate' => 1000, 'tm_rate' => 1000, 'odp_rate_eclaire' => 3000, 'tm_rate_eclaire' => 3000];

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->from(route('admin.settings.communes.tariffs'))->post($url, $champs)->assertSessionHas('error');
        $this->assertSame(1, CommuneTaxRateHistory::where('commune_id', 1)->count(), 'rien n\'est écrit sans motif');

        $this->from(route('admin.settings.communes.tariffs'))->post($url, $champs + ['notes' => 'Tarif confirmé le 18/11'])
            ->assertSessionHas('success');
        $this->assertSame(3000.0, Commune::find(1)->ratesAt('2026-03-01', true)['odp'], 'le MP gère les tarifs (décision du 18/11)');
    }
}
