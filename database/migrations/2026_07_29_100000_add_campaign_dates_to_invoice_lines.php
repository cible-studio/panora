<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TX-9 (2026-07-29) — version de main, jouée en production : campaign_start
 * / campaign_end (nullable) sur invoice_lines. Sa sœur
 * 2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines (develop)
 * fait la même chose, plus quote_lines.
 *
 * 2026-10-30 — les deux fichiers vivent désormais sur les deux branches
 * (module Devis passé sur main). Chacune ne fait rien si les colonnes
 * existent déjà : la production a joué celle-ci, le staging l'autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoice_lines') || Schema::hasColumn('invoice_lines', 'campaign_start')) {
            return;
        }
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->date('campaign_start')->nullable()->after('duree_mois');
            $table->date('campaign_end')->nullable()->after('campaign_start');
        });
    }

    public function down(): void
    {
        // Rien : les colonnes appartiennent aussi à la migration sœur, qui les retire.
    }
};
