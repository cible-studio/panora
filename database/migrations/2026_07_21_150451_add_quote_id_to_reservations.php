<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien bidirectionnel Reservation ↔ Quote.
 *
 * Quand un devis est accepté et converti, on crée une Reservation type=ferme.
 * On garde une trace du devis d'origine côté résa (via reservations.quote_id)
 * ET côté devis (via quotes.converted_reservation_id — déjà en place).
 *
 * Idempotente : hasColumn check pour permettre re-run sans erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'quote_id')) {
                // Pas de ->after() : reservations n'a pas de campaign_id
                // (c'est campaigns qui a reservation_id). Colonne ajoutée
                // en fin de table, ordre non significatif.
                $table->foreignId('quote_id')->nullable()
                      ->constrained('quotes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'quote_id')) {
                $table->dropForeign(['quote_id']);
                $table->dropColumn('quote_id');
            }
        });
    }
};
