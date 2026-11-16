<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattrapage : `client_contact_id` manquante en production (2026-11-16).
 *
 * ═══ LE DÉFAUT ═══
 * L'enregistrement d'un devis échouait en production :
 *   SQLSTATE[42S22] Unknown column 'client_contact_id' in 'field list'
 *   insert into `quotes` (…, `client_contact_id`, …)
 *
 * La colonne est pourtant prévue par la migration du 2026-10-29
 * (add_interlocuteur_to_campaigns_reservations_quotes), qui la pose sur
 * `campaigns`, `reservations` ET `quotes`. Elle est bien présente sur les
 * deux premières — les réservations s'enregistrent sans erreur — mais pas
 * sur `quotes`.
 *
 * Cette migration-là est déjà inscrite dans la table `migrations` : la
 * rejouer ne ferait rien. D'où ce rattrapage séparé.
 *
 * Cf. CLAUDE.md règle n°2 : les migrations Panora ont divergé de la base
 * réelle, seule la structure observée fait foi.
 *
 * Strictement additive et rejouable : sur une base déjà correcte, elle
 * ne fait rien. Portable MySQL et SQLite.
 */
return new class extends Migration
{
    private const TABLES = ['campaigns', 'reservations', 'quotes'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'client_contact_id')) {
                continue;
            }

            // `after()` n'a de sens que si la colonne de référence existe ;
            // sinon MySQL rejette l'ALTER et on perd le rattrapage entier.
            $apresClientId = Schema::hasColumn($table, 'client_id');

            Schema::table($table, function (Blueprint $t) use ($apresClientId) {
                $colonne = $t->unsignedBigInteger('client_contact_id')->nullable();
                if ($apresClientId) {
                    $colonne->after('client_id');
                }
            });

            // Index posé à part : s'il existait déjà sous un autre nom, on
            // ne veut pas faire échouer l'ajout de la colonne, qui est le
            // vrai sujet.
            try {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->index('client_contact_id', "idx_{$table}_client_contact");
                });
            } catch (\Throwable $e) {
                // Index déjà présent : sans conséquence.
            }
        }
    }

    public function down(): void
    {
        // Volontairement vide : cette migration ne fait que rattraper ce
        // que celle du 2026-10-29 aurait dû poser. C'est elle qui porte le
        // down() de la colonne — en dupliquer un ici risquerait de
        // supprimer une colonne pleine de données.
    }
};
