<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Auth fields — placés après email
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('remember_token');
            $table->boolean('must_change_password')->default(true)->after('password_changed_at');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'remember_token',
                'password_changed_at',
                'must_change_password',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};