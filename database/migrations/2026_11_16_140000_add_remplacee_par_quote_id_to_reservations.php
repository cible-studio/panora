<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un devis remplace l'option ouverte automatiquement (2026-11-16).
 *
 * ═══ POURQUOI ═══
 * Depuis le flux « bon de commande », un client qui répond « je renouvelle »
 * déclenche la création d'une RÉSERVATION EN OPTION sur ses panneaux, en
 * attente de BC pendant 30 jours.
 *
 * On peut maintenant générer un devis depuis la fiche de renouvellement.
 * Sans lien explicite, l'acceptation de ce devis créerait une SECONDE
 * réservation sur les mêmes panneaux pour le même renouvellement : deux
 * dossiers vivants là où il n'y a qu'un engagement.
 *
 * Cette colonne dit « cette option sera remplacée par ce devis ». Elle est
 * posée à l'enregistrement du devis, et l'option n'est annulée qu'à son
 * ACCEPTATION — pas avant, sinon les panneaux repartiraient au catalogue
 * pendant que le client réfléchit (un devis ne bloque rien, cf.
 * billing.quote_legal_mention).
 *
 * On ne réutilise pas `reservations.quote_id` : cette colonne-là sert de
 * preuve qu'un devis a été accepté (Quote::aEteAccepte()). La remplir sur
 * une simple option ferait passer un brouillon pour un devis signé, et
 * interdirait sa suppression.
 *
 * Strictement additive et rejouable. Portable MySQL et SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }
        if (Schema::hasColumn('reservations', 'remplacee_par_quote_id')) {
            return;
        }

        // `after()` n'a de sens que si la colonne de référence existe ;
        // sinon MySQL rejette l'ALTER (cf. CLAUDE.md règle n°2 : les
        // migrations Panora ont divergé de la base réelle).
        $apresQuoteId = Schema::hasColumn('reservations', 'quote_id');

        Schema::table('reservations', function (Blueprint $t) use ($apresQuoteId) {
            $colonne = $t->unsignedBigInteger('remplacee_par_quote_id')->nullable();
            if ($apresQuoteId) {
                $colonne->after('quote_id');
            }
        });

        // Index posé à part : s'il existait déjà sous un autre nom, on ne
        // veut pas faire échouer l'ajout de la colonne, qui est le sujet.
        try {
            Schema::table('reservations', function (Blueprint $t) {
                $t->index('remplacee_par_quote_id', 'idx_reservations_remplacee_par_quote');
            });
        } catch (\Throwable $e) {
            // Index déjà présent : sans conséquence.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')
            || ! Schema::hasColumn('reservations', 'remplacee_par_quote_id')) {
            return;
        }

        try {
            Schema::table('reservations', function (Blueprint $t) {
                $t->dropIndex('idx_reservations_remplacee_par_quote');
            });
        } catch (\Throwable $e) {
            // Index absent ou nommé autrement : on continue.
        }

        Schema::table('reservations', function (Blueprint $t) {
            $t->dropColumn('remplacee_par_quote_id');
        });
    }
};
