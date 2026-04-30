<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : Ajout des champs de motif d'annulation sur la table reservations
 *
 * Commande : php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // ✅ Type de motif d'annulation
            $table->string('cancel_type', 50)
                ->nullable()
                ->after('confirmed_at')
                ->comment('Motif : client_demande, budget, concurrent, report, autre');

            // ✅ Description détaillée
            $table->text('cancel_reason')
                ->nullable()
                ->after('cancel_type')
                ->comment('Description du motif d\'annulation');

            // ✅ Date/heure d'annulation
            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('cancel_reason')
                ->comment('Date/heure de l\'annulation');

            // ✅ Utilisateur ayant annulé
            $table->unsignedBigInteger('cancelled_by')
                ->nullable()
                ->after('cancelled_at')
                ->comment('ID de l\'utilisateur ayant annulé la réservation');

            $table->foreign('cancelled_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Index pour statistiques d'annulation par type
            $table->index(['cancel_type', 'cancelled_at'], 'idx_reservations_cancel_stats');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex('idx_reservations_cancel_stats');
            $table->dropColumn(['cancel_type', 'cancel_reason', 'cancelled_at', 'cancelled_by']);
        });
    }
};
