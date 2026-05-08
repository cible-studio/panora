<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking des rappels de proposition (J+2 et J+5) — évite l'envoi multiple
 * sur plusieurs runs de la commande propositions:send-reminders.
 *
 * Reset attendu côté contrôleur quand on régénère un lien (envoyer/reinit).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'proposition_reminded_j2_at')) {
                $table->timestamp('proposition_reminded_j2_at')
                      ->nullable()
                      ->after('proposition_expires_at');
            }
            if (!Schema::hasColumn('reservations', 'proposition_reminded_j5_at')) {
                $table->timestamp('proposition_reminded_j5_at')
                      ->nullable()
                      ->after('proposition_reminded_j2_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'proposition_reminded_j2_at')) {
                $table->dropColumn('proposition_reminded_j2_at');
            }
            if (Schema::hasColumn('reservations', 'proposition_reminded_j5_at')) {
                $table->dropColumn('proposition_reminded_j5_at');
            }
        });
    }
};
