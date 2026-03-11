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
        Schema::create('pose_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')->onDelete('set null');
            $table->string('team_name', 50)->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('done_at')->nullable();
            $table->enum('status', [
                'planifiee',
                'en_cours',
                'realisee',
                'annulee'
            ])->default('planifiee');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pose_tasks');
    }
};
