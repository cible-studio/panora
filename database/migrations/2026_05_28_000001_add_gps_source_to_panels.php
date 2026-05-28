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
 * IMPORTANT — pas de création d'index ici.
 * Cette migration tourne dans le CMD Docker (`php artisan migrate` au
 * démarrage du conteneur, avec trafic live). Un CREATE INDEX sur une table
 * chaude prend un metadata lock long → risque de blocage/timeout du boot →
 * boucle de redémarrage. On se limite donc à des ADD COLUMN nullable
 * (rapides, ALGORITHM=INSTANT) et idempotents. L'index (panel_id, status)
 * n'est pas requis (l'index FK piges_panel_id_foreign suffit, peu de piges
 * par panneau) ; s'il devient utile, l'ajouter dans une migration dédiée
 * jouée hors trafic.
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
    }

    public function down(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            foreach (['gps_source', 'gps_dispersion_flag', 'gps_computed_at'] as $col) {
                if (Schema::hasColumn('panels', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
