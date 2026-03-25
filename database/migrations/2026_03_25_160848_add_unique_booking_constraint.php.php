<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index de performance existant — vérifier qu'il existe
        Schema::table('reservation_panels', function (Blueprint $table) {
            // Empêcher le même panneau dans deux réservations actives
            // Note : contrainte applicative uniquement (SQL ne gère pas
            // les chevauchements de dates nativement sans trigger)
            // → On ajoute un index composite pour accélérer les lookups
            $table->index(['panel_id', 'reservation_id'], 'idx_res_panel_lookup');
        });

        // Contrainte CHECK sur reservations — dates cohérentes
        DB::statement('ALTER TABLE reservations 
            ADD CONSTRAINT chk_reservation_dates 
            CHECK (end_date > start_date)');

        // Contrainte CHECK sur reservation_panels — prix positifs
        DB::statement('ALTER TABLE reservation_panels 
            ADD CONSTRAINT chk_panel_prices 
            CHECK (unit_price >= 0 AND total_price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
