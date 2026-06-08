<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs IFU (Identifiant Fiscal Unique) et NCC (Numéro de Compte
 * Contribuable) côté client — exigés par la FNE pour identifier le
 * bénéficiaire de la facture sur le document légal.
 *
 * Idempotent (hasColumn check). Nullable parce qu'on a des clients
 * historiques sans ces champs. L'UI les rendra obligatoires côté
 * formulaire futur (à validation client).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'ifu')) {
                $table->string('ifu', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('clients', 'ncc')) {
                $table->string('ncc', 30)->nullable()->after('ifu');
            }
            if (!Schema::hasColumn('clients', 'rccm')) {
                $table->string('rccm', 50)->nullable()->after('ncc')
                    ->comment('Registre Commerce et Crédit Mobilier — utile pour entreprises');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach (['rccm', 'ncc', 'ifu'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
