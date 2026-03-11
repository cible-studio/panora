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
        Schema::create('campaign_panels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('panel_id')
                ->nullable()
                ->constrained()->onDelete('cascade');
            $table->foreignId('external_panel_id')
                ->nullable()
                ->constrained()->onDelete('cascade');
            $table->enum('type', ['interne', 'externe'])
                ->default('interne');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_panels');
    }
};
