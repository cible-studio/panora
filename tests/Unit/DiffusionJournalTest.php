<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Diffusion des disponibilités, journal des envois et adresses exclues
 * (2026-10-28) : filtres, mois, pages courtes, recherche. Même avec des
 * milliers de lignes, l'écran reste lisible.
 */
class DiffusionJournalTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        Carbon::setTestNow('2026-10-28 10:00:00');

        Schema::create('jours_feries', function (Blueprint $t) {
            $t->id(); $t->date('date')->unique(); $t->string('libelle'); $t->string('source')->default('manuel'); $t->timestamps();
        });
        Schema::create('diffusion_envois', function (Blueprint $t) {
            $t->id(); $t->date('creneau'); $t->date('periode_debut'); $t->date('periode_fin'); $t->string('mode', 10);
            $t->unsignedBigInteger('declenche_par')->nullable(); $t->string('statut', 12)->default('prepare');
            $t->unsignedInteger('nb_destinataires')->default(0); $t->unsignedInteger('nb_panneaux')->default(0);
            $t->string('pdf_path')->nullable(); $t->string('pdf_token')->nullable(); $t->string('brevo_campaign_id')->nullable();
            $t->text('erreur')->nullable(); $t->timestamp('envoye_at')->nullable(); $t->timestamps();
        });

        // Ce que le gabarit admin lit à chaque page.
        $this->withoutMiddleware(\App\Http\Middleware\SyncReactiveState::class);
        Schema::create('alerts', function (Blueprint $t) {
            $t->id(); $t->boolean('is_read')->default(false); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });
        foreach (['admin.conflicts.count', 'admin.messages.new_count', 'admin.signalements.pending_count'] as $cle) {
            Cache::put($cle, 0, 600);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envoi(string $mode, string $statut, string $date, ?string $erreur = null): void
    {
        DB::table('diffusion_envois')->insert([
            'creneau' => '2026-10-01', 'periode_debut' => '2026-10-01', 'periode_fin' => '2026-10-31',
            'mode' => $mode, 'statut' => $statut, 'nb_panneaux' => 353, 'nb_destinataires' => $statut === 'envoye' ? 2 : 0,
            'erreur' => $erreur, 'created_at' => $date, 'updated_at' => $date,
        ]);
    }

    private function journalDeTest(): void
    {
        $refus = 'Brevo a refusé la création de la campagne (HTTP 402) : Your account is under validation. You can not create another campaign.';
        $this->envoi('test', 'echec', '2026-09-30 15:31:00', $refus);
        $this->envoi('test', 'envoye', '2026-10-01 11:19:00');
        $this->envoi('auto', 'envoye', '2026-10-15 08:00:00');
        $this->envoi('manuel', 'echec', '2026-10-16 09:00:00', 'Délai dépassé');
        for ($i = 0; $i < 12; $i++) {
            $this->envoi('test', 'envoye', '2026-10-20 1' . ($i % 10) . ':00:00');
        }
    }

    public function test_filtres_et_mois(): void
    {
        $this->journalDeTest();

        $this->assertSame(16, DiffusionEnvoi::filtre('tous')->count());
        $this->assertSame(2, DiffusionEnvoi::filtre('clients')->count());   // auto + manuel
        $this->assertSame(14, DiffusionEnvoi::filtre('tests')->count());
        $this->assertSame(2, DiffusionEnvoi::filtre('echecs')->count());
        $this->assertSame(1, DiffusionEnvoi::duMois('2026-09')->count());
        $this->assertSame(1, DiffusionEnvoi::duMois('2026-10')->filtre('echecs')->count());
        $this->assertSame(16, DiffusionEnvoi::duMois('2026-13')->count());    // mois invalide : tout
        $this->assertSame(16, DiffusionEnvoi::duMois(null)->count());
    }

    public function test_l_ecran_filtre_pagine_par_10_et_replie_les_erreurs_longues(): void
    {
        $this->journalDeTest();
        $admin = User::findOrFail(1);

        $lignes = fn($reponse) => substr_count($reponse->getContent(), 'text-align:right;">353<');

        // 16 envois : 10 en page 1, 6 en page 2 (le plus ancien, avec la longue erreur Brevo repliée).
        $page = $this->withoutVite()->actingAs($admin)->get('/admin/diffusion-dispos')->assertOk();
        $page->assertSee('Envois aux clients')->assertSee('Échecs')->assertSee('Septembre 2026')->assertSee('Octobre 2026');
        $this->assertSame(10, $lignes($page));
        $page2 = $this->actingAs($admin)->get('/admin/diffusion-dispos?page=2')->assertOk();
        $this->assertSame(6, $lignes($page2));
        $page2->assertSee('<details', false);

        $echecs = $this->actingAs($admin)->get('/admin/diffusion-dispos?journal=echecs')->assertOk();
        $this->assertSame(2, $lignes($echecs));
        $echecs->assertSee('Délai dépassé')->assertSee('HTTP 402');
        $echecs->assertDontSee('20/10/2026'); // aucun envoi réussi dans ce filtre

        $this->actingAs($admin)->get('/admin/diffusion-dispos?journal=clients&mois=2026-09')
            ->assertOk()->assertSee('Aucun envoi pour ce choix.');

        // Valeurs bricolées dans l'adresse : ignorées, pas d'erreur.
        $this->actingAs($admin)->get('/admin/diffusion-dispos?journal[]=x&mois=abc&exclu[]=y')->assertOk();
    }

    public function test_adresses_exclues_les_50_plus_recentes_et_recherche(): void
    {
        $lignes = [];
        for ($i = 1; $i <= 60; $i++) {
            $lignes[] = ['email' => "client{$i}@exemple.ci", 'motif' => 'rebond', 'source' => 'brevo',
                'survenu_at' => Carbon::parse('2026-01-01')->addDays($i), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('diffusion_desinscriptions')->insert($lignes);
        $admin = User::findOrFail(1);

        $page = $this->withoutVite()->actingAs($admin)->get('/admin/diffusion-dispos')->assertOk();
        $page->assertSee('60 adresses exclues · les 50 plus récentes', false)
            ->assertSee('client60@exemple.ci')->assertDontSee('client5@exemple.ci');

        $this->actingAs($admin)->get('/admin/diffusion-dispos?exclu=client5')
            ->assertOk()->assertSee('11 résultats pour « client5 »', false) // client5, client50 à client59
            ->assertSee('client5@exemple.ci')->assertSee('client50@exemple.ci');

        $this->actingAs($admin)->get('/admin/diffusion-dispos?exclu=' . urlencode('%'))
            ->assertOk()->assertSee('Aucune adresse ne correspond.');   // « % » cherché tel quel
    }
}
