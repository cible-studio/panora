<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interlocuteur d'un dossier (2026-10-29) : un client (ex. ORANGE) a
 * plusieurs interlocuteurs ; une campagne, une réservation ou un devis
 * précise lequel suit le dossier. Ses mails de suivi lui sont adressés, à
 * lui seul ; les documents (factures, devis, propositions, piges) partent
 * à l'adresse de la fiche et à lui.
 *
 * Simple index, sans contrainte : un interlocuteur supprimé (soft delete)
 * redonne la règle par défaut (contacts cochés « reçoit les mails »).
 * Additive et rejouable.
 */
return new class extends Migration
{
    private const TABLES = ['campaigns', 'reservations', 'quotes'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'client_contact_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->unsignedBigInteger('client_contact_id')->nullable()->after('client_id');
                    $t->index('client_contact_id', "idx_{$table}_client_contact");
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'client_contact_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex("idx_{$table}_client_contact");
                    $t->dropColumn('client_contact_id');
                });
            }
        }
    }
};
