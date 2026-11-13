<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bon de commande (2026-11-13) — « sans BC, pas de campagne ».
 *
 * Avant : le clic du client sur la proposition créait la campagne dans la
 * foulée, alors qu'aucun bon de commande n'était forcément arrivé.
 * Maintenant : les panneaux restent EN OPTION le temps d'obtenir le BC
 * (30 jours par défaut, cf. config/bon_de_commande.php), le commercial
 * enregistre sa réception — verbalement, sans pièce jointe obligatoire —
 * puis le MP crée la campagne dédiée.
 *
 * Colonnes volontairement toutes nullable : les réservations existantes
 * ne changent pas de comportement tant que rien n'est posé dessus.
 * Portable MySQL et SQLite (pas de MODIFY, pas de raw) — la suite de
 * tests tourne sur SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Date limite de réception du BC. C'est AUSSI le marqueur du
            // nouveau flux : non nul = « le client s'est engagé, on attend
            // son bon de commande ». Nul = ancien comportement.
            $table->date('bc_attendu_jusqu_au')->nullable()->after('confirmed_at');

            // Réception du BC — confirmée verbalement par le commercial.
            $table->timestamp('bc_recu_at')->nullable()->after('bc_attendu_jusqu_au');
            $table->unsignedBigInteger('bc_recu_par')->nullable()->after('bc_recu_at');
            // Champ libre FACULTATIF : numéro de BC, ou toute autre info.
            $table->string('bc_reference', 190)->nullable()->after('bc_recu_par');

            // Relances déjà parties (J-15, J-7, J-2), pour ne jamais
            // envoyer deux fois la même.
            $table->timestamp('bc_relance_j15_at')->nullable()->after('bc_reference');
            $table->timestamp('bc_relance_j7_at')->nullable()->after('bc_relance_j15_at');
            $table->timestamp('bc_relance_j2_at')->nullable()->after('bc_relance_j7_at');

            // Le balayage quotidien filtre sur cette colonne.
            $table->index('bc_attendu_jusqu_au', 'reservations_bc_attendu_index');
        });

        // ── Bascule des options déjà en cours ────────────────────────
        // Décision 2026-11-13 : on leur donne 30 jours pleins à compter
        // d'aujourd'hui plutôt que de les laisser mourir sous l'ancienne
        // règle des 7 jours (reservations:expire-old-options), qui les
        // aurait tuées avant même la première relance.
        $delai = (int) config('bon_de_commande.delai_jours', 30);
        DB::table('reservations')
            ->whereNull('deleted_at')
            ->where('type', 'option')
            ->where('status', 'en_attente')
            ->whereNull('bc_attendu_jusqu_au')
            ->update(['bc_attendu_jusqu_au' => now()->addDays($delai)->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_bc_attendu_index');
            $table->dropColumn([
                'bc_attendu_jusqu_au',
                'bc_recu_at',
                'bc_recu_par',
                'bc_reference',
                'bc_relance_j15_at',
                'bc_relance_j7_at',
                'bc_relance_j2_at',
            ]);
        });
    }
};
