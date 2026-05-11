<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 2.2 — Réservation avec date de début décalée.
 * Permet d'enregistrer une date de début spécifique par panneau
 * quand celui-ci est encore occupé au moment de la réservation
 * mais se libère avant la fin de la période demandée.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservation_panels', function (Blueprint $table) {
            if (!Schema::hasColumn('reservation_panels', 'panel_start_date')) {
                $table->date('panel_start_date')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservation_panels', function (Blueprint $table) {
            if (Schema::hasColumn('reservation_panels', 'panel_start_date')) {
                $table->dropColumn('panel_start_date');
            }
        });
    }
};
