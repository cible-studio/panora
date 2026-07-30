<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TX-9 (2026-07-29) — Ajoute campaign_start / campaign_end (nullable) sur
 * invoice_lines pour permettre le calcul automatique de la TM (mois
 * anniversaire entamés) et de l'ODP (trimestres calendaires touchés)
 * depuis les dates réelles de la campagne.
 *
 * Colonnes nullable → aucune facture existante n'est cassée. Si les dates
 * sont absentes, InvoiceCalculator retombe sur l'ancien comportement basé
 * sur duree_mois saisi par le commercial (compatibilité totale historique).
 *
 * NOTE : sur main, seule la table invoice_lines existe (le module Devis
 * — quote_lines — est encore sur develop). La migration sœur qui ajoute
 * ces mêmes colonnes à quote_lines vit sur develop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->date('campaign_start')->nullable()->after('duree_mois');
            $table->date('campaign_end')->nullable()->after('campaign_start');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['campaign_start', 'campaign_end']);
        });
    }
};
