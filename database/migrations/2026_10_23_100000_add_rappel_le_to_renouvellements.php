<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renouvellements (2026-10-23) : date de rappel notée par le commercial
 * après un appel (« rappeler le … »). Reprise dans le mail du matin le
 * jour venu. Migration ADDITIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('renouvellements') && !Schema::hasColumn('renouvellements', 'rappel_le')) {
            Schema::table('renouvellements', function (Blueprint $table) {
                $table->date('rappel_le')->nullable()->after('statut');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('renouvellements', 'rappel_le')) {
            Schema::table('renouvellements', function (Blueprint $table) {
                $table->dropColumn('rappel_le');
            });
        }
    }
};
