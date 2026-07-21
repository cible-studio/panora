<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute regime_imposition à la table clients.
 *
 * Contexte (2026-07-21) : refonte du PDF facture au modèle FNE officiel
 * fourni par la patronne. Le bloc client de l'entête FNE contient :
 *   Nom · Adresse · NCC · Régime d'imposition
 *
 * NCC est déjà stocké. Régime d'imposition (RNI, RSI, RME…) manquait.
 *
 * Migration additive + idempotente. Valeur nullable — les factures
 * existantes ne changent pas, l'admin renseigne au fil de l'eau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'regime_imposition')) {
                $table->string('regime_imposition', 50)
                      ->nullable()
                      ->after('ncc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'regime_imposition')) {
                $table->dropColumn('regime_imposition');
            }
        });
    }
};
