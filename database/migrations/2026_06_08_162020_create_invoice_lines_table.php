<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes détaillées de facture — refonte FNE.
 *
 * Aujourd'hui invoices.amount est un montant HT global posé à la main.
 * À partir de maintenant, le montant HT est calculé comme la somme des
 * lignes (une ligne = un emplacement panneau pour une durée donnée).
 *
 * Chaque ligne porte :
 *   - les infos métier : PU HT mensuel, quantité (nb panneaux), durée
 *   - la dimension m² ET la commune au moment de la facturation
 *     (snapshot — si la commune est renommée ou le panneau supprimé,
 *     la facture reste correcte)
 *   - les taxes pré-calculées par ligne (TM, ODP) pour audit
 *
 * La référence panel_id reste nullable + onDelete('set null') pour
 * tolérer la suppression d'un panneau après facturation (la facture
 * est un document fiscal, elle ne doit jamais perdre ses lignes).
 *
 * snapshot_commune_name : indispensable pour le PDF longue durée.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('panels')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();

            // Snapshots métier (la facture reste lisible même si l'origine évolue)
            $table->string('designation', 200)
                ->comment('Ex: "ABG-001A — Abengourou rte gare UTI 4×3m"');
            $table->string('snapshot_commune_name', 100)->nullable();
            $table->decimal('dimension_m2', 8, 2)->default(0);

            // Pricing
            $table->decimal('pu_ht_mensuel', 15, 2)->default(0);
            $table->unsignedInteger('quantite')->default(1)
                ->comment('nb_panneaux dans la ligne — 1 panneau = 1 ligne sauf groupage');
            $table->decimal('duree_mois', 6, 2)->default(1)
                ->comment('Décimal pour gérer 0.5 / 1.5 mois (règle CIBLE)');

            // Calculé (stocké pour ne pas recalculer à chaque affichage,
            // et pour figer le montant historique en cas de changement
            // de tarif ODP ultérieur)
            $table->decimal('montant_ht_ligne', 15, 2)->default(0);
            $table->decimal('odp_rate_applique', 15, 2)->default(0)
                ->comment('Snapshot du tarif ODP au moment de la facturation');
            $table->decimal('tm_rate_applique', 15, 2)->default(1000);
            $table->decimal('odp_ligne', 15, 2)->default(0);
            $table->decimal('tm_ligne', 15, 2)->default(0);

            $table->unsignedInteger('order_index')->default(0)
                ->comment('Ordre d\'affichage manuel');
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('commune_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
