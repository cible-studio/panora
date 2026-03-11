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
        Schema::create('panel_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->decimal('width', 6, 2)->nullable();
            $table->decimal('height', 6, 2)->nullable();
            $table->decimal('surface', 8, 2)->nullable();
            $table->string('print_type', 80)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_formats');
    }
};
