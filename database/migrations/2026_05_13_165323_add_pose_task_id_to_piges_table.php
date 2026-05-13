<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lien explicite Pige → PoseTask.
 *
 * Avant : relation implicite via (panel_id + campaign_id). Fragile et
 * coûteux à reconstituer côté UI / observers.
 *
 * Après : FK directe piges.pose_task_id → pose_tasks.id (nullable car
 * certaines piges legacy n'ont pas de tâche associée — campagnes
 * partagées via campaign.pige_token avant l'unification du workflow).
 *
 * Backfill : pour chaque pige existante, on trouve la PoseTask qui
 * correspond (même panel_id + campaign_id) et on la rattache. Si rien
 * n'existe, on laisse NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pose_tasks était en MyISAM (legacy seed) → InnoDB requis pour
        // accepter une FK depuis piges. Conversion idempotente : pas d'op
        // si déjà InnoDB.
        try {
            DB::statement('ALTER TABLE pose_tasks ENGINE = InnoDB');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('migration.pose_tasks_engine_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Idempotent : si la colonne existe déjà (run partiel précédent),
        // on saute la création et on tente juste d'ajouter la FK manquante.
        if (!Schema::hasColumn('piges', 'pose_task_id')) {
            Schema::table('piges', function (Blueprint $table) {
                $table->foreignId('pose_task_id')
                    ->nullable()
                    ->after('campaign_id')
                    ->constrained('pose_tasks')
                    ->nullOnDelete()
                    ->comment('Lien explicite vers la tâche de pose qui a généré cette pige');
                $table->index('pose_task_id');
            });
        } else {
            // Colonne existante : ajouter la FK + index si manquants
            try {
                Schema::table('piges', function (Blueprint $table) {
                    $table->foreign('pose_task_id')
                        ->references('id')->on('pose_tasks')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('migration.fk_already_present', [
                    'error' => $e->getMessage(),
                ]);
            }
            try {
                Schema::table('piges', function (Blueprint $table) {
                    $table->index('pose_task_id');
                });
            } catch (\Throwable $e) {
                // index déjà là — ignore
            }
        }

        // Backfill : reconstituer les liens existants via (panel_id, campaign_id).
        // Cas typique : 1 PoseTask par (panel, campaign) — on prend la dernière
        // créée si plusieurs (rare mais possible avec re-pose).
        DB::statement("
            UPDATE piges p
            INNER JOIN (
                SELECT id, panel_id, campaign_id,
                       ROW_NUMBER() OVER (PARTITION BY panel_id, campaign_id ORDER BY created_at DESC) AS rn
                FROM pose_tasks
            ) pt ON pt.panel_id = p.panel_id
               AND pt.campaign_id = p.campaign_id
               AND pt.rn = 1
            SET p.pose_task_id = pt.id
            WHERE p.pose_task_id IS NULL
              AND p.campaign_id IS NOT NULL
              AND p.panel_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            $table->dropForeign(['pose_task_id']);
            $table->dropIndex(['pose_task_id']);
            $table->dropColumn('pose_task_id');
        });
    }
};
