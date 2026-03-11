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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('client_id')
                ->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained()->onDelete('set null');
            $table->foreignId('created_by')
                ->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tva', 5, 2)->default(18.00);
            $table->decimal('amount_ttc', 15, 2)->default(0);
            $table->date('issued_at');
            $table->date('paid_at')->nullable();
            $table->enum('status', [
                'brouillon',
                'envoyee',
                'payee',
                'annulee'
            ])->default('brouillon');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
