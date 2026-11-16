<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantité sur les services annexes (2026-11-16).
 *
 * ═══ POURQUOI ═══
 * Le modèle de devis officiel CIBLE facture un service en quantité :
 *
 *   Frais d'impression dos bleu format 12m² | 30 | 20 000 FCFA | 600 000 FCFA
 *
 * Panora ne portait qu'un libellé et un prix global : impossible de
 * reproduire cette présentation, et impossible pour le client de vérifier
 * le calcul — il lisait « 600 000 » sans savoir d'où ça venait.
 *
 * ═══ SÉMANTIQUE ═══
 * `prix_ht` devient le prix UNITAIRE, et le montant de la ligne vaut
 * prix_ht × quantite.
 *
 * La valeur par défaut est 1 : toutes les lignes existantes gardent donc
 * exactement le même montant qu'avant (prix_ht × 1 = prix_ht). Aucune
 * facture ni aucun devis déjà émis ne change de total.
 *
 * ⚠ Les deux tables bougent ensemble. Un devis accepté devient une
 * facture : si seule l'une des deux portait la quantité, le montant du
 * devis et celui de la facture finiraient par diverger — exactement le
 * genre d'écart qui se transforme en litige client.
 *
 * Strictement additive et rejouable. Portable MySQL et SQLite.
 */
return new class extends Migration
{
    /** table => colonne après laquelle poser la quantité, si elle existe. */
    private const TABLES = [
        'quote_services'   => 'label',
        'invoice_services' => 'label',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $apres) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'quantite')) {
                continue;
            }

            // `after()` n'a de sens que si la colonne de référence existe ;
            // sinon MySQL rejette l'ALTER entier (CLAUDE.md règle n°2 : les
            // migrations Panora ont divergé de la base réelle).
            $apresExiste = Schema::hasColumn($table, $apres);

            Schema::table($table, function (Blueprint $t) use ($apres, $apresExiste) {
                // 1 par défaut : une ligne existante vaut toujours son
                // prix_ht, le total ne bouge pas.
                $colonne = $t->unsignedInteger('quantite')->default(1);
                if ($apresExiste) {
                    $colonne->after($apres);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'quantite')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('quantite');
            });
        }
    }
};
