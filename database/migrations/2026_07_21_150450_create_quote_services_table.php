<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Services annexes du devis (impression, frais de pose, électricité, etc.)
 * Miroir de invoice_services — même logique de saisie libre libellé + prix HT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->string('label', 200);
            $table->unsignedBigInteger('prix_ht')->default(0);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index('quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_services');
    }
};
