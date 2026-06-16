<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mission C — visibilité litige + 13 motifs prédéfinis.
 *
 * Avant : la raison du litige était saisie via prompt() JS et stockée
 * uniquement dans invoice_status_history.reason (texte libre). Aucune
 * possibilité d'affichage persistant ni de filtrage par motif.
 *
 * Après : trois colonnes nullable sur invoices :
 *   - litige_motif (string)    : l'un des 13 codes Invoice::LITIGE_MOTIFS
 *   - litige_note  (text)      : commentaire libre de l'admin
 *   - litige_opened_at (date)  : timestamp d'ouverture du litige
 *
 * Ces colonnes restent NULL pour les factures non litigieuses et sont
 * remises à NULL automatiquement quand la facture sort du statut 'litige'
 * (cf. transitionStatusTo / observer).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('litige_motif', 60)->nullable()->after('status')
                ->comment('Code parmi Invoice::LITIGE_MOTIFS (échéancier_non_respecté, retard_paiement…).');
            $table->text('litige_note')->nullable()->after('litige_motif')
                ->comment('Commentaire libre de l\'admin sur le litige (contexte, contact client, prochaine étape).');
            $table->dateTime('litige_opened_at')->nullable()->after('litige_note')
                ->comment('Date d\'ouverture du litige (NULL = pas en litige).');

            // Index pour filtrer les litiges en cours par motif (utile
            // pour les dashboards finance / contentieux).
            $table->index('litige_motif');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['litige_motif']);
            $table->dropColumn(['litige_motif', 'litige_note', 'litige_opened_at']);
        });
    }
};
