<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOT 1 — Traçabilité paiements taxes communales (cahier des charges
 * 2026-06-19) : on doit pouvoir retrouver pour chaque versement le mode
 * (virement / chèque / espèces / mobile money / autre), la référence
 * (N° chèque, bordereau, transaction), et un commentaire libre.
 *
 * Cohérence : on aligne sur les modes utilisés par InvoicePayment
 * (cf. migration 2026_06_10_110000_phase2_payments_enriched) pour
 * harmoniser les exports comptables.
 *
 * Pas de valeur NOT NULL : les paiements historiques saisis avant cette
 * migration restent valides (mode nullable). Le formulaire de saisie
 * impose désormais le mode pour les nouveaux paiements.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('commune_tax_payments', function (Blueprint $t) {
            // Mode de paiement (nullable pour compat historique).
            $t->string('mode', 20)->nullable()->after('paid_at');
            // Référence transaction (N° chèque, bordereau virement, ID Mobile Money).
            $t->string('reference', 100)->nullable()->after('mode');
            // Commentaire libre (banque, motif, observation).
            $t->text('comment')->nullable()->after('reference');

            // Index sur mode pour requêtes filtrées (KPIs futurs : top mode, total/mode).
            $t->index('mode', 'idx_ctp_mode');
        });
    }

    public function down(): void
    {
        Schema::table('commune_tax_payments', function (Blueprint $t) {
            $t->dropIndex('idx_ctp_mode');
            $t->dropColumn(['mode', 'reference', 'comment']);
        });
    }
};
