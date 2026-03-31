<?php
// database/migrations/2026_03_30_add_is_technical_to_reservations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'is_technical')) {
                $table->boolean('is_technical')
                      ->default(false)
                      ->after('confirmed_at')
                      ->comment('Réservation auto-créée par CampaignService');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumnIfExists('is_technical');
        });
    }
};