<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TX-9 (2026-07-29) — Ajoute campaign_start / campaign_end (nullable) sur
 * invoice_lines et quote_lines pour permettre le calcul automatique de la
 * TM (mois anniversaire entamés) et de l'ODP (trimestres calendaires touchés)
 * depuis les dates réelles de la campagne.
 *
 * Colonnes nullable → aucune facture existante n'est cassée. Si les dates
 * sont absentes, InvoiceCalculator retombe sur l'ancien comportement basé
 * sur duree_mois saisi par le commercial (compatibilité totale historique).
 *
 * 2026-10-30 — rendue idempotente : la production a déjà ajouté les colonnes
 * d'invoice_lines par la migration sœur de main
 * (2026_07_29_100000_add_campaign_dates_to_invoice_lines). Sans ce test, le
 * passage du module Devis sur main faisait échouer `migrate --force` au
 * démarrage (« Duplicate column ») et la production ne repartait pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice_lines', 'quote_lines'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'campaign_start')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->date('campaign_start')->nullable()->after('duree_mois');
                $t->date('campaign_end')->nullable()->after('campaign_start');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoice_lines', 'quote_lines'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'campaign_start')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['campaign_start', 'campaign_end']));
            }
        }
    }
};
