<?php

namespace Tests\Unit;

use App\Models\DiffusionDesinscription;
use App\Services\Brevo\BrevoClient;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Nettoyage des adresses (2026-11-02) : écarte les adresses bloquées par
 * Brevo et celles d'un domaine sans serveur de mail. Rapport seul par
 * défaut ; --appliquer enregistre ; une exclusion existante garde son motif.
 */
class NettoyerAdressesDiffusionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['brevo.lists.clients' => 3]);

        Schema::create('diffusion_desinscriptions', function (Blueprint $t) {
            $t->id(); $t->string('email', 191)->unique(); $t->string('motif', 20);
            $t->string('source', 20)->default('brevo'); $t->timestamp('survenu_at')->nullable(); $t->timestamps();
        });
        DiffusionDesinscription::create(['email' => 'deja@domaine-mort.invalid', 'motif' => 'desinscription', 'source' => 'lien']);

        // « .invalid » est réservé : aucun DNS ne peut y répondre.
        $this->mock(DiffusionDisponibilitesService::class, fn ($m) => $m->shouldReceive('destinataires')
            ->andReturn(collect([['email' => 'a@domaine-mort.invalid'], ['email' => 'deja@domaine-mort.invalid']])));
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('estConfigure')->andReturnTrue();
            $m->shouldReceive('emailsBloquesDeLaListe')->with(3)->andReturn(['bloque@exemple.com']);
        });
    }

    public function test_par_defaut_rien_n_est_ecrit(): void
    {
        $this->artisan('dispos:nettoyer-adresses')->assertSuccessful();

        $this->assertSame(1, DiffusionDesinscription::count());
    }

    public function test_appliquer_ecarte_les_bloquees_et_les_domaines_morts(): void
    {
        $this->artisan('dispos:nettoyer-adresses', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame('brevo', DiffusionDesinscription::where('email', 'bloque@exemple.com')->value('source'));
        $this->assertSame('verification', DiffusionDesinscription::where('email', 'a@domaine-mort.invalid')->value('source'));
        $this->assertSame('rebond', DiffusionDesinscription::where('email', 'a@domaine-mort.invalid')->value('motif'));
        // L'exclusion existante garde son motif d'origine.
        $this->assertSame('desinscription', DiffusionDesinscription::where('email', 'deja@domaine-mort.invalid')->value('motif'));
        $this->assertSame(3, DiffusionDesinscription::count());
    }
}
