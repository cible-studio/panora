<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute commercial_user_id aux campagnes — aligné avec reservations.
 *
 * - Le créateur (user_id) reste la trace de qui a saisi la campagne.
 * - Le commercial assigné (commercial_user_id) est responsable du suivi
 *   relation client + notifications email.
 * - Si null → fallback sur user_id (rétro-compat).
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('commercial_user_id')->nullable()->after('user_id')
                  ->constrained('users')->nullOnDelete();
            $table->index('commercial_user_id', 'campaigns_commercial_idx');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('campaigns_commercial_idx');
            $table->dropForeign(['commercial_user_id']);
            $table->dropColumn('commercial_user_id');
        });
    }
};
