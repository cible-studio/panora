<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides taxes au cas par cas (demande 2026-08-03 patronne) — le
 * Comptable doit pouvoir ajuster les taxes sur une facture spécifique
 * sans changer les défauts globaux.
 *
 * 3 colonnes ajoutées (toutes nullable → aucune facture existante
 * n'est cassée) :
 *
 *   - invoices.tsp_rate_override         (decimal 5,2)
 *       Taux TSP % pour cette facture. NULL = config('billing.tsp_rate')
 *       (défaut 3%). 0 = TSP désactivée. La colonne `invoices.tva`
 *       existe déjà et sera recyclée en override effectif TVA
 *       (auparavant écrite au store mais jamais relue par le
 *       calculator — le fix rendra la lecture cohérente).
 *
 *   - invoice_services.tva_applicable    (boolean, default true)
 *       Si false, le service annexe n'est pas soumis à TVA
 *       (prix_ht = TTC). Cas métier : certains frais annexes
 *       (impression fournisseur externe) sont facturés HT stricts
 *       sans TVA re-appliquée.
 *
 *   - invoice_lines.odp_amount_override  (bigint FCFA)
 *   - invoice_lines.tm_amount_override   (bigint FCFA)
 *       Si présent, remplace la valeur ODP/TM calculée automatiquement
 *       pour cette ligne. NULL = calcul auto habituel
 *       (rate × m² × qty × durée). Cas métier : négociation ponctuelle
 *       avec la commune ou correction manuelle.
 *
 * Aucun impact rétroactif : les factures existantes gardent leur
 * calcul actuel (tous les overrides à NULL / défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tsp_rate_override', 5, 2)->nullable()->after('tva');
        });

        Schema::table('invoice_services', function (Blueprint $table) {
            $table->boolean('tva_applicable')->default(true)->after('prix_ht');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->bigInteger('odp_amount_override')->unsigned()->nullable()->after('odp_ligne');
            $table->bigInteger('tm_amount_override')->unsigned()->nullable()->after('tm_ligne');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tsp_rate_override');
        });
        Schema::table('invoice_services', function (Blueprint $table) {
            $table->dropColumn('tva_applicable');
        });
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['odp_amount_override', 'tm_amount_override']);
        });
    }
};
