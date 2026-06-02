<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements terrain — workflow "voir → traiter / mettre en maintenance".
 *
 * Étend pose_task_actions pour distinguer les signalements actifs des
 * signalements déjà traités par un admin, et tracer l'action de résolution
 * (mise en maintenance vs simple clôture).
 *
 *  - resolved_at        : quand l'admin a traité (NULL = à traiter)
 *  - resolved_by        : qui (user_id, nullable)
 *  - resolution_action  : 'maintenance' | 'dismissed' | NULL
 *  - maintenance_id     : si action=maintenance, FK vers la maintenance créée
 *
 * Boot-safe : ADD COLUMN nullable uniquement, idempotent via hasColumn.
 * AUCUN index/contrainte au boot — la table peut être chaude.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pose_task_actions', function (Blueprint $table) {
            if (!Schema::hasColumn('pose_task_actions', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('created_at');
            }
            if (!Schema::hasColumn('pose_task_actions', 'resolved_by')) {
                $table->unsignedBigInteger('resolved_by')->nullable()->after('resolved_at');
            }
            if (!Schema::hasColumn('pose_task_actions', 'resolution_action')) {
                $table->enum('resolution_action', ['maintenance', 'dismissed'])
                    ->nullable()->after('resolved_by');
            }
            if (!Schema::hasColumn('pose_task_actions', 'maintenance_id')) {
                $table->unsignedBigInteger('maintenance_id')->nullable()->after('resolution_action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pose_task_actions', function (Blueprint $table) {
            foreach (['resolved_at', 'resolved_by', 'resolution_action', 'maintenance_id'] as $col) {
                if (Schema::hasColumn('pose_task_actions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
