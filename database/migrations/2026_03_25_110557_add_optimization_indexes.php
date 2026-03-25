<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index sur reservations
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasIndex('reservations', 'idx_reservations_status_end_date')) {
                $table->index(['status', 'end_date'], 'idx_reservations_status_end_date');
            }
            if (!Schema::hasIndex('reservations', 'idx_reservations_status_start_date')) {
                $table->index(['status', 'start_date'], 'idx_reservations_status_start_date');
            }
            if (!Schema::hasIndex('reservations', 'idx_reservations_client_status')) {
                $table->index(['client_id', 'status'], 'idx_reservations_client_status');
            }
            if (!Schema::hasIndex('reservations', 'idx_reservations_created_at')) {
                $table->index('created_at', 'idx_reservations_created_at');
            }
            if (!Schema::hasIndex('reservations', 'idx_reservations_reference')) {
                $table->index('reference', 'idx_reservations_reference');
            }
        });
        
        // Index sur reservation_panels
        Schema::table('reservation_panels', function (Blueprint $table) {
            if (!Schema::hasIndex('reservation_panels', 'idx_rp_panel_reservation')) {
                $table->index(['panel_id', 'reservation_id'], 'idx_rp_panel_reservation');
            }
            if (!Schema::hasIndex('reservation_panels', 'idx_rp_panel_id')) {
                $table->index('panel_id', 'idx_rp_panel_id');
            }
        });
        
        // Index sur panels - avec vérification d'existence
        Schema::table('panels', function (Blueprint $table) {
            // Vérifier et créer l'index status s'il n'existe pas
            $indexes = DB::select("SHOW INDEX FROM panels WHERE Key_name = 'idx_panels_status'");
            if (empty($indexes)) {
                $table->index('status', 'idx_panels_status');
            }
            
            if (!Schema::hasIndex('panels', 'idx_panels_commune_status')) {
                $table->index(['commune_id', 'status'], 'idx_panels_commune_status');
            }
            
            if (!Schema::hasIndex('panels', 'idx_panels_reference')) {
                $table->index('reference', 'idx_panels_reference');
            }
            
            if (!Schema::hasIndex('panels', 'idx_panels_deleted_at')) {
                $table->index('deleted_at', 'idx_panels_deleted_at');
            }
        });
    }

    public function down(): void
    {
        // Supprimer les index (safe - on ne fait que drop, pas de vérification)
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_reservations_status_end_date');
            $table->dropIndexIfExists('idx_reservations_status_start_date');
            $table->dropIndexIfExists('idx_reservations_client_status');
            $table->dropIndexIfExists('idx_reservations_created_at');
            $table->dropIndexIfExists('idx_reservations_reference');
        });
        
        Schema::table('reservation_panels', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_rp_panel_reservation');
            $table->dropIndexIfExists('idx_rp_panel_id');
        });
        
        Schema::table('panels', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_panels_status');
            $table->dropIndexIfExists('idx_panels_commune_status');
            $table->dropIndexIfExists('idx_panels_reference');
            $table->dropIndexIfExists('idx_panels_deleted_at');
        });
    }
};