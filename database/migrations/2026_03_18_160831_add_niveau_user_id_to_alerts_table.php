<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->enum('niveau', ['info', 'warning', 'danger'])
                  ->default('info')
                  ->after('type');
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('cascade')
                  ->after('niveau');
            $table->string('lien')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['niveau', 'user_id', 'lien']);
        });
    }
};
