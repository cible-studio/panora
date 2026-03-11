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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('client_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('reservation_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', [
                'actif',
                'pose',
                'termine',
                'annule'
            ])->default('actif');
            $table->integer('total_panels')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
