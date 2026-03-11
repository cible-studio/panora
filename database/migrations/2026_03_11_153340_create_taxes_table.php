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
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')
                ->constrained()->onDelete('cascade');
            $table->year('year');
            $table->enum('type', ['odp', 'tm']);
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->enum('status', [
                'en_attente',
                'payee',
                'en_retard'
            ])->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
