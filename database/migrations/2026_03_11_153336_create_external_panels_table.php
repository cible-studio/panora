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
        Schema::create('external_panels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')
                ->constrained('external_agencies')
                ->onDelete('cascade');
            $table->foreignId('commune_id')
                ->constrained()->onDelete('cascade');
            $table->string('code_panneau');
            $table->string('designation');
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_panels');
    }
};
