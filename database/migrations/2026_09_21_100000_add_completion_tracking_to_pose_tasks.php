<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité de la complétion d'une PoseTask.
 *
 * Contexte (feedback user 2026-09-21, urgent) : quand le media planner
 * ajoute manuellement une pige depuis /admin/piges/create, la PoseTask
 * correspondante restait en statut PLANNED → la tâche continuait
 * d'apparaître comme « à faire » dans l'espace technicien, alors que la
 * preuve de pose existait déjà. Source de confusion pour les techs.
 *
 * Nouvelle règle : toute pige créée clôture la PoseTask, peu importe
 * qui l'a uploadée. Ces 2 colonnes permettent d'afficher « Fait par X »
 * côté espace tech et de distinguer une complétion terrain d'une
 * complétion administrative.
 *
 * Migration ADDITIVE (règle N°3 Panora) : colonnes nullable, aucune
 * donnée existante touchée, aucun comportement cassé si non peuplées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('pose_tasks', 'completed_by_user_id')) {
                $table->foreignId('completed_by_user_id')
                    ->nullable()
                    ->after('done_at')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Utilisateur ayant déclenché la complétion (upload pige ou markDone)');
            }

            if (!Schema::hasColumn('pose_tasks', 'completed_source')) {
                // 'tech'  → complétée depuis l'espace technicien (upload pige
                //           via le lien public ou bouton « Marquer terminée »)
                // 'admin' → complétée par un MP/admin qui a ajouté la pige
                //           manuellement depuis le back-office
                $table->string('completed_source', 20)
                    ->nullable()
                    ->after('completed_by_user_id')
                    ->comment('Origine de la complétion : tech | admin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('pose_tasks', 'completed_by_user_id')) {
                $table->dropConstrainedForeignId('completed_by_user_id');
            }
            if (Schema::hasColumn('pose_tasks', 'completed_source')) {
                $table->dropColumn('completed_source');
            }
        });
    }
};
