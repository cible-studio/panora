<?php

namespace Tests\Unit;

use App\Models\PoseTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Équipe créditée d'une pose — règle validée le 2026-11-04 (option « b »).
 *
 * Constat : chaque pose attribuée à David portait « Equipe Abengourou »,
 * même depuis la liste ou avec « Aucune (pose solo) ». Il est membre d'une
 * seule équipe : un saving de PoseTask en déduisait team_name, puis la
 * synchro en tirait pose_team_id. Ses poses sortaient de ses chiffres.
 *
 * Désormais :
 *   · le serveur ne déduit plus l'équipe du technicien ;
 *   · les formulaires la PROPOSENT (script unique _equipe_proposee), sans
 *     jamais écraser un choix manuel, « Aucune » compris ;
 *   · un choix explicite (select, action groupée, nom historique) garde
 *     sa synchro pose_team_id ↔ team_name.
 *
 * Les migrations ne passent pas sous SQLite (cf. TECHNICAL_DEBT) : base
 * réduite, et seul l'événement « saving » est déclenché — c'est lui qui
 * porte la règle.
 */
class EquipeProposeeTest extends TestCase
{
    private const DAVID = 7;
    private const ABENGOUROU = 3;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable();
            $t->unsignedBigInteger('pose_team_id')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('pose_teams', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->nullable(); $t->string('color_slug')->nullable();
            $t->unsignedBigInteger('leader_user_id')->nullable(); $t->boolean('is_active')->default(true);
            $t->text('description')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('pose_team_user', function (Blueprint $t) {
            $t->unsignedBigInteger('pose_team_id'); $t->unsignedBigInteger('user_id'); $t->timestamp('joined_at')->nullable();
        });

        // David : technicien membre d'UNE seule équipe.
        DB::table('users')->insert(['id' => self::DAVID, 'name' => 'DAVID', 'role' => 'technique']);
        DB::table('pose_teams')->insert(['id' => self::ABENGOUROU, 'name' => 'Equipe Abengourou', 'color_slug' => 'red']);
        DB::table('pose_team_user')->insert(['pose_team_id' => self::ABENGOUROU, 'user_id' => self::DAVID, 'joined_at' => now()]);
    }

    /** Déclenche les règles « saving » de PoseTask, sans écrire en base. */
    private function enregistrer(PoseTask $pose): PoseTask
    {
        (fn () => $this->fireModelEvent('saving', false))->call($pose);

        return $pose;
    }

    /** Pose déjà en base, solo, sans technicien. */
    private function poseSolo(): PoseTask
    {
        return (new PoseTask)->newFromBuilder([
            'id' => 1, 'assigned_user_id' => null, 'pose_team_id' => null, 'team_name' => null,
        ]);
    }

    // ── Le serveur ne choisit plus l'équipe ─────────────────────────

    public function test_attribuer_david_depuis_la_liste_garde_la_pose_solo(): void
    {
        // Action groupée « Technicien » : seul assigned_user_id change.
        $pose = $this->poseSolo();
        $pose->assigned_user_id = self::DAVID;

        $this->enregistrer($pose);

        $this->assertNull($pose->pose_team_id, 'Plus de crédit « Equipe Abengourou » imposé.');
        $this->assertNull($pose->team_name);
    }

    public function test_une_nouvelle_pose_de_david_sans_equipe_reste_solo(): void
    {
        $pose = new PoseTask(['assigned_user_id' => self::DAVID]);

        $this->enregistrer($pose);

        $this->assertNull($pose->pose_team_id);
        $this->assertNull($pose->team_name);
    }

    // ── Un choix explicite garde sa synchro ─────────────────────────

    public function test_une_equipe_choisie_est_creditee_avec_son_nom(): void
    {
        $pose = $this->poseSolo();
        $pose->assigned_user_id = self::DAVID;
        $pose->pose_team_id = self::ABENGOUROU;

        $this->enregistrer($pose);

        $this->assertSame(self::ABENGOUROU, (int) $pose->pose_team_id);
        $this->assertSame('Equipe Abengourou', $pose->team_name);
    }

    public function test_un_nom_d_equipe_saisi_retrouve_son_equipe(): void
    {
        // Action groupée historique « rename_team » : team_name explicite.
        $pose = $this->poseSolo();
        $pose->team_name = 'equipe abengourou';

        $this->enregistrer($pose);

        $this->assertSame(self::ABENGOUROU, (int) $pose->pose_team_id);
    }

    // ── Les formulaires proposent, sans imposer ─────────────────────

    public function test_les_deux_formulaires_utilisent_le_script_unique(): void
    {
        foreach (['create', 'edit'] as $vue) {
            $source = file_get_contents(resource_path("views/admin/poses/{$vue}.blade.php"));

            $this->assertStringContainsString("@include('admin.poses.partials._equipe_proposee'", $source, $vue);
            $this->assertStringContainsString('id="team-auto-hint"', $source, $vue);
            // Plus de copie locale qui écrase le select sans condition.
            $this->assertStringNotContainsString('teamsList.length !== 1', $source, $vue);
        }
    }

    public function test_la_proposition_respecte_un_choix_manuel(): void
    {
        $script = file_get_contents(resource_path('views/admin/poses/partials/_equipe_proposee.blade.php'));

        // Un changement manuel du select Équipe (« Aucune » compris) bloque
        // toute proposition ultérieure.
        $this->assertStringContainsString("selTeam.addEventListener('change'", $script);
        $this->assertStringContainsString('if (choisie) return;', $script);
        // La proposition ne simule pas un choix manuel (le filtre des
        // techniciens ne s'applique qu'à un vrai choix).
        $this->assertStringNotContainsString("new Event('change')", $script);
    }

    public function test_la_fiche_technicien_ne_promet_plus_un_report_automatique(): void
    {
        $source = file_get_contents(resource_path('views/admin/poses/techniciens/create.blade.php'));

        $this->assertStringNotContainsString('reportée automatiquement', $source);
        $this->assertStringContainsString('proposée', $source);
    }
}
