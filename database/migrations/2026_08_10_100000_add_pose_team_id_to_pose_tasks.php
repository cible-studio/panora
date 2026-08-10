<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribution du mérite pose : solo vs équipe (2026-08-10).
 *
 * Contexte métier (validation user 2026-08-10) : jusqu'ici, la colonne
 * `team_name` VARCHAR sur pose_tasks était un simple label snapshot,
 * sans lien avec `pose_teams`. Résultat : le rapport "Performance
 * équipes" sommait aveuglément les KPIs des membres de chaque équipe,
 * ce qui comptait pour l'équipe même les poses solo faites par ses
 * membres. Bug visible sur /admin/performance/equipes : équipe
 * Abengourou affichait "2 poses réalisées" alors qu'aucune pose ne
 * lui était explicitement attribuée — c'étaient les 2 poses solo de
 * David (membre) qui remontaient à tort.
 *
 * Modèle après cette migration :
 *   - pose_team_id NULL  → pose SOLO (crédit individuel du tech)
 *   - pose_team_id = X   → pose D'ÉQUIPE (crédit collectif équipe X,
 *                          même si le tech qui a physiquement pigé
 *                          est identifié via `piges.user_id`)
 *
 * Une seule source de vérité : les rapports se filtrent sur la
 * présence/absence de `pose_team_id`. `team_name` reste conservé
 * comme snapshot historique (comparable aux snapshots taxes), mais
 * n'est plus le driver des calculs.
 *
 * Additif uniquement (nullable, aucune donnée cassée) : les poses
 * existantes ont `pose_team_id = NULL` → toutes solo par défaut, aucune
 * régression. Le backfill éventuel se fait via la command
 * `posetasks:backfill-team-ids` (matching team_name → pose_teams.name).
 *
 * Index composite (pose_team_id, done_at) : optimise les rapports
 * équipe qui filtrent par équipe + période (usage principal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->foreignId('pose_team_id')
                ->nullable()
                ->after('team_name')
                ->constrained('pose_teams')
                ->nullOnDelete();

            // Requête typique : "poses de l'équipe X sur la période P"
            // (utilisée dans TechnicianPerformanceService::byTeam).
            $table->index(['pose_team_id', 'done_at'], 'pose_tasks_team_done_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->dropIndex('pose_tasks_team_done_idx');
            $table->dropForeign(['pose_team_id']);
            $table->dropColumn('pose_team_id');
        });
    }
};
