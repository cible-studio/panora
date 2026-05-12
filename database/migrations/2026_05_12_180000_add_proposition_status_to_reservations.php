<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Cycle interne de la proposition (Évolution rôles — docs/ROLES_VALIDES.md) :
 *
 *   draft        → en construction par le Media Planner
 *   prepared     → MP a terminé la construction
 *   pending_send → soumise au commercial pour envoi au client
 *   sent         → commercial a envoyé l'email (proposition_sent_at posé)
 *
 * Les statuts suivants (vue, signée, refusée, expirée) restent dérivés
 * des timestamps existants (proposition_viewed_at, status, etc.) pour
 * ne pas dupliquer la source de vérité.
 *
 * Les propositions existantes (avec proposition_sent_at non null) sont
 * migrées en 'sent'. Les autres en 'draft'.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('proposition_status', 32)
                ->default('draft')
                ->after('proposition_slug');
            $table->index('proposition_status');
        });

        // Backfill : propositions déjà envoyées → 'sent', le reste en 'draft'
        DB::table('reservations')
            ->whereNotNull('proposition_sent_at')
            ->update(['proposition_status' => 'sent']);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['proposition_status']);
            $table->dropColumn('proposition_status');
        });
    }
};
