<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Services annexes d'un devis : TVA au choix, service par service
 * (2026-10-30, demande de la direction).
 *
 * La facture le permet depuis le 2026-08-03 (invoice_services) : le
 * comptable décoche la TVA sur un service refacturé sans la réappliquer —
 * impression d'un fournisseur extérieur déjà taxée, par exemple. Le devis
 * doit annoncer le même montant que la facture qui en découlera.
 *
 * Migration ADDITIVE, défaut `true` : tous les services déjà saisis
 * restent soumis à la TVA, aucun devis existant ne change de total.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quote_services') && !Schema::hasColumn('quote_services', 'tva_applicable')) {
            Schema::table('quote_services', function (Blueprint $table) {
                $table->boolean('tva_applicable')->default(true)->after('prix_ht');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quote_services', 'tva_applicable')) {
            Schema::table('quote_services', function (Blueprint $table) {
                $table->dropColumn('tva_applicable');
            });
        }
    }
};
