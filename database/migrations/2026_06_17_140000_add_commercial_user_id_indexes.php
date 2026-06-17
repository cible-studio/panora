<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 Performance Commerciale — index sur commercial_user_id.
 *
 * Justification : les dashboards commerciaux (leaderboard, drill par
 * commercial, KPIs filtrés) font des WHERE commercial_user_id = X très
 * fréquents. Sans index, scan full table sur campaigns/invoices.
 *
 * Sqlite-safe via Schema::hasIndex().
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('campaigns') && !Schema::hasIndex('campaigns', 'idx_campaigns_commercial')) {
            Schema::table('campaigns', function (Blueprint $t) {
                $t->index('commercial_user_id', 'idx_campaigns_commercial');
            });
        }
        if (Schema::hasTable('invoices') && !Schema::hasIndex('invoices', 'idx_invoices_commercial')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->index('commercial_user_id', 'idx_invoices_commercial');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasIndex('campaigns', 'idx_campaigns_commercial')) {
            Schema::table('campaigns', fn (Blueprint $t) => $t->dropIndex('idx_campaigns_commercial'));
        }
        if (Schema::hasTable('invoices') && Schema::hasIndex('invoices', 'idx_invoices_commercial')) {
            Schema::table('invoices', fn (Blueprint $t) => $t->dropIndex('idx_invoices_commercial'));
        }
    }
};
