<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * add_arrived_at_to_pose_tasks
 *
 * Feedback patronne 2026-07-06 : le SLA "temps de pose" actuel inclut
 * le TRAJET du tech vers le site, ce qui le fausse (2h de route
 * Abidjan → Bouaké + 30 min de pose = "2h30 de pose" dans les stats).
 *
 * Nouvelle sémantique métier validée :
 *   started_at (existant) = clic "Y aller" — tech démarre le sprint
 *   arrived_at (nouveau)  = clic "50% Arrivé sur place" — tech est sur site
 *   done_at    (existant) = upload photo — pose terminée
 *
 * KPI "temps de pose" (real_minutes) va basculer sur done_at - arrived_at
 * pour ne compter QUE le temps effectivement passé sur le site (hors
 * trajet). Le KPI "réactivité" (started_at - created_at) reste inchangé.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('started_at');
            $table->index('arrived_at');
        });
    }

    public function down(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->dropIndex(['arrived_at']);
            $table->dropColumn('arrived_at');
        });
    }
};
