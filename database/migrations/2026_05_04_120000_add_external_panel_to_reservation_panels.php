<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Étend reservation_panels pour supporter les panneaux de régies externes.
 *
 *  - external_panel_id (nullable)  → FK vers external_panels.id
 *  - source enum('interne','externe') → discriminateur (cohérent avec campaign_panels)
 *  - panel_id devient NULLABLE      → permet une ligne purement externe
 *
 * Compatibilité ascendante :
 *  - Toutes les lignes existantes reçoivent source='interne' (défaut).
 *  - Les modèles/contrôleurs continuent à fonctionner sans modification
 *    via la relation Reservation::panels() qui scope wherePivot('source','interne').
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservation_panels', function (Blueprint $table) {
            if (!Schema::hasColumn('reservation_panels', 'external_panel_id')) {
                $table->foreignId('external_panel_id')
                    ->nullable()
                    ->after('panel_id')
                    ->constrained('external_panels')
                    ->onDelete('cascade');
            }
            if (!Schema::hasColumn('reservation_panels', 'source')) {
                $table->enum('source', ['interne', 'externe'])
                    ->default('interne')
                    ->after('external_panel_id');
            }
        });

        // panel_id doit pouvoir être NULL pour les lignes externes pures.
        // Laravel a besoin de doctrine/dbal pour ->change() sur certaines
        // versions ; on passe par du SQL brut pour rester compatible.
        try {
            DB::statement('ALTER TABLE reservation_panels MODIFY panel_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Si la colonne est déjà nullable, ignorer.
        }

        // Index utile pour les jointures de l'AvailabilityService côté externes
        Schema::table('reservation_panels', function (Blueprint $table) {
            if (!array_key_exists('idx_resp_external', collect(Schema::getIndexes('reservation_panels'))->keyBy('name')->all())) {
                try {
                    $table->index(['external_panel_id', 'reservation_id'], 'idx_resp_external');
                } catch (\Throwable $e) {
                    // Index pré-existant — ignorer
                }
            }
        });

        // Backfill défensif : toutes les lignes héritées sont des internes.
        DB::table('reservation_panels')->whereNull('source')->update(['source' => 'interne']);
    }

    public function down(): void
    {
        Schema::table('reservation_panels', function (Blueprint $table) {
            try { $table->dropIndex('idx_resp_external'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('reservation_panels', 'external_panel_id')) {
                $table->dropForeign(['external_panel_id']);
                $table->dropColumn('external_panel_id');
            }
            if (Schema::hasColumn('reservation_panels', 'source')) {
                $table->dropColumn('source');
            }
        });

        // Retour à NOT NULL — on ne peut le faire que si toutes les lignes
        // restantes ont un panel_id (c'est le cas après suppression des externes).
        try {
            DB::statement('ALTER TABLE reservation_panels MODIFY panel_id BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {}
    }
};
