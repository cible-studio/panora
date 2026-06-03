<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande de changement de dates par le client lors d'une proposition.
 *
 * Le client peut demander un décalage de période depuis sa page de
 * proposition (ex: "je préférerais 15/07 → 30/07 au lieu de 01/07 → 15/07").
 * La réservation reste EN_ATTENTE, on stocke juste les valeurs souhaitées.
 * L'admin voit un bandeau dédié et peut accepter (les dates remplacent
 * les actuelles) ou refuser (les champs sont effacés).
 *
 * Boot-safe : colonnes nullable + idempotent + reversible.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'requested_start_date')) {
                $table->date('requested_start_date')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('reservations', 'requested_end_date')) {
                $table->date('requested_end_date')->nullable()->after('requested_start_date');
            }
            if (!Schema::hasColumn('reservations', 'date_change_note')) {
                $table->text('date_change_note')->nullable()->after('requested_end_date');
            }
            if (!Schema::hasColumn('reservations', 'date_change_requested_at')) {
                $table->timestamp('date_change_requested_at')->nullable()->after('date_change_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            foreach ([
                'requested_start_date',
                'requested_end_date',
                'date_change_note',
                'date_change_requested_at',
            ] as $col) {
                if (Schema::hasColumn('reservations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
