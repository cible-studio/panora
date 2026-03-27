<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── external_agencies ────────────────────────────────
        Schema::table('external_agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('external_agencies', 'phone')) {
                $table->string('phone', 20)->nullable()->after('contact');
            }
            if (!Schema::hasColumn('external_agencies', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('external_agencies', 'notes')) {
                $table->text('notes')->nullable()->after('city');
            }
            if (!Schema::hasColumn('external_agencies', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
            if (!Schema::hasColumn('external_agencies', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ── external_panels ────────────────────────────────
        Schema::table('external_panels', function (Blueprint $table) {
            if (!Schema::hasColumn('external_panels', 'zone_id')) {
                $table->foreignId('zone_id')
                      ->nullable()->after('commune_id')
                      ->constrained('zones')->onDelete('set null');
            }
            if (!Schema::hasColumn('external_panels', 'format_id')) {
                $table->foreignId('format_id')
                      ->nullable()->after('zone_id')
                      ->constrained('panel_formats')->onDelete('set null');
            }
            if (!Schema::hasColumn('external_panels', 'is_lit')) {
                $table->boolean('is_lit')->default(false)->after('type');
            }
            if (!Schema::hasColumn('external_panels', 'daily_traffic')) {
                $table->unsignedBigInteger('daily_traffic')->nullable()->after('is_lit');
            }
            if (!Schema::hasColumn('external_panels', 'monthly_rate')) {
                $table->decimal('monthly_rate', 12, 2)->nullable()->after('daily_traffic');
            }
            if (!Schema::hasColumn('external_panels', 'zone_description')) {
                $table->text('zone_description')->nullable()->after('monthly_rate');
            }
            if (!Schema::hasColumn('external_panels', 'availability_status')) {
                $table->enum('availability_status', [
                    'disponible',
                    'occupe',
                    'a_verifier',
                ])->default('a_verifier')->after('zone_description');
            }
            if (!Schema::hasColumn('external_panels', 'available_from')) {
                $table->date('available_from')->nullable()->after('availability_status');
            }
            if (!Schema::hasColumn('external_panels', 'available_until')) {
                $table->date('available_until')->nullable()->after('available_from');
            }
            if (!Schema::hasColumn('external_panels', 'notes')) {
                $table->text('notes')->nullable()->after('available_until');
            }
            if (!Schema::hasColumn('external_panels', 'deleted_at')) {
                $table->softDeletes();
            }

            // Index performance
            if (!Schema::hasColumn('external_panels', 'idx_ext_panels_agency')) {
                $table->index('agency_id', 'idx_ext_panels_agency');
            }
            if (!Schema::hasColumn('external_panels', 'idx_ext_panels_commune')) {
                $table->index('commune_id', 'idx_ext_panels_commune');
            }
            if (!Schema::hasColumn('external_panels', 'idx_ext_panels_status')) {
                $table->index('availability_status', 'idx_ext_panels_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('external_panels', function (Blueprint $table) {
            $table->dropIndex('idx_ext_panels_agency');
            $table->dropIndex('idx_ext_panels_commune');
            $table->dropIndex('idx_ext_panels_status');
            if (Schema::hasColumn('external_panels', 'zone_id')) {
                $table->dropConstrainedForeignId('zone_id');
            }
            if (Schema::hasColumn('external_panels', 'format_id')) {
                $table->dropConstrainedForeignId('format_id');
            }
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
