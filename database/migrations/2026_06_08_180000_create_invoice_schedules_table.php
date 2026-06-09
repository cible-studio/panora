<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Échéancier prévisionnel par facture — point 4 du prompt facturation.
 *
 * Une facture peut être réglée en N versements planifiés à l'avance :
 *   - Acompte 30% à la commande
 *   - Solde 70% à J+30
 *   - Ou mensualités libres (4 × 25%, etc.)
 *
 * Cette table sert d'engagement de paiement — le rapprochement avec
 * les versements réels (invoice_payments) se fait à l'enregistrement
 * du paiement via paid_at sur la ligne échéancier correspondante.
 *
 * Le RESTE À PAYER calculé reste basé sur invoice_payments
 * (source unique des règlements réels). Cet échéancier est PRÉVISIONNEL
 * — il sert au recouvrement (qui doit relancer, quand).
 *
 * Champ reminded_at : date du dernier rappel envoyé. Permet de ne pas
 * spammer le client (1 relance par échéance).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('label', 100)->nullable()
                ->comment('Ex: "Acompte 30%", "Solde", "Mensualité 1/3"');
            $table->unsignedTinyInteger('order_index')->default(0);
            $table->date('paid_at')->nullable()
                ->comment('Marqué quand un versement couvre cette échéance');
            $table->foreignId('paid_payment_id')->nullable()
                ->constrained('invoice_payments')->nullOnDelete()
                ->comment('Le versement qui a marqué cette échéance comme réglée');
            $table->date('reminded_at')->nullable()
                ->comment('Date dernier rappel envoyé au client (anti-spam)');
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'due_date']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_schedules');
    }
};
