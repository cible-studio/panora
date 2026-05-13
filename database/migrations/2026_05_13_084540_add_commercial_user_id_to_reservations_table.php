<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 12.3 — Flux "Soumettre au commercial".
 *
 * Ajoute le champ `commercial_user_id` sur reservations pour assigner
 * explicitement la proposition à un commercial. Permet de :
 *   - Cibler la notification email + alerte interne sur ce commercial.
 *   - Filtrer "Mes propositions à traiter" pour chaque commercial.
 *   - Tracer l'historique (qui a eu la main sur quel dossier).
 *
 * Nullable car le concept arrive après la création initiale du parc :
 *   - Réservations existantes restent à null (rétro-compat).
 *   - Modèle::resolveCommercialContact() retombe sur user_id (créateur) si null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('commercial_user_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->index('commercial_user_id', 'idx_resa_commercial');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['commercial_user_id']);
            $table->dropIndex('idx_resa_commercial');
            $table->dropColumn('commercial_user_id');
        });
    }
};
