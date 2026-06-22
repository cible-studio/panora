<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-appartenance technicien ↔ équipe (refonte 2026-06-19).
 *
 * Contexte métier (validé par patronne) : un technicien terrain peut
 * intervenir dans plusieurs équipes selon les chantiers. On passe donc
 * de 1-to-N (users.pose_team_id) à N-to-N (table pivot dédiée).
 *
 * Stratégie :
 *   1. Création de la pivot pose_team_user (user_id, pose_team_id).
 *   2. Copie des données existantes depuis users.pose_team_id (les
 *      rattachements actuels sont préservés au franc près).
 *   3. users.pose_team_id RESTE en place pour rétro-compat lecture des
 *      vues qui ne sont pas encore migrées (nettoyage dans une migration
 *      ultérieure une fois la transition complète).
 *
 * Règles métier préservées :
 *   - Leader : un tech peut être leader d'UNE SEULE équipe (champ
 *     pose_teams.leader_user_id inchangé, validation aussi).
 *   - team_name sur pose_tasks : reste obligatoire à la création
 *     (une pose appartient à 1 équipe précise, indépendamment de
 *     l'équipe du tech qui la réalise).
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Pivot N-to-N — sans FK explicite pour rester portable entre
        //    InnoDB (prod) et MyISAM (certains envs locaux historiques —
        //    cf. docs/TECHNICAL_DEBT.md). La cohérence est garantie par
        //    cascadeOnDelete au niveau Eloquent (PoseTeam et User detach
        //    leurs membres au delete).
        Schema::create('pose_team_user', function (Blueprint $t) {
            $t->unsignedBigInteger('pose_team_id');
            $t->unsignedBigInteger('user_id');
            $t->timestamp('joined_at')->useCurrent();
            // PK composite : un même couple (tech, équipe) ne peut exister qu'une fois.
            $t->primary(['pose_team_id', 'user_id']);
            // Index inverse pour les requêtes "équipes d'un tech".
            $t->index('user_id', 'idx_pose_team_user_user');
        });

        // Tentative d'ajout des FK uniquement si les 2 tables référencées sont
        // en InnoDB (silencieux sinon — environnements locaux MyISAM).
        try {
            $usersEngine = DB::selectOne("SHOW TABLE STATUS WHERE Name = 'users'")?->Engine ?? null;
            $teamsEngine = DB::selectOne("SHOW TABLE STATUS WHERE Name = 'pose_teams'")?->Engine ?? null;
            if ($usersEngine === 'InnoDB' && $teamsEngine === 'InnoDB') {
                Schema::table('pose_team_user', function (Blueprint $t) {
                    $t->foreign('pose_team_id')->references('id')->on('pose_teams')->cascadeOnDelete();
                    $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            }
        } catch (\Throwable $e) {
            // Best-effort, on ne bloque pas la migration si l'ajout des FK échoue.
        }

        // 2. Copie des données existantes depuis users.pose_team_id.
        //    Idempotent : si on relance la migration après rollback partiel,
        //    INSERT IGNORE évite les doublons (sur MySQL/MariaDB).
        if (Schema::hasColumn('users', 'pose_team_id')) {
            $rows = DB::table('users')
                ->whereNotNull('pose_team_id')
                ->select('id as user_id', 'pose_team_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('pose_team_user')->updateOrInsert(
                    ['pose_team_id' => $row->pose_team_id, 'user_id' => $row->user_id],
                    ['joined_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pose_team_user');
        // users.pose_team_id n'est PAS touchée ici (gérée par sa propre migration).
    }
};
