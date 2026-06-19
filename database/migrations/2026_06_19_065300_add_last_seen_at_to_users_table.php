<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SM2b Lot 1.1 — Ajoute users.last_seen_at pour le dashboard admin live.
 *
 * Consommé par :
 *   - TechSpaceController::heartbeat() → stamp à chaque poll 20s du tech
 *   - AdminLiveDashboardService → "techs en ligne" = last_seen_at < 10 min
 *
 * Distincte de reservations_last_seen_at (autre usage, page réservations).
 *
 * IDX sur last_seen_at car les requêtes "techs actifs" filtrent dessus
 * sur 10 minutes — sans index, full scan de la table users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')
                      ->nullable()
                      ->after('reservations_last_seen_at')
                      ->comment('Dernière activité (heartbeat tech ou navigation admin)');
                $table->index('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $table->dropIndex(['last_seen_at']);
                $table->dropColumn('last_seen_at');
            }
        });
    }
};
