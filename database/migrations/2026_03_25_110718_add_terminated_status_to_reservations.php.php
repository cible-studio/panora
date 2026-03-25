<?php
// database/migrations/2026_03_25_000002_add_terminated_status_to_reservations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modifier la colonne status pour accepter 'termine'
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('en_attente', 'confirme', 'refuse', 'annule', 'termine') NOT NULL DEFAULT 'en_attente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('en_attente', 'confirme', 'refuse', 'annule') NOT NULL DEFAULT 'en_attente'");
    }
};