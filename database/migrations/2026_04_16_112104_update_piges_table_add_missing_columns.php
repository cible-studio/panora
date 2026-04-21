<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piges', function (Blueprint $table) {

            // Ajouter photo_thumb
            if (!Schema::hasColumn('piges', 'photo_thumb')) {
                $table->string('photo_thumb')->nullable()->after('photo_path');
            }

            // Rendre user_id nullable
            $table->foreignId('user_id')->nullable()->change();

            // Rendre taken_at nullable
            $table->timestamp('taken_at')->nullable()->change();

            // Modifier status (varchar → enum)
            $table->enum('status', ['en_attente', 'verifie', 'rejete'])
                  ->default('en_attente')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('piges', function (Blueprint $table) {

            $table->dropColumn('photo_thumb');

            $table->string('status')->default('en_attente')->change();

            // (optionnel rollback)
            $table->timestamp('taken_at')->nullable(false)->change();
        });
    }
};
