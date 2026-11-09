<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PDF des envois de test (règle du 2026-11-09) : supprimés après 7 jours,
 * car ils pesaient 342 Mo sur les 545 Mo de la sauvegarde. Les envois aux
 * clients ne sont jamais concernés. Un test suspendu par Brevo garde ses
 * PDF jusqu'à la fin de sa période : le test #8 du 2/11 a atteint des clients.
 */
class DiffusionPurgePdfTestsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 01:15:00');
        Storage::fake('local');
        Schema::create('diffusion_envois', function (Blueprint $t) {
            $t->id(); $t->date('creneau'); $t->date('periode_debut'); $t->date('periode_fin'); $t->string('mode', 10);
            $t->unsignedBigInteger('declenche_par')->nullable(); $t->string('statut', 12)->default('prepare');
            $t->unsignedInteger('nb_destinataires')->default(0); $t->unsignedInteger('nb_panneaux')->default(0);
            $t->string('pdf_path')->nullable(); $t->string('pdf_token')->nullable();
            $t->unsignedInteger('nb_panneaux_abidjan')->default(0); $t->unsignedInteger('nb_panneaux_interieur')->default(0);
            $t->string('pdf_path_abidjan')->nullable(); $t->string('pdf_token_abidjan')->nullable();
            $t->string('pdf_path_interieur')->nullable(); $t->string('pdf_token_interieur')->nullable();
            $t->string('brevo_campaign_id')->nullable();
            $t->text('erreur')->nullable(); $t->timestamp('envoye_at')->nullable(); $t->timestamps();
        });
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envoi(
        string $lettre,
        string $mode,
        string $creeLe,
        string $statut = DiffusionEnvoi::STATUT_ENVOYE,
        string $finPeriode = '2026-11-30',
    ): DiffusionEnvoi {
        $jeton = fn (string $l) => str_repeat($l, 48);
        $chemin = fn (string $zone) => "diffusions/2026-11-15_{$mode}_{$zone}{$lettre}.pdf";

        $id = DB::table('diffusion_envois')->insertGetId([
            'creneau' => '2026-11-15', 'periode_debut' => '2026-11-15', 'periode_fin' => $finPeriode,
            'mode' => $mode, 'statut' => $statut,
            'pdf_path' => $chemin(''), 'pdf_token' => $jeton($lettre),
            'pdf_path_abidjan' => $chemin('abidjan_'), 'pdf_token_abidjan' => $jeton($lettre . 'a'),
            'pdf_path_interieur' => $chemin('interieur_'), 'pdf_token_interieur' => $jeton($lettre . 'i'),
            'created_at' => $creeLe, 'updated_at' => $creeLe,
        ]);
        $envoi = DiffusionEnvoi::findOrFail($id);

        foreach ($envoi->cheminsCatalogues() as $c) {
            Storage::disk('local')->put($c, str_repeat('x', 1000));
        }

        return $envoi;
    }

    private function purger(bool $simuler = false): array
    {
        return app(DiffusionDisponibilitesService::class)->purgerPdfTests($simuler);
    }

    public function test_un_test_de_plus_de_sept_jours_perd_ses_pdf_mais_reste_au_journal(): void
    {
        $envoi = $this->envoi('a', DiffusionEnvoi::MODE_TEST, '2026-11-12 10:00:00');
        $chemins = array_values($envoi->cheminsCatalogues());

        $this->assertSame(['envois' => 1, 'fichiers' => 3, 'octets' => 3000], $this->purger());

        Storage::disk('local')->assertMissing($chemins);
        $envoi->refresh();
        $this->assertSame([], $envoi->cheminsCatalogues());
        $this->assertNull($envoi->lienPdf(), 'Plus de lien dans le journal.');
        $this->assertNull($envoi->lienPdfZone(DiffusionEnvoi::ZONE_ABIDJAN));
        $this->assertNull($envoi->lienPdfZone(DiffusionEnvoi::ZONE_INTERIEUR));
    }

    public function test_un_test_recent_est_garde(): void
    {
        $envoi = $this->envoi('b', DiffusionEnvoi::MODE_TEST, '2026-11-14 10:00:00');

        $this->assertSame(0, $this->purger()['envois']);
        Storage::disk('local')->assertExists(array_values($envoi->cheminsCatalogues()));
    }

    public function test_les_envois_aux_clients_ne_sont_jamais_touches(): void
    {
        $manuel = $this->envoi('c', DiffusionEnvoi::MODE_MANUEL, '2026-10-01 10:00:00', finPeriode: '2026-10-31');
        $auto   = $this->envoi('d', DiffusionEnvoi::MODE_AUTO, '2026-10-01 10:00:00', finPeriode: '2026-10-31');

        $this->assertSame(0, $this->purger()['envois']);
        Storage::disk('local')->assertExists([
            ...array_values($manuel->cheminsCatalogues()),
            ...array_values($auto->cheminsCatalogues()),
        ]);
    }

    public function test_un_test_suspendu_garde_ses_pdf_jusqu_a_la_fin_de_sa_periode(): void
    {
        // Le test #8 du 2/11 est parti à des clients : leurs liens doivent
        // marcher tant que la période annoncée n'est pas finie.
        $enCours = $this->envoi('e', DiffusionEnvoi::MODE_TEST, '2026-11-02 10:00:00', DiffusionEnvoi::STATUT_SUSPENDU);
        $finie   = $this->envoi('f', DiffusionEnvoi::MODE_TEST, '2026-10-02 10:00:00', DiffusionEnvoi::STATUT_SUSPENDU, '2026-10-31');

        $this->assertSame(1, $this->purger()['envois']);
        Storage::disk('local')->assertExists(array_values($enCours->cheminsCatalogues()));
        $this->assertSame([], $finie->refresh()->cheminsCatalogues());
    }

    public function test_la_simulation_ne_touche_a_rien(): void
    {
        $envoi = $this->envoi('g', DiffusionEnvoi::MODE_TEST, '2026-11-01 10:00:00');
        $chemins = $envoi->cheminsCatalogues();

        $this->assertSame(['envois' => 1, 'fichiers' => 3, 'octets' => 3000], $this->purger(simuler: true));

        Storage::disk('local')->assertExists(array_values($chemins));
        $this->assertSame($chemins, $envoi->refresh()->cheminsCatalogues());
    }

    public function test_un_pdf_nettoye_n_est_pas_refait_et_son_lien_repond_404(): void
    {
        $envoi = $this->envoi('h', DiffusionEnvoi::MODE_TEST, '2026-11-02 10:00:00');
        $jeton = $envoi->pdf_token;
        $chemin = $envoi->pdf_path;
        $this->purger();

        // La réparation tourne chaque minute : elle ne doit rien refaire.
        $this->assertSame(0, app(DiffusionDisponibilitesService::class)->reparerPdfsManquants());
        Storage::disk('local')->assertMissing($chemin);

        $this->get(route('diffusion.pdf', $jeton))->assertNotFound();
    }

    public function test_la_duree_est_reglable_et_la_tache_planifiee_avant_la_sauvegarde(): void
    {
        config(['diffusion.jours_pdf_tests' => 30]);
        $this->envoi('i', DiffusionEnvoi::MODE_TEST, '2026-11-01 10:00:00');
        $this->assertSame(0, $this->purger()['envois']);

        $this->assertArrayHasKey('dispos:purger-pdf-tests', Artisan::all());
        $this->assertMatchesRegularExpression(
            "/Schedule::command\('dispos:purger-pdf-tests'\)\s+->dailyAt\('01:15'\)/",
            file_get_contents(base_path('routes/console.php'))
        );
    }
}
