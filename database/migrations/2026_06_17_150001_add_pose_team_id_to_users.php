<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lie un technicien à son équipe de pose (M2 Performance Technicien).
 *
 * - Nullable : un tech sans équipe est autorisé (cas Backfill : MP
 *   rattache manuellement).
 * - onDelete('set null') au niveau FK : si l'équipe est SUPPRIMÉE
 *   physiquement (rare — on utilise softDelete sinon), les techs
 *   deviennent simplement « sans équipe ».
 * - Index : leaderboard équipes fait des WHERE pose_team_id = X.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('pose_team_id')->nullable()->after('agent_code')
                ->constrained('pose_teams')->nullOnDelete();
            $t->index('pose_team_id', 'idx_users_pose_team');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'pose_team_id')) {
                $t->dropForeign(['pose_team_id']);
                $t->dropIndex('idx_users_pose_team');
                $t->dropColumn('pose_team_id');
            }
        });
    }
};
