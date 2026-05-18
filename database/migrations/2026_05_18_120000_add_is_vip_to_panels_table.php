<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            if (!Schema::hasColumn('panels', 'is_vip')) {
                $table->boolean('is_vip')->default(false)->after('is_lit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            if (Schema::hasColumn('panels', 'is_vip')) {
                $table->dropColumn('is_vip');
            }
        });
    }
};
