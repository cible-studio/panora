<?php
// database/migrations/2024_03_01_000001_add_reservations_last_seen_at_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vérification si la colonne n'existe pas déjà
            if (!Schema::hasColumn('users', 'reservations_last_seen_at')) {
                $table->timestamp('reservations_last_seen_at')
                      ->nullable()
                      ->after('remember_token')
                      ->comment('Dernière consultation de la liste des réservations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('reservations_last_seen_at');
        });
    }
};