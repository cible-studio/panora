<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('proposition_token', 64)->nullable()->unique()->after('is_technical');
            $table->timestamp('proposition_sent_at')->nullable()->after('proposition_token');
            $table->timestamp('proposition_viewed_at')->nullable()->after('proposition_sent_at');
            $table->timestamp('proposition_expires_at')->nullable()->after('proposition_viewed_at');

            // Index pour recherche par token (très fréquente — accès page client)
            $table->index('proposition_token', 'idx_reservation_prop_token');
            // Index pour le job d'expiration (scan périodique)
            $table->index(['proposition_expires_at', 'status'], 'idx_proposition_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservation_prop_token');
            $table->dropIndex('idx_proposition_expiry');
            $table->dropColumn([
                'proposition_token',
                'proposition_sent_at',
                'proposition_viewed_at',
                'proposition_expires_at',
            ]);
        });
    }
};