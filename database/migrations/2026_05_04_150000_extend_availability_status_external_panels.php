<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BUGFIX prod : extend external_panels.availability_status enum.
 *
 * Origine : la colonne avait été créée avec ENUM('disponible','occupe','a_verifier')
 * dans la migration 2026_03_23. Or AvailabilityService::syncExternalPanelStatuses()
 * tente d'écrire 'option', 'confirme' ou 'maintenance' (mêmes valeurs que les
 * panneaux internes), ce qui provoque :
 *
 *   SQLSTATE[01000]: Warning: 1265 Data truncated for column
 *   'availability_status' at row 1
 *
 * Conséquence : la valeur écrite est tronquée à '' (chaîne vide), ce qui rend
 * la ligne incohérente et fait planter les requêtes filtrées ensuite.
 *
 * Solution : passer la colonne à VARCHAR(20) — flexibilité maximale, pas
 * d'ENUM rigide à maintenir, et la liste des valeurs valides est portée
 * par le code (AvailabilityService).
 *
 * Backfill défensif : toute ligne corrompue (chaîne vide / valeur inconnue)
 * est ramenée à 'disponible'.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('external_panels', 'availability_status')) {
            return; // colonne absente — rien à modifier
        }

        // ── 1) Élargir le type : ENUM → VARCHAR ────────────────────
        // doctrine/dbal n'est pas requis ici car on passe par du SQL brut.
        DB::statement("
            ALTER TABLE external_panels
            MODIFY COLUMN availability_status VARCHAR(20) NOT NULL DEFAULT 'disponible'
        ");

        // ── 2) Réparer les lignes corrompues par la troncation ────
        $valid = ['disponible', 'occupe', 'option', 'confirme', 'maintenance', 'a_verifier'];
        DB::table('external_panels')
            ->whereNotIn('availability_status', $valid)
            ->update(['availability_status' => 'disponible']);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('external_panels', 'availability_status')) {
            return;
        }

        // Revenir à l'ENUM strict si rollback (les valeurs hors-enum sont déjà nettoyées).
        // On garde 'option', 'confirme', 'maintenance' dans la liste pour ne pas
        // recasser ce que le code écrit légitimement.
        DB::statement("
            ALTER TABLE external_panels
            MODIFY COLUMN availability_status
                ENUM('disponible','occupe','a_verifier','option','confirme','maintenance')
                NOT NULL DEFAULT 'a_verifier'
        ");
    }
};
