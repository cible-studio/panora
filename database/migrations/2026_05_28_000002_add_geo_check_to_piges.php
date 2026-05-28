<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anti-fraude géographique : verdict de cohérence entre le GPS de la pige
 * (où la photo a été prise) et le GPS du panneau (où il est censé être).
 * PUREMENT INFORMATIF — n'empêche aucun upload ni validation.
 *
 * - geo_distance_m : distance pige ↔ panneau en mètres (smallint unsigned,
 *   plafonné à 65535 par l'appelant).
 * - geo_check : verdict.
 *     'ok'           ≤ 50 m
 *     'warn'         50–200 m (à vérifier)
 *     'out'          > 200 m (hors-zone)
 *     'no_gps'       la pige n'a pas de GPS
 *     'no_panel_gps' le panneau n'est pas (encore) géolocalisé
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            if (!Schema::hasColumn('piges', 'geo_distance_m')) {
                $table->smallInteger('geo_distance_m', false, true)
                    ->nullable()
                    ->after('gps_lng');
            }
            if (!Schema::hasColumn('piges', 'geo_check')) {
                $table->enum('geo_check', ['ok', 'warn', 'out', 'no_gps', 'no_panel_gps'])
                    ->nullable()
                    ->after('geo_distance_m');
            }
        });

        // Index pour le filtre MP "lister les piges hors-zone / douteuses".
        Schema::table('piges', function (Blueprint $table) {
            try { $table->index('geo_check', 'idx_piges_geo_check'); }
            catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            try { $table->dropIndex('idx_piges_geo_check'); }
            catch (\Throwable) {}
            foreach (['geo_distance_m', 'geo_check'] as $col) {
                if (Schema::hasColumn('piges', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
