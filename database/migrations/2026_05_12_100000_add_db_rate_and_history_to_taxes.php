<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Évolution 4 — Taxes Communales :
 *  - Ajout du type DB (Droit de Bornage) en plus de ODP / TM.
 *  - Ajout de db_rate sur la table communes.
 *  - Création de commune_tax_rate_history pour conserver les tarifs
 *    en vigueur à chaque période — sans ça, un calcul rétroactif sur
 *    une période passée utiliserait les tarifs actuels et serait faux
 *    (audit / contrôle commune impossible à justifier).
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. db_rate sur communes
        if (!Schema::hasColumn('communes', 'db_rate')) {
            Schema::table('communes', function (Blueprint $table) {
                $table->decimal('db_rate', 10, 2)->default(0)->after('tm_rate');
            });
        }

        // 2. Élargir l'enum taxes.type pour inclure 'db'.
        DB::statement("ALTER TABLE taxes
            MODIFY COLUMN type ENUM('odp','tm','db') NOT NULL");

        // 3. Historique tarifaire — une ligne par changement.
        //    effective_from = date à laquelle ces tarifs entrent en vigueur.
        //    effective_to (nullable) = date de fin (NULL si en vigueur).
        //    Lookup : rate à la date D = enregistrement où
        //        effective_from <= D AND (effective_to IS NULL OR effective_to >= D)
        if (!Schema::hasTable('commune_tax_rate_history')) {
            Schema::create('commune_tax_rate_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commune_id')->constrained()->cascadeOnDelete();
                $table->decimal('odp_rate', 10, 2)->default(0);
                $table->decimal('tm_rate', 10, 2)->default(0);
                $table->decimal('db_rate', 10, 2)->default(0);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['commune_id', 'effective_from']);
            });
        }

        // 4. Snapshot initial : pour chaque commune, créer une ligne
        //    d'historique reflétant les tarifs actuels avec effective_from
        //    = 2026-01-01 (début d'année courante). Garantit que tout calcul
        //    rétroactif sur 2026 s'appuie sur des tarifs explicites.
        $today = now()->startOfYear()->toDateString();
        DB::statement("
            INSERT INTO commune_tax_rate_history
                (commune_id, odp_rate, tm_rate, db_rate, effective_from, notes, created_at, updated_at)
            SELECT c.id, COALESCE(c.odp_rate, 0), COALESCE(c.tm_rate, 0), COALESCE(c.db_rate, 0),
                   '{$today}', '[Auto] Snapshot initial Évolution 4', NOW(), NOW()
            FROM communes c
            WHERE NOT EXISTS (
                SELECT 1 FROM commune_tax_rate_history h
                WHERE h.commune_id = c.id AND h.effective_from = '{$today}'
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('commune_tax_rate_history');
        DB::statement("ALTER TABLE taxes MODIFY COLUMN type ENUM('odp','tm') NOT NULL");
        if (Schema::hasColumn('communes', 'db_rate')) {
            Schema::table('communes', fn(Blueprint $t) => $t->dropColumn('db_rate'));
        }
    }
};
