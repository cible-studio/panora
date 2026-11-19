<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bon de commande : qui l'a SAISI dans Panora (2026-11-19).
 *
 * Règle validée par écrit le 19/11 : le BC est reçu par le commercial du
 * dossier (bc_recu_par = commercial assigné) ; quand l'admin ou le MP le
 * saisit à sa place, on garde la trace de qui a cliqué (bc_saisi_par).
 * Additive, colonne nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'bc_saisi_par')) {
                $table->unsignedBigInteger('bc_saisi_par')->nullable()->after('bc_recu_par');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'bc_saisi_par')) {
                $table->dropColumn('bc_saisi_par');
            }
        });
    }
};
