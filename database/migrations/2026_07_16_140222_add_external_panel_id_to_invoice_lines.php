<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute external_panel_id à invoice_lines pour permettre le lien vers
 * un panneau de régie externe partenaire (SPBS-01, etc.) lors de la
 * facturation.
 *
 * Contexte (2026-07-16) : jusqu'ici seul panel_id existait sur
 * invoice_lines. Impossible donc de tracer proprement qu'une ligne de
 * facture correspond à un panneau EXTERNE (loué à un partenaire).
 * Résultat : validation d'unicité impossible (on ne pouvait pas
 * différencier "SPBS-01 externe" et "SP-001 interne" au moment du
 * check). Cette migration comble le trou pour permettre la règle
 * "aucun panneau facturé 2× dans la même facture" (Bug 3b/3c).
 *
 * Additif — additive uniquement. Colonne nullable, aucun impact sur
 * les factures existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('external_panel_id')
                  ->nullable()
                  ->after('panel_id')
                  ->constrained('external_panels')
                  ->nullOnDelete();

            // Index composite pour accélérer la validation d'unicité
            // intra-facture (SELECT ... WHERE invoice_id=? AND external_panel_id=?).
            $table->index(['invoice_id', 'external_panel_id'], 'idx_invoice_line_ext_panel');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_line_ext_panel');
            $table->dropForeign(['external_panel_id']);
            $table->dropColumn('external_panel_id');
        });
    }
};
