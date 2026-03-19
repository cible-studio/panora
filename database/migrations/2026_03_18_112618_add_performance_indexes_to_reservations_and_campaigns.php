<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->index('status',     'idx_reservations_status');
            $table->index('client_id',  'idx_reservations_client');
            $table->index('user_id',    'idx_reservations_user');
            $table->index(['start_date', 'end_date'], 'idx_reservations_dates');
            $table->index(['status', 'end_date'],     'idx_reservations_status_end');
            $table->index('created_at', 'idx_reservations_created');
        });

        Schema::table('reservation_panels', function (Blueprint $table) {
            $table->index('panel_id',       'idx_rp_panel');
            $table->index('reservation_id', 'idx_rp_reservation');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->index('status',                  'idx_campaigns_status');
            $table->index('client_id',               'idx_campaigns_client');
            $table->index(['status', 'end_date'],    'idx_campaigns_status_end');
            $table->index(['client_id', 'name'],     'idx_campaigns_client_name');
        });

        Schema::table('panels', function (Blueprint $table) {
            $table->index('status',    'idx_panels_status');
            $table->index('format_id', 'idx_panels_format');
            $table->index('zone_id',   'idx_panels_zone');
        });

        Schema::table('campaign_panels', function (Blueprint $table) {
            $table->index('campaign_id', 'idx_cp_campaign');
            $table->index('panel_id',    'idx_cp_panel');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_panels', function (Blueprint $table) {
            $table->dropIndex('idx_cp_campaign');
            $table->dropIndex('idx_cp_panel');
        });
        Schema::table('panels', function (Blueprint $table) {
            $table->dropIndex('idx_panels_status');
            $table->dropIndex('idx_panels_format');
            $table->dropIndex('idx_panels_zone');
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_campaigns_status');
            $table->dropIndex('idx_campaigns_client');
            $table->dropIndex('idx_campaigns_status_end');
            $table->dropIndex('idx_campaigns_client_name');
        });
        Schema::table('reservation_panels', function (Blueprint $table) {
            $table->dropIndex('idx_rp_panel');
            $table->dropIndex('idx_rp_reservation');
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_status');
            $table->dropIndex('idx_reservations_client');
            $table->dropIndex('idx_reservations_user');
            $table->dropIndex('idx_reservations_dates');
            $table->dropIndex('idx_reservations_status_end');
            $table->dropIndex('idx_reservations_created');
        });
    }
};