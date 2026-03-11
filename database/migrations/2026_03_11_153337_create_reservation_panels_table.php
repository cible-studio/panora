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
        Schema::create('reservation_panels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('panel_id')
                ->constrained()->onDelete('cascade');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();

            // Index pour performances
            $table->index(['panel_id', 'reservation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_panels');
    }
};
