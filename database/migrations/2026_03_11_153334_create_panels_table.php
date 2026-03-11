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
        Schema::create('panels', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('name', 150);
            $table->foreignId('commune_id')
                ->constrained()->onDelete('restrict');
            $table->foreignId('zone_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('format_id')
                ->constrained('panel_formats')
                ->onDelete('restrict');
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('panel_categories')
                ->onDelete('set null');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', [
                'libre',
                'occupe',
                'option',
                'confirme',
                'maintenance'
            ])->default('libre');
            $table->boolean('is_lit')->default(false);
            $table->decimal('monthly_rate', 15, 2)->default(0);
            $table->integer('daily_traffic')->nullable();
            $table->enum('maintenance_status', [
                'bon',
                'moyen',
                'defaillant'
            ])->default('bon');
            $table->text('zone_description')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Index pour performances
            $table->index(['commune_id', 'status']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panels');
    }
};
