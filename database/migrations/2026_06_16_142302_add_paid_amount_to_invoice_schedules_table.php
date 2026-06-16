<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mission "bug échéancier" — colonne paid_amount sur invoice_schedules.
 *
 * Avant : le statut d'une échéance était binaire (paid_at NULL ↔ Payée).
 * Le marquage venait soit d'un FIFO local sur le seul versement courant,
 * soit d'un clic manuel sans contrôle du cumul → désynchronisation avec
 * la réalité encaissée (cas FAC-2026-010 : 53 000 versés, 398 700 marqués
 * "payés" via clic).
 *
 * Après : le statut DÉRIVE du cumul global des versements, recalculé par
 * ScheduleAllocationService::recomputeFromPayments() à chaque mouvement.
 *
 *   paid_amount  ←  total imputé à cette échéance (FIFO sur tous les
 *                   versements de la facture, dans l'ordre chronologique)
 *   paid_at      ←  NULL si paid_amount < amount, date du dernier
 *                   versement qui a fini de couvrir l'échéance sinon
 *   paid_payment_id ← idem (dernier versement déclencheur si complet,
 *                     NULL sinon)
 *
 * Aucune migration de données ici — un script artisan dédié
 * `schedules:recompute-all` recalcule rétroactivement après déploiement.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0)->after('amount')
                ->comment('Montant cumulé imputé à cette échéance (FIFO depuis invoice_payments). 0 si rien, == amount si soldée.');

            // Index utile pour le futur dashboard "échéances partiellement
            // soldées" : où paid_amount > 0 AND paid_amount < amount.
            $table->index(['invoice_id', 'paid_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_schedules', function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'paid_amount']);
            $table->dropColumn('paid_amount');
        });
    }
};
