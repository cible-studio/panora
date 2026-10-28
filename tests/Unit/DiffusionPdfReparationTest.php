<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Catalogue de disponibilités dont le PDF a disparu (2026-10-28) : le
 * conteneur de production est recréé à chaque mise en ligne, et le lien
 * reçu par les clients répondait 404. Le PDF est refait au même lien.
 */
class DiffusionPdfReparationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-28 10:00:00');
        Storage::fake('local');
        Schema::create('diffusion_envois', function (Blueprint $t) {
            $t->id(); $t->date('creneau'); $t->date('periode_debut'); $t->date('periode_fin'); $t->string('mode', 10);
            $t->unsignedBigInteger('declenche_par')->nullable(); $t->string('statut', 12)->default('prepare');
            $t->unsignedInteger('nb_destinataires')->default(0); $t->unsignedInteger('nb_panneaux')->default(0);
            $t->string('pdf_path')->nullable(); $t->string('pdf_token')->nullable(); $t->string('brevo_campaign_id')->nullable();
            $t->text('erreur')->nullable(); $t->timestamp('envoye_at')->nullable(); $t->timestamps();
        });
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envoi(string $lettre, string $statut, string $debut, string $fin, string $mode = 'auto'): DiffusionEnvoi
    {
        $token = str_repeat($lettre, 48);
        $id = DB::table('diffusion_envois')->insertGetId([
            'creneau' => $debut, 'periode_debut' => $debut, 'periode_fin' => $fin, 'mode' => $mode, 'statut' => $statut,
            'pdf_path' => "diffusions/{$debut}_{$mode}_{$token}.pdf", 'pdf_token' => $token,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DiffusionEnvoi::findOrFail($id);
    }

    public function test_refait_les_catalogues_envoyes_disparus_au_meme_lien_une_fois_par_periode(): void
    {
        $client  = $this->envoi('A', 'envoye', '2026-10-15', '2026-10-31');
        $test    = $this->envoi('B', 'envoye', '2026-10-15', '2026-10-31', 'test');
        $ancien  = $this->envoi('C', 'envoye', '2026-10-01', '2026-10-14');  // période finie : pas refait
        $present = $this->envoi('D', 'envoye', '2026-10-15', '2026-10-31');
        $prepare = $this->envoi('E', 'prepare', '2026-10-15', '2026-10-31'); // pas encore parti : pas refait
        Storage::disk('local')->put($present->pdf_path, 'intact');

        $service = $this->partialMock(DiffusionDisponibilitesService::class,
            fn($m) => $m->shouldReceive('renduCatalogue')->once()->andReturn([232, '%PDF-refait']));

        $this->assertSame(2, $service->reparerPdfsManquants());
        Storage::disk('local')->assertExists([$client->pdf_path, $test->pdf_path]);
        Storage::disk('local')->assertMissing([$ancien->pdf_path, $prepare->pdf_path]);
        $this->assertSame('intact', Storage::disk('local')->get($present->pdf_path));

        // Statut et lien inchangés : l'envoi ne repart pas, le client garde son lien.
        $this->assertSame('envoye', $client->fresh()->statut);
        $this->assertSame(str_repeat('A', 48), $client->fresh()->pdf_token);
        $this->get('/dispos/' . $client->pdf_token)->assertOk()->assertHeader('content-type', 'application/pdf');

        // Plus rien ne manque : aucune nouvelle génération (once() ci-dessus).
        $this->assertSame(0, $service->reparerPdfsManquants());
    }

    public function test_lien_client_casse_donne_les_coordonnees_de_cible_pas_la_connexion(): void
    {
        $client = $this->envoi('A', 'envoye', '2026-10-15', '2026-10-31');

        $this->get('/dispos/' . $client->pdf_token)
            ->assertNotFound()
            ->assertSee("Ce lien n'est plus valable", false)
            ->assertSee(config('charte.coordonnees.email'))
            ->assertSee('Écrire à CIBLE')
            ->assertDontSee('Se connecter');

        // Partout ailleurs, la connexion reste proposée.
        $this->get('/adresse-qui-n-existe-pas')->assertNotFound()->assertSee('Se connecter');
    }
}
