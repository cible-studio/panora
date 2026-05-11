<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Une maintenance "Rouvrir" crée une nouvelle ligne reliée à la
     * maintenance résolue/annulée précédente via parent_maintenance_id.
     * Ça permet de tracer les récurrences (panneau qui re-tombe en panne
     * deux fois pour la même raison) sans dénormaliser ni mélanger
     * l'historique d'une fiche close.
     */
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('parent_maintenance_id')
                ->nullable()
                ->after('panel_id')
                ->constrained('maintenances')
                ->nullOnDelete();
            $table->index('parent_maintenance_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropForeign(['parent_maintenance_id']);
            $table->dropIndex(['parent_maintenance_id']);
            $table->dropColumn('parent_maintenance_id');
        });
    }
};
