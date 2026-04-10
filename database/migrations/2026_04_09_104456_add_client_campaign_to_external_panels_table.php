<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('external_panels', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null')->after('agency_id');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null')->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('external_panels', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['client_id', 'campaign_id']);
        });
    }
};
