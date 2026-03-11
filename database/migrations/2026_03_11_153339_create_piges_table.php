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
        Schema::create('piges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('user_id')
                ->constrained()->onDelete('cascade');
            $table->string('photo_path');
            $table->timestamp('taken_at');
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piges');
    }
};
