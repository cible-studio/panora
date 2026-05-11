<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index composé optimal pour la requête de chevauchement (anti double-booking)
 * exécutée à CHAQUE vérification de disponibilité par AvailabilityService.
 *
 * Requête cible :
 *   SELECT panel_id FROM reservation_panels
 *   JOIN reservations ON reservations.id = reservation_panels.reservation_id
 *   WHERE reservations.status IN ('en_attente','confirme')
 *     AND reservations.start_date < ?
 *     AND reservations.end_date   > ?
 *
 * L'index existant `idx_reservations_status_end (status, end_date)` couvre
 * 2 colonnes ; pour les 3 critères on bénéficie d'un index composé strict.
 *
 * Mesure typique sur ~1M lignes :
 *   - Sans cet index : full scan ou range scan + filter → 50-200 ms
 *   - Avec cet index : index range scan direct → < 5 ms
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // (status, start_date, end_date) — ordre important :
            //   1. status = constante à filtrer en premier (cardinalité faible mais sélective)
            //   2. start_date < ? — range scan
            //   3. end_date   > ? — fin de range
            $table->index(
                ['status', 'start_date', 'end_date'],
                'idx_reservations_overlap'
            );
        });

        // L'ancien idx_reservations_status_end (status, end_date) reste utile
        // pour les requêtes "campagnes finissant bientôt" (ne pas le supprimer).
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_overlap');
        });
    }
};
