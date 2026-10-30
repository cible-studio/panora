<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes du devis — 1 ligne = 1 panneau proposé (interne ou externe)
 * pour une durée donnée.
 *
 * Miroir de invoice_lines pour bénéficier des mêmes contrôles
 * (validation unicité, calcul ODP/TM par ligne, etc.).
 * Différence : ici on ne bloque PAS le panneau — c'est le principe
 * du devis. Le check unicité intra-devis reste (impossible de mettre
 * le même panneau 2 fois dans un même devis) mais pas inter-devis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();

            // Lien vers le panneau (interne ou externe)
            $table->foreignId('panel_id')->nullable()
                  ->constrained('panels')->nullOnDelete();
            $table->foreignId('external_panel_id')->nullable()
                  ->constrained('external_panels')->nullOnDelete();

            // Commune + snapshot du nom (résiste à un rename futur)
            $table->foreignId('commune_id')->nullable()
                  ->constrained('communes')->nullOnDelete();
            $table->string('snapshot_commune_name', 100)->nullable();

            $table->string('designation', 200);

            // Métrique + prix
            $table->decimal('dimension_m2', 8, 2)->default(0);
            $table->unsignedBigInteger('pu_ht_mensuel')->default(0);
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('duree_mois', 6, 2)->default(1);

            // Snapshot taux ODP/TM au moment du devis (résiste au
            // changement de barème communal)
            $table->unsignedInteger('odp_rate_applique')->default(0);
            $table->unsignedInteger('tm_rate_applique')->default(0);

            // Agrégats ligne (pré-calculés par QuoteBuilder)
            $table->unsignedBigInteger('montant_ht_ligne')->default(0);
            $table->unsignedBigInteger('odp_ligne')->default(0);
            $table->unsignedBigInteger('tm_ligne')->default(0);

            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index('quote_id');
            $table->index(['quote_id', 'panel_id'], 'idx_quote_line_int_panel');
            $table->index(['quote_id', 'external_panel_id'], 'idx_quote_line_ext_panel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
