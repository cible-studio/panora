<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()->after('client_id')
                    ->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('campaigns', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()->after('user_id')
                    ->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};