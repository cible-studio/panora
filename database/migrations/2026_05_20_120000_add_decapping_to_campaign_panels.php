<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracking décappage (retrait du visuel) au niveau pivot campaign × panel.
     *
     * Workflow opérationnel :
     *   1. Campagne se termine (status='termine', end_date dans le passé)
     *   2. Le panneau attend d'être décappé physiquement sur le terrain
     *   3. Un technicien ou un admin appose la date de décappage effectif
     *   4. Si > 7j après end_date sans décappage → alerte "retard" dans le rapport
     *
     * Champs ajoutés aux deux pivots (campaign_panels + reservation_panels)
     * pour rester cohérent quel que soit le système (legacy/new) utilisé.
     */
    public function up(): void
    {
        Schema::table('campaign_panels', function (Blueprint $table) {
            $table->timestamp('decapped_at')->nullable()->after('type');
            $table->foreignId('decapped_by_user_id')->nullable()
                  ->after('decapped_at')
                  ->constrained('users')->nullOnDelete();
            $table->text('decap_notes')->nullable()->after('decapped_by_user_id');

            // Index pour le tableau de bord (queries WHERE decapped_at IS NULL …)
            $table->index('decapped_at', 'campaign_panels_decapped_at_idx');
        });

        Schema::table('reservation_panels', function (Blueprint $table) {
            $table->timestamp('decapped_at')->nullable()->after('total_price');
            $table->foreignId('decapped_by_user_id')->nullable()
                  ->after('decapped_at')
                  ->constrained('users')->nullOnDelete();
            $table->text('decap_notes')->nullable()->after('decapped_by_user_id');

            $table->index('decapped_at', 'reservation_panels_decapped_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_panels', function (Blueprint $table) {
            $table->dropIndex('campaign_panels_decapped_at_idx');
            $table->dropForeign(['decapped_by_user_id']);
            $table->dropColumn(['decapped_at', 'decapped_by_user_id', 'decap_notes']);
        });

        Schema::table('reservation_panels', function (Blueprint $table) {
            $table->dropIndex('reservation_panels_decapped_at_idx');
            $table->dropForeign(['decapped_by_user_id']);
            $table->dropColumn(['decapped_at', 'decapped_by_user_id', 'decap_notes']);
        });
    }
};
