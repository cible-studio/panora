<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients et prospects (2026-10-28).
 *
 * - clients.statut_commercial : client | prospect. « client » par défaut :
 *   tous les clients existants le restent. Un prospect passe client à sa
 *   première campagne (CampaignObserver).
 * - client_contacts.statut : chaque adresse a son propre statut (règle de la
 *   direction : pas de contact principal pour les envois).
 * - relances_fin_campagne.destinataires : un mail de campagne part désormais
 *   à chaque interlocuteur coché « reçoit les mails » ; la colonne
 *   `destinataire` (191 caractères) ne suffit plus pour les noter tous.
 *
 * Additive et rejouable : chaque colonne n'est ajoutée que si elle manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'statut_commercial')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('statut_commercial', 10)->default('client')->after('sector');
                $table->index('statut_commercial', 'idx_clients_statut_commercial');
            });
        }

        if (Schema::hasTable('client_contacts') && !Schema::hasColumn('client_contacts', 'statut')) {
            Schema::table('client_contacts', function (Blueprint $table) {
                $table->string('statut', 10)->default('client')->after('position');
            });
        }

        if (Schema::hasTable('relances_fin_campagne') && !Schema::hasColumn('relances_fin_campagne', 'destinataires')) {
            Schema::table('relances_fin_campagne', function (Blueprint $table) {
                $table->text('destinataires')->nullable()->after('destinataire');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('relances_fin_campagne', 'destinataires')) {
            Schema::table('relances_fin_campagne', fn (Blueprint $t) => $t->dropColumn('destinataires'));
        }
        if (Schema::hasColumn('client_contacts', 'statut')) {
            Schema::table('client_contacts', fn (Blueprint $t) => $t->dropColumn('statut'));
        }
        if (Schema::hasColumn('clients', 'statut_commercial')) {
            Schema::table('clients', function (Blueprint $t) {
                $t->dropIndex('idx_clients_statut_commercial');
                $t->dropColumn('statut_commercial');
            });
        }
    }
};
