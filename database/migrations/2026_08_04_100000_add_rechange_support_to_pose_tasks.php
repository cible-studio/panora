<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-poses par campagne × panneau (2026-08-04).
 *
 * Contexte métier : sur une campagne longue (ex : 12 mois), le client
 * change périodiquement de créa. À chaque changement, une nouvelle
 * pose physique doit être réalisée sur le même panneau. Aujourd'hui
 * le code bloque la création d'une seconde PoseTask sur (campagne,
 * panneau) tant que la précédente n'est pas annulée.
 *
 * 3 colonnes ajoutées (toutes nullable additives — aucune pose
 * existante n'est cassée) :
 *
 *   - pose_kind ENUM('initial','rechange','retouche') DEFAULT 'initial'
 *       Type de pose. Toutes les poses existantes deviennent 'initial'
 *       par défaut. Les rechanges suivants sont créés via un nouveau
 *       workflow dédié (PoseService::createRechange).
 *
 *   - replaces_pose_task_id BIGINT NULLABLE FK self
 *       Pointe vers la pose PRÉCÉDENTE que celle-ci vient remplacer.
 *       NULL sur les poses initiales. Renseigné pour toute rechange.
 *       Permet de reconstituer la chaîne complète des poses.
 *
 *   - replaced_at TIMESTAMP NULLABLE
 *       Posé automatiquement par le service quand un rechange est
 *       créé sur cette pose : équivalent d'un "décapage intermédiaire"
 *       implicite (l'ancienne affiche a bien été retirée avant la
 *       nouvelle). L'ancienne pose garde son statut 'realisee' pour
 *       préserver l'audit — replaced_at signale juste qu'elle n'est
 *       plus la pose active.
 *
 * Le décapage FINAL de fin de campagne reste sur
 * campaign_panels.decapped_at (1 seul événement, inchangé).
 *
 * Piges : la FK pose_tasks.id ↔ piges.pose_task_id existe déjà
 * (migration 2026-05-13). L'exploitation dans les vues est activée
 * dans un chantier séparé (relation PoseTask::piges).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            // ENUM PHP-side plutôt que enum SQL pour éviter les migrations
            // ALTER lourdes en cas d'ajout d'un nouveau type de pose plus
            // tard (retouche_partielle, verification, etc.). VARCHAR 20
            // + check contrainte applicative dans PoseTaskKind enum.
            $table->string('pose_kind', 20)->default('initial')->after('status');

            $table->foreignId('replaces_pose_task_id')
                ->nullable()
                ->after('pose_kind')
                ->constrained('pose_tasks')
                ->nullOnDelete(); // si l'ancienne pose est purgée, la nouvelle survit sans FK cassée

            $table->timestamp('replaced_at')
                ->nullable()
                ->after('done_at');

            // Index pour retrouver rapidement la chaîne d'une pose
            // (SELECT * FROM pose_tasks WHERE replaces_pose_task_id = X)
            $table->index('replaces_pose_task_id', 'pose_tasks_replaces_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->dropIndex('pose_tasks_replaces_idx');
            $table->dropForeign(['replaces_pose_task_id']);
            $table->dropColumn(['pose_kind', 'replaces_pose_task_id', 'replaced_at']);
        });
    }
};
