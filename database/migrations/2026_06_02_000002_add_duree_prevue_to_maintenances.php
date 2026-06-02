<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durée prévue d'une maintenance — l'admin la saisit à la mise en maintenance.
 *
 *  - duree_prevue_jours : nombre de jours estimés d'indisponibilité (1-365).
 *  - date_fin_prevue    : date dérivée (date_signalement + duree_prevue_jours)
 *                         pour requêtes rapides "panneaux qui reviennent dans
 *                         N jours" sans calcul applicatif.
 *
 * Sert à informer le client : combien de temps son panneau est down + quand
 * il revient. Le client n'a pas espace ? Email automatique.
 *
 * Boot-safe : colonnes nullable, idempotent, aucun index au boot.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenances', 'duree_prevue_jours')) {
                $table->unsignedSmallInteger('duree_prevue_jours')
                    ->nullable()
                    ->after('priorite');
            }
            if (!Schema::hasColumn('maintenances', 'date_fin_prevue')) {
                $table->date('date_fin_prevue')
                    ->nullable()
                    ->after('date_signalement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            foreach (['duree_prevue_jours', 'date_fin_prevue'] as $col) {
                if (Schema::hasColumn('maintenances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
