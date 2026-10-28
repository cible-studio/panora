<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts envoyés depuis le Google Sheets (2026-10-28).
 *
 * - envois_contacts_gmail : journal de chaque envoi du bouton « Envoyer à
 *   Panora » (qui, quand, combien, résultat ligne par ligne). Les
 *   vérifications sans écriture y sont notées aussi.
 * - client_contacts.source : « gmail » pour un interlocuteur arrivé du
 *   Sheets (badge sur la fiche) ; vide pour une saisie dans Panora.
 *
 * Additive et rejouable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('envois_contacts_gmail')) {
            Schema::create('envois_contacts_gmail', function (Blueprint $table) {
                $table->id();
                $table->string('envoye_par', 150);
                $table->boolean('simulation')->default(false);
                $table->unsignedInteger('lignes')->default(0);
                $table->unsignedInteger('crees')->default(0);
                $table->unsignedInteger('completes')->default(0);
                $table->unsignedInteger('a_jour')->default(0);
                $table->unsignedInteger('refuses')->default(0);
                $table->unsignedInteger('entreprises_creees')->default(0);
                $table->longText('detail')->nullable(); // JSON : une entrée par ligne du Sheets
                $table->timestamps();
                $table->index('created_at', 'idx_envois_contacts_gmail_created');
            });
        }

        if (Schema::hasTable('client_contacts') && !Schema::hasColumn('client_contacts', 'source')) {
            Schema::table('client_contacts', function (Blueprint $table) {
                $table->string('source', 20)->nullable()->after('statut');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_contacts', 'source')) {
            Schema::table('client_contacts', fn (Blueprint $t) => $t->dropColumn('source'));
        }
        Schema::dropIfExists('envois_contacts_gmail');
    }
};
