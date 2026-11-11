<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Axes multi-pieds — phase 1, le socle (2026-11-11).
 *
 * Un axe (ex. « Cocody Lumipub Axe St Jean - Carrefour Lavie (11P x 3F) »)
 * est aujourd'hui UNE ligne `panels` alors qu'il regroupe 11 supports
 * distincts le long de la voie. Le client ne peut donc pas en réserver une
 * partie, et une seule tâche de pose est créée pour 11 pieds à poser.
 *
 * Choix d'architecture (cf. docs/AXES_MULTI_PIEDS.md § 5) : **un
 * enregistrement `panels` par pied**, rattaché à la ligne de son axe.
 * Pas de table `pieds` : `panel_id` vit dans 8 tables et 669 endroits du
 * code, et AvailabilityService (1209 lignes, verrou pessimiste, index
 * dédié) est la pièce qui empêche de vendre deux fois le même
 * emplacement. Une table parallèle obligerait à la dupliquer.
 *
 * Deux colonnes, additives (règle n°3) :
 *   - `axe_id`  : la ligne de l'axe parent. NULL = panneau normal, ou
 *                 ligne d'axe elle-même.
 *   - `est_axe` : marque la ligne parente. Elle reste en base avec tout
 *                 son historique (piges, poses, contrats signés) mais
 *                 cesse d'être proposée à la vente.
 *
 * Pas de clé étrangère SQL : la structure réelle de `panels` a divergé des
 * migrations (cf. CLAUDE.md règle n°2). Index seulement. Rejouable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('panels')) {
            return;
        }

        if (! Schema::hasColumn('panels', 'axe_id')) {
            Schema::table('panels', function (Blueprint $table) {
                // Auto-référence vers panels.id — sans contrainte SQL.
                $table->unsignedBigInteger('axe_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('panels', 'est_axe')) {
            Schema::table('panels', function (Blueprint $table) {
                $table->boolean('est_axe')->default(false)->index();
            });
        }

        // Numéro du pied sur son axe : 1 pour le premier, 11 pour le
        // dernier de Carrefour Lavie. Sert au tri et au libellé, jamais au
        // calcul. NULL sur un panneau normal et sur la ligne d'axe.
        if (! Schema::hasColumn('panels', 'pied_numero')) {
            Schema::table('panels', function (Blueprint $table) {
                $table->unsignedSmallInteger('pied_numero')->nullable();
            });
        }

        // Nombre de faces portées par UN pied : 3 sur les axes Lumipub,
        // 2 sur BK-002 (Classique double face).
        //
        // ⚠ On n'utilise PAS `nombre_faces`, déjà présente : elle porte 1
        // sur 320 panneaux, 4 sur les 27 Chevalets (faces du chevalet) et
        // 3 sur un seul panneau d'axe. Deux significations dans la même
        // colonne — cf. docs/TECHNICAL_DEBT.md.
        if (! Schema::hasColumn('panels', 'faces_par_pied')) {
            Schema::table('panels', function (Blueprint $table) {
                $table->unsignedSmallInteger('faces_par_pied')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['faces_par_pied', 'pied_numero'] as $colonne) {
            if (Schema::hasColumn('panels', $colonne)) {
                Schema::table('panels', function (Blueprint $table) use ($colonne) {
                    $table->dropColumn($colonne);
                });
            }
        }

        foreach (['est_axe', 'axe_id'] as $colonne) {
            if (Schema::hasColumn('panels', $colonne)) {
                Schema::table('panels', function (Blueprint $table) use ($colonne) {
                    $table->dropIndex([$colonne]);
                    $table->dropColumn($colonne);
                });
            }
        }
    }
};
