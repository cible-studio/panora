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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('technicien_id')
                ->nullable()
                ->constrained('users')->onDelete('set null');
            $table->foreignId('signale_par')
                ->constrained('users')->onDelete('cascade');
            $table->string('type_panne');
            $table->enum('priorite', [
                'faible',
                'normale',
                'haute',
                'urgente'
            ])->default('normale');
            $table->enum('statut', [
                'signale',
                'en_cours',
                'resolu',
                'annule'
            ])->default('signale');
            $table->date('date_signalement');
            $table->date('date_resolution')->nullable();
            $table->text('description')->nullable();
            $table->text('solution')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
