<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des paiements de taxes communales (ODP + TM) par commune et par
 * période. 1 ligne = 1 (commune × type période × année × valeur période).
 *
 * period_type :
 *   - mensuel    → period_value = mois 1..12
 *   - trimestriel→ period_value = trimestre 1..4
 *   - annuel     → period_value = 0 (un seul enregistrement / année)
 *
 * On stocke les théoriques au moment du paiement pour traçabilité (si le
 * parc évolue plus tard, le montant payé reste cohérent avec l'instant
 * du paiement). Pour le calcul live, on utilise toujours TaxController
 * qui recalcule depuis les communes/panels actuels.
 */
return new class extends Migration {
    public function up(): void
    {
        // S'assure d'abord que les colonnes odp_rate/tm_rate sur communes
        // ont les bonnes contraintes (odp_rate déjà existant) et que tm_rate
        // a une default de 1000 (TM fixe nationale).
        Schema::table('communes', function (Blueprint $table) {
            // odp_rate existe déjà sans default — pas touché pour ne pas
            // écraser les tarifs actuels.
            // tm_rate existait avec default 0 → on patche les valeurs à
            // 1000 via le seeder (pas dans la migration pour rester
            // séparation responsabilités).
        });

        if (Schema::hasTable('commune_tax_payments')) return;

        Schema::create('commune_tax_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes')->cascadeOnDelete();

            // Période : mensuel / trimestriel / annuel
            $table->string('period_type', 20);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_value')->default(0); // 1..12, 1..4, ou 0 (annuel)

            // Théoriques snapshot au moment de l'enregistrement
            $table->decimal('odp_theorique', 14, 2)->default(0);
            $table->decimal('tm_theorique',  14, 2)->default(0);

            // Montants effectivement payés
            $table->decimal('odp_paye', 14, 2)->default(0);
            $table->decimal('tm_paye',  14, 2)->default(0);

            $table->date('paid_at')->nullable();

            // Attestation reçue de la mairie (preuve fiscale)
            $table->boolean('attestation_recue')->default(false);
            $table->date('attestation_date')->nullable();
            $table->string('attestation_path', 255)->nullable(); // PDF scan optionnel

            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // 1 entrée unique par (commune × période)
            $table->unique(['commune_id', 'period_type', 'period_year', 'period_value'], 'tax_payment_unique');
            $table->index(['period_year', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commune_tax_payments');
    }
};
