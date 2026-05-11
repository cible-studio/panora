<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Suppression du statut POSE — il dupliquait la sémantique des
     * PoseTask. Les campagnes en POSE redeviennent ACTIF (la pose
     * terrain continue d'être suivie via les PoseTask).
     */
    public function up(): void
    {
        // 1. Migrer les données existantes
        DB::table('campaigns')
            ->where('status', 'pose')
            ->update(['status' => 'actif']);

        // 2. Retirer 'pose' de l'enum SQL
        DB::statement("ALTER TABLE campaigns
            MODIFY COLUMN status ENUM('planifie','actif','pause','termine','annule')
            NOT NULL DEFAULT 'actif'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE campaigns
            MODIFY COLUMN status ENUM('planifie','actif','pose','pause','termine','annule')
            NOT NULL DEFAULT 'actif'");
    }
};
