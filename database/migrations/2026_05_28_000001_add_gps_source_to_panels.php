<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-géolocalisation des panneaux via piges validées.
 *
 * - gps_source : provenance des coordonnées lat/lng du panneau.
 *     'manual'           = saisi à la main par un admin (NE JAMAIS écraser)
 *     'pige_provisional' = déduit d'1 seule pige validée (à confirmer)
 *     'pige_confirmed'   = médiane de 2+ piges validées (fiable)
 *     NULL               = aucune coordonnée / origine inconnue (legacy)
 *
 * - gps_dispersion_flag : true si les piges du panneau divergent de plus
 *   du seuil (100 m) → "GPS incohérent, à vérifier" pour le media planner.
 *
 * - gps_computed_at : dernière fois que la médiane a été recalculée.
 *
 * Ajoute aussi l'index composite (panel_id, status) sur `piges` : c'est la
 * requête exacte de PanelGeoLocator::recomputeFromPiges() (piges validées
 * d'un panneau). Sans lui, MySQL filtre le statut en scan dans l'index FK.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            if (!Schema::hasColumn('panels', 'gps_source')) {
                $table->enum('gps_source', [
                    'manual',
                    'pige_provisional',
                    'pige_confirmed',
                ])->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('panels', 'gps_dispersion_flag')) {
                $table->boolean('gps_dispersion_flag')
                    ->default(false)
                    ->after('gps_source');
            }
            if (!Schema::hasColumn('panels', 'gps_computed_at')) {
                $table->timestamp('gps_computed_at')
                    ->nullable()
                    ->after('gps_dispersion_flag');
            }
        });

        // Index composite servant la requête de recompute (best-effort :
        // ignore si déjà présent, MySQL n'a pas de "CREATE INDEX IF NOT EXISTS").
        Schema::table('piges', function (Blueprint $table) {
            try { $table->index(['panel_id', 'status'], 'idx_piges_panel_status'); }
            catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {
            try { $table->dropIndex('idx_piges_panel_status'); }
            catch (\Throwable) {}
        });

        Schema::table('panels', function (Blueprint $table) {
            foreach (['gps_source', 'gps_dispersion_flag', 'gps_computed_at'] as $col) {
                if (Schema::hasColumn('panels', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
