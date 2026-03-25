<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── external_agencies — enrichissement ───────────────────
        Schema::table('external_agencies', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('contact');
            $table->string('city', 100)->nullable()->after('address');
            $table->text('notes')->nullable()->after('city');
            $table->boolean('is_active')->default(true)->after('notes');
            $table->softDeletes();
        });

        // ── external_panels — enrichissement ─────────────────────
        Schema::table('external_panels', function (Blueprint $table) {
            // Relations manquantes
            $table->foreignId('zone_id')
                  ->nullable()->after('commune_id')
                  ->constrained('zones')->onDelete('set null');
            $table->foreignId('format_id')
                  ->nullable()->after('zone_id')
                  ->constrained('panel_formats')->onDelete('set null');

            // Données affichage
            $table->boolean('is_lit')->default(false)->after('type');
            $table->unsignedBigInteger('daily_traffic')->nullable()->after('is_lit');
            $table->decimal('monthly_rate', 12, 2)->nullable()->after('daily_traffic');
            $table->text('zone_description')->nullable()->after('monthly_rate');

            // Disponibilité manuelle
            $table->enum('availability_status', [
                'disponible',
                'occupe',
                'a_verifier',
            ])->default('a_verifier')->after('zone_description');
            $table->date('available_from')->nullable()->after('availability_status');
            $table->date('available_until')->nullable()->after('available_from');

            $table->text('notes')->nullable()->after('available_until');
            $table->softDeletes();

            // Index performance
            $table->index('agency_id',            'idx_ext_panels_agency');
            $table->index('commune_id',            'idx_ext_panels_commune');
            $table->index('availability_status',   'idx_ext_panels_status');
        });
    }

    public function down(): void
    {
        Schema::table('external_panels', function (Blueprint $table) {
            $table->dropIndex('idx_ext_panels_agency');
            $table->dropIndex('idx_ext_panels_commune');
            $table->dropIndex('idx_ext_panels_status');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropConstrainedForeignId('format_id');
            $table->dropColumn([
                'is_lit', 'daily_traffic', 'monthly_rate', 'zone_description',
                'availability_status', 'available_from', 'available_until',
                'notes', 'deleted_at',
            ]);
        });

        Schema::table('external_agencies', function (Blueprint $table) {
            $table->dropColumn(['phone','city','notes','is_active','deleted_at']);
        });
    }
};