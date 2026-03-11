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
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('demand_level', [
                'faible',
                'normale',
                'haute',
                'tres_haute'
            ])->default('normale');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
