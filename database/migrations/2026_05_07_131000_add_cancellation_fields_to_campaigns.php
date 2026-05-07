<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'cancellation_reason')) {
                $table->string('cancellation_reason', 50)->nullable()->after('notes');
            }
            if (!Schema::hasColumn('campaigns', 'cancellation_notes')) {
                $table->text('cancellation_notes')->nullable()->after('cancellation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancellation_notes']);
        });
    }
};
