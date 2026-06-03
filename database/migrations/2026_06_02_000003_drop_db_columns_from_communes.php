<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire la 3e taxe communale "DB" (ambiguë, jamais formalisée côté
 * spec — supposée "Droits de Bord" mais source non confirmée). Le
 * module Taxes communes ne gère plus que ODP + TM.
 *
 * Colonnes concernées :
 *   - communes.db_rate
 *   - commune_tax_rate_history.db_rate
 *   - commune_tax_payments.db_theorique
 *   - commune_tax_payments.db_paye
 *
 * Boot-safe :
 *   - vérifie l'existence de chaque colonne avant drop
 *   - aucun index/contrainte créé au boot
 *   - reversible (down() ré-ajoute les colonnes en decimal(14,2) default 0)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('communes')) {
            Schema::table('communes', function (Blueprint $table) {
                if (Schema::hasColumn('communes', 'db_rate')) {
                    $table->dropColumn('db_rate');
                }
            });
        }

        if (Schema::hasTable('commune_tax_rate_history')) {
            Schema::table('commune_tax_rate_history', function (Blueprint $table) {
                if (Schema::hasColumn('commune_tax_rate_history', 'db_rate')) {
                    $table->dropColumn('db_rate');
                }
            });
        }

        if (Schema::hasTable('commune_tax_payments')) {
            Schema::table('commune_tax_payments', function (Blueprint $table) {
                if (Schema::hasColumn('commune_tax_payments', 'db_theorique')) {
                    $table->dropColumn('db_theorique');
                }
                if (Schema::hasColumn('commune_tax_payments', 'db_paye')) {
                    $table->dropColumn('db_paye');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('communes')) {
            Schema::table('communes', function (Blueprint $table) {
                if (!Schema::hasColumn('communes', 'db_rate')) {
                    $table->decimal('db_rate', 10, 2)->default(0)->after('tm_rate');
                }
            });
        }

        if (Schema::hasTable('commune_tax_rate_history')) {
            Schema::table('commune_tax_rate_history', function (Blueprint $table) {
                if (!Schema::hasColumn('commune_tax_rate_history', 'db_rate')) {
                    $table->decimal('db_rate', 10, 2)->default(0)->after('tm_rate');
                }
            });
        }

        if (Schema::hasTable('commune_tax_payments')) {
            Schema::table('commune_tax_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('commune_tax_payments', 'db_theorique')) {
                    $table->decimal('db_theorique', 14, 2)->default(0)->after('tm_theorique');
                }
                if (!Schema::hasColumn('commune_tax_payments', 'db_paye')) {
                    $table->decimal('db_paye', 14, 2)->default(0)->after('tm_paye');
                }
            });
        }
    }
};
