<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\PoseTask;
use App\Models\User;
use App\Models\Vague;
use App\Services\Piges\PigeFiltres;
use App\Services\Piges\RapportPigesService;
use App\Services\PoseService;
use App\Services\Vagues\VagueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Vagues d'une campagne annuelle (2026-11-04).
 *
 * Un client garde ses panneaux toute l'année et change plusieurs fois de
 * message ; le MP renommait la campagne et perdait l'historique. Règles
 * validées : une vague concerne tout ou partie des panneaux ; facture
 * unique ; nom obligatoire (« Vague N » proposé) ; Vague 1 au premier
 * changement, avec le nom de la campagne ; retouche = vague de la pose
 * réparée ; panneau ajouté en cours d'année = vague en cours ; nom montré
 * aux techniciens.
 *
 * Base SQLite réduite + la VRAIE migration des vagues.
 */
class VaguesCampagneTest extends TestCase
{
    private const MOOV = 1;
    private User $mp;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable();
            $t->string('whatsapp_number')->nullable(); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id')->nullable(); $t->string('status')->default('actif');
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->string('name')->nullable(); $t->unsignedBigInteger('commune_id')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('pose_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('assigned_user_id')->nullable(); $t->string('team_name')->nullable();
            $t->unsignedBigInteger('pose_team_id')->nullable(); $t->dateTime('scheduled_at')->nullable();
            $t->dateTime('done_at')->nullable(); $t->string('status')->default('planifiee'); $t->text('notes')->nullable();
            $t->string('pose_kind')->nullable(); $t->unsignedBigInteger('replaces_pose_task_id')->nullable();
            $t->dateTime('replaced_at')->nullable(); $t->string('public_token')->nullable();
            $t->unsignedTinyInteger('progress_percent')->default(0); $t->timestamps();
        });
        Schema::create('piges', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('panel_id'); $t->unsignedBigInteger('campaign_id')->nullable();
            $t->unsignedBigInteger('pose_task_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('photo_path')->default('x.jpg'); $t->string('status')->default('verifie');
            $t->timestamp('taken_at')->nullable(); $t->timestamp('archived_at')->nullable(); $t->timestamps();
        });

        // La vraie migration (table vagues + pose_tasks.vague_id).
        (require database_path('migrations/2026_11_04_100000_create_vagues_table.php'))->up();

        DB::table('users')->insert(['id' => 1, 'name' => 'MP', 'role' => 'mediaplanner']);
        DB::table('clients')->insert(['id' => self::MOOV, 'name' => 'MOOV AFRICA']);
        DB::table('campaigns')->insert(['id' => 1, 'name' => 'MOOV 2026', 'client_id' => self::MOOV, 'status' => 'actif']);
        DB::table('panels')->insert([
            ['id' => 1, 'reference' => 'COC-001'], ['id' => 2, 'reference' => 'COC-002'], ['id' => 3, 'reference' => 'YOP-001'],
        ]);
        // Phase 1 posée sur deux panneaux.
        DB::table('pose_tasks')->insert([
            ['id' => 1, 'panel_id' => 1, 'campaign_id' => 1, 'status' => 'realisee', 'pose_kind' => 'initial', 'scheduled_at' => '2026-01-10 08:00:00', 'done_at' => '2026-01-10 10:00:00'],
            ['id' => 2, 'panel_id' => 2, 'campaign_id' => 1, 'status' => 'realisee', 'pose_kind' => 'initial', 'scheduled_at' => '2026-01-11 08:00:00', 'done_at' => '2026-01-11 10:00:00'],
        ]);

        $this->mp = User::find(1);
    }

    private function rechange(int $source, array $choix): array
    {
        return app(PoseService::class)->createRechange(
            PoseTask::find($source),
            array_merge(['scheduled_at' => '2026-09-01 08:00:00', 'pose_kind' => 'rechange'], $choix),
            $this->mp
        );
    }

    private function vagues(): array
    {
        return Vague::orderBy('numero')->get()->map(fn ($v) => [$v->numero, $v->nom])->all();
    }

    // ── Une campagne qui ne change pas de message ne change pas ─────

    public function test_sans_nouvelle_vague_rien_ne_change(): void
    {
        $r = $this->rechange(1, ['vague' => VagueService::EN_COURS]);

        $this->assertTrue($r['ok']);
        $this->assertNull($r['task']->vague_id);
        $this->assertSame([], $this->vagues());
        $this->assertTrue(app(VagueService::class)->resume(Campaign::find(1))->isEmpty());
    }

    // ── Première vague ──────────────────────────────────────────────

    public function test_la_premiere_nouvelle_vague_cree_la_vague_1_avec_le_nom_de_la_campagne(): void
    {
        $r = $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);

        $this->assertTrue($r['ok']);
        $this->assertSame([[1, 'MOOV 2026'], [2, 'Phase 2']], $this->vagues());

        [$v1, $v2] = Vague::orderBy('numero')->get();
        // Toutes les poses déjà faites rejoignent la Vague 1 — y compris
        // le panneau 2, que ce changement ne concerne pas.
        $this->assertSame($v1->id, PoseTask::find(1)->vague_id);
        $this->assertSame($v1->id, PoseTask::find(2)->vague_id);
        $this->assertSame($v2->id, $r['task']->vague_id);
        $this->assertSame('2026-01-10', $v1->debut_le->toDateString());
        $this->assertSame('2026-09-01', $v2->debut_le->toDateString());
    }

    public function test_un_lot_cree_une_seule_vague_par_campagne(): void
    {
        $r = app(PoseService::class)->createRechangeBulk([1, 2], [
            'scheduled_at' => '2026-09-01 08:00:00', 'pose_kind' => 'rechange',
            'vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2',
        ], $this->mp);

        $this->assertSame(2, $r['created']);
        $this->assertSame([[1, 'MOOV 2026'], [2, 'Phase 2']], $this->vagues());
        $v2 = Vague::where('numero', 2)->value('id');
        $this->assertSame([$v2, $v2], PoseTask::whereIn('id', $r['task_ids'])->pluck('vague_id')->all());
    }

    public function test_un_lot_sans_succes_ne_laisse_aucune_vague(): void
    {
        DB::table('pose_tasks')->update(['status' => 'planifiee']); // rien de réaffichable

        $r = app(PoseService::class)->createRechangeBulk([1, 2], [
            'scheduled_at' => '2026-09-01 08:00:00', 'vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2',
        ], $this->mp);

        $this->assertSame(0, $r['created']);
        $this->assertSame([], $this->vagues());
    }

    public function test_continuer_une_vague_existante(): void
    {
        $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);
        $v2 = Vague::where('numero', 2)->first();

        // Le lendemain, le panneau 2 passe à son tour en Phase 2.
        $r = $this->rechange(2, ['vague' => (string) $v2->id]);

        $this->assertSame($v2->id, $r['task']->vague_id);
        $this->assertCount(2, $this->vagues());
    }

    // ── Retouche et poses hors rechange ─────────────────────────────

    public function test_une_retouche_reste_dans_la_vague_de_la_pose_reparee(): void
    {
        $r = $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);
        $r['task']->forceFill(['status' => 'realisee', 'done_at' => now()])->save();

        // Même si le formulaire envoyait « nouvelle vague » : ignoré.
        $retouche = $this->rechange($r['task']->id, ['pose_kind' => 'retouche', 'vague' => VagueService::NOUVELLE, 'vague_nom' => 'X']);

        $this->assertSame($r['task']->vague_id, $retouche['task']->vague_id);
        $this->assertCount(2, $this->vagues());
    }

    public function test_un_panneau_ajoute_en_cours_d_annee_rejoint_la_vague_en_cours(): void
    {
        $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);

        $pose = PoseTask::create(['panel_id' => 3, 'campaign_id' => 1, 'status' => 'planifiee', 'scheduled_at' => '2026-09-05 08:00:00']);

        $this->assertSame(Vague::where('numero', 2)->value('id'), $pose->vague_id);
        $this->assertSame('Vague 2 · Phase 2', $pose->libelleVague());
    }

    // ── Formulaire : nom obligatoire ────────────────────────────────

    public function test_le_nom_est_obligatoire_pour_une_nouvelle_vague(): void
    {
        $valide = function (array $donnees): bool {
            $request = Request::create('/', 'POST', $donnees);
            return Validator::make($donnees, VagueService::regles($request))->passes();
        };

        $this->assertFalse($valide(['vague' => 'nouvelle', 'vague_nom' => '', 'pose_kind' => 'rechange']));
        $this->assertTrue($valide(['vague' => 'nouvelle', 'vague_nom' => 'Phase 2', 'pose_kind' => 'rechange']));
        $this->assertTrue($valide(['vague' => 'nouvelle', 'vague_nom' => '', 'pose_kind' => 'retouche']));
        $this->assertTrue($valide(['vague' => 'en_cours']));
        $this->assertSame('Vague 2', app(VagueService::class)->nomPropose(1));
    }

    // ── Piges, fiche campagne, PDF ──────────────────────────────────

    public function test_les_piges_se_retrouvent_par_vague(): void
    {
        $r = $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);
        [$v1, $v2] = Vague::orderBy('numero')->get();

        DB::table('piges')->insert([
            ['id' => 1, 'panel_id' => 1, 'campaign_id' => 1, 'pose_task_id' => 1],                // phase 1
            ['id' => 2, 'panel_id' => 1, 'campaign_id' => 1, 'pose_task_id' => $r['task']->id],  // phase 2
            ['id' => 3, 'panel_id' => 2, 'campaign_id' => 1, 'pose_task_id' => null],            // ancienne, sans pose
        ]);

        $ids = fn (Vague $v) => PigeFiltres::appliquer(\App\Models\Pige::active(), ['vague_id' => $v->id])->orderBy('id')->pluck('id')->all();
        $this->assertSame([1, 3], $ids($v1));
        $this->assertSame([2], $ids($v2));

        $resume = app(VagueService::class)->resume(Campaign::find(1));
        $this->assertSame([2, 1], $resume->pluck('panneaux')->all());
        $this->assertSame([2, 1], $resume->pluck('piges')->all());
        $this->assertSame([2, 0], $resume->pluck('realisees')->all());

        // Filtre Vague proposé dès que la campagne est choisie.
        $this->assertSame([1, 2], PigeFiltres::listes(['campaign_id' => 1])['vagues']->pluck('numero')->all());
    }

    public function test_titre_du_pdf_avec_la_vague(): void
    {
        $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);
        [$v1, $v2] = Vague::orderBy('numero')->get();
        $rapport = app(RapportPigesService::class);

        $this->assertSame('MOOV 2026 — Phase 2', $rapport->titreParDefaut(['campaign_id' => 1, 'vague_id' => $v2->id]));
        $this->assertSame('MOOV 2026', $rapport->titreParDefaut(['campaign_id' => 1, 'vague_id' => $v1->id]));
    }

    public function test_libelles(): void
    {
        $nommee = new Vague(['numero' => 2, 'nom' => 'Phase 2']);
        $parDefaut = new Vague(['numero' => 3, 'nom' => 'Vague 3']);

        $this->assertSame('Vague 2 · Phase 2', $nommee->libelle());
        $this->assertSame('V2 · Phase 2', $nommee->badge());
        $this->assertSame('Vague 3', $parDefaut->libelle());
        $this->assertSame('V3', $parDefaut->badge());
    }

    // ── Déploiement avant migration ─────────────────────────────────

    public function test_sans_la_table_les_poses_se_creent_comme_avant(): void
    {
        Schema::drop('vagues');

        $r = $this->rechange(1, ['vague' => VagueService::NOUVELLE, 'vague_nom' => 'Phase 2']);
        $pose = PoseTask::create(['panel_id' => 3, 'campaign_id' => 1, 'status' => 'planifiee']);

        $this->assertTrue($r['ok']);
        $this->assertNull($r['task']->vague_id);
        $this->assertNull($pose->libelleVague());
    }

    // ── Écrans techniciens et rechanges ─────────────────────────────

    public function test_les_ecrans_utilisent_le_champ_et_le_libelle_communs(): void
    {
        foreach (['admin/campaigns/show', 'admin/poses/show', 'admin/poses/index'] as $vue) {
            $this->assertStringContainsString("@include('admin.poses.partials._champ_vague'",
                file_get_contents(resource_path("views/{$vue}.blade.php")), $vue);
        }
        foreach ([
            'public/tech/partials/_pose_card', 'public/pose-task', 'public/tech-route-sheet',
            'admin/campaigns/fiche-pose-pdf', 'admin/campaigns/fiche-pose-pdf-liste',
        ] as $vue) {
            $this->assertStringContainsString('libelleVague()', file_get_contents(resource_path("views/{$vue}.blade.php")), $vue);
        }
    }
}
