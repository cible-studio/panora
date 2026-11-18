<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarif « panneau éclairé » (2026-11-18, règle validée par écrit) : une
 * commune peut avoir un tarif ODP et/ou TM différent pour les panneaux
 * éclairés (case « Éclairé » de la fiche panneau, `panels.is_lit`).
 * Vide = même tarif que les panneaux non éclairés : toutes les communes
 * existantes gardent exactement leur calcul.
 *
 * Exemples donnés le 18/11 (à saisir à l'écran une fois confirmés) :
 *   Port-Bouët : ODP 3 000 ; TM 1 000 non éclairé / 3 000 éclairé.
 *   Adjamé     : ODP 1 000 / 3 000 ; TM 1 000 / 3 000, au 01/01/2026.
 *
 * Colonnes nullables ajoutées, rien n'est modifié. Portable MySQL et SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['communes', 'commune_tax_rate_history'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach (['odp_rate_eclaire', 'tm_rate_eclaire'] as $colonne) {
                if (!Schema::hasColumn($table, $colonne)) {
                    Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger($colonne)->nullable());
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['communes', 'commune_tax_rate_history'] as $table) {
            foreach (['odp_rate_eclaire', 'tm_rate_eclaire'] as $colonne) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $colonne)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($colonne));
                }
            }
        }
    }
};
