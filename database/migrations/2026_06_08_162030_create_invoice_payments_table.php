<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versements multiples par facture (acompte + solde, mensualités, etc.).
 *
 * Le champ legacy invoices.paid_at reste pour rétrocompat affichage,
 * mais la VRAIE source devient l'agrégation des invoice_payments.
 *
 * Pour les factures historiques (avant cette migration) on backfill
 * un seul payment depuis paid_at + amount_ttc dans une migration
 * suivante (idempotente).
 *
 * Statut facture devient calculé :
 *   - 0 versement → 'non_payee'
 *   - somme < total → 'partielle'
 *   - somme >= total → 'soldee'
 * Le champ invoices.status reste posé manuellement (brouillon/envoyee/
 * annulee) mais 'payee' devient un statut DÉRIVÉ recalculé à chaque
 * versement.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('montant', 15, 2);
            $table->enum('mode', [
                'especes',
                'cheque',
                'virement',
                'mobile_money',
                'compensation', // p.ex. un avoir compensé
                'autre',
            ])->default('virement');
            $table->string('reference', 100)->nullable()
                ->comment('N° chèque, ID transaction, etc.');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
