<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('reservation_id')
                    ->constrained('users')
                    ->onDelete('set null');
            }
            if (! Schema::hasColumn('campaigns', 'type')) {
                $table->string('type', 10)->default('ferme')->after('status')
                    ->comment('option ou ferme');
            }
        });
    }
    
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'updated_by');
            $table->dropColumn(['updated_by', 'type']);
        });
    }
};
