<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 Performance Technicien — table équipes de pose.
 *
 * Décisions métier :
 *   - Couleur stockée en SLUG (red, blue, indigo…) — résolu en hex
 *     côté PHP via PoseTeam::colorHex(). Découple la palette du data.
 *   - SoftDeletes : la suppression détache les membres (pose_team_id=NULL)
 *     mais préserve l'historique (drill ancienne équipe possible).
 *   - leader_user_id UNIQUE — un user = leader d'une équipe maximum.
 *     NULL autorisé (équipe sans leader OK).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pose_teams', function (Blueprint $t) {
            // Force InnoDB — le default MySQL local est MyISAM (qui ne
            // supporte pas les FK). Toutes les tables Panora utilisent InnoDB.
            $t->engine = 'InnoDB';
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 100);
            $t->string('color_slug', 20)->default('indigo');
            $t->foreignId('leader_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->unique('name');
            $t->unique('slug');
            // Garde-fou B : un user = leader d'1 équipe maximum.
            // NULL multiples autorisés par MySQL (équipes sans leader OK).
            $t->unique('leader_user_id', 'uniq_pose_teams_leader');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pose_teams');
    }
};
