<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commune_tax_payments', function (Blueprint $table) {
            $table->decimal('db_theorique', 14, 2)->default(0)->after('tm_theorique');
            $table->decimal('db_paye',      14, 2)->default(0)->after('tm_paye');
        });
    }

    public function down(): void
    {
        Schema::table('commune_tax_payments', function (Blueprint $table) {
            $table->dropColumn(['db_theorique', 'db_paye']);
        });
    }
};
