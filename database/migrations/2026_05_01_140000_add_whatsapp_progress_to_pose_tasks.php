<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module WhatsApp + suivi temps réel des poses.
 *
 * Ajoute :
 *   - progress_percent (0-100)         : avancement actuel reporté par le technicien
 *   - estimated_minutes / real_minutes : SLA et durée réelle
 *   - started_at / done_at             : début effectif / fin effective
 *   - whatsapp_sent_at                 : date d'envoi de la notification WhatsApp
 *   - public_token (unique)            : permet l'accès mobile au technicien sans login
 *
 * `done_at` existait déjà dans la table — on ne le re-crée pas, on le laisse.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('progress_percent');
            $table->unsignedSmallInteger('real_minutes')->nullable()->after('estimated_minutes');
            $table->timestamp('started_at')->nullable()->after('real_minutes');
            // done_at existe déjà — pas re-créé
            $table->timestamp('whatsapp_sent_at')->nullable()->after('done_at');
            $table->string('public_token', 32)->nullable()->unique()->after('whatsapp_sent_at');

            // Index pour le polling de l'admin (filtre rapide sur en cours)
            $table->index(['status', 'progress_percent'], 'idx_pose_tasks_progress');
        });
    }

    public function down(): void
    {
        Schema::table('pose_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_pose_tasks_progress');
            $table->dropColumn([
                'progress_percent',
                'estimated_minutes',
                'real_minutes',
                'started_at',
                'whatsapp_sent_at',
                'public_token',
            ]);
        });
    }
};
