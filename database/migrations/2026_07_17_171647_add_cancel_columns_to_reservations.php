<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout des colonnes d'annulation à la table reservations.
 *
 * Contexte urgent (2026-07-17) : la patronne signale une erreur en prod
 *   « Column not found: cancel_type »
 * en tentant d'annuler la résa 67. Root cause : les 4 colonnes
 * cancel_type / cancel_reason / cancelled_at / cancelled_by sont
 * utilisées par le code (Controller::annuler, Service::cancel,
 * modèle Reservation) depuis plusieurs mois, mais la MIGRATION n'a
 * jamais été committée. Elles ont été ajoutées à la main sur les
 * environnements de dev, ce qui masquait la dette. La prod (main)
 * n'a jamais reçu ces colonnes.
 *
 * Cette migration est ADDITIVE + IDEMPOTENTE : elle utilise
 * hasColumn() pour ne rien créer si les colonnes existent déjà
 * (cas dev où elles ont été ajoutées à la main). Sûre sur tous les
 * environnements.
 *
 * À signaler dans docs/TECHNICAL_DEBT.md : chercher d'autres colonnes
 * ajoutées manuellement dans les modèles Panora — audit à prévoir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'cancel_type')) {
                $table->string('cancel_type', 50)->nullable()->after('type');
            }
            if (!Schema::hasColumn('reservations', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancel_type');
            }
            if (!Schema::hasColumn('reservations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            }
            if (!Schema::hasColumn('reservations', 'cancelled_by')) {
                $table->foreignId('cancelled_by')
                      ->nullable()
                      ->after('cancelled_at')
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
                $table->dropColumn('cancelled_by');
            }
            foreach (['cancelled_at', 'cancel_reason', 'cancel_type'] as $col) {
                if (Schema::hasColumn('reservations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
