<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit transverse Phase 8E — Couverture INTÉGRALE de la RÈGLE D'OR #4
 * sur l'ensemble du domaine financier.
 *
 * La Phase 1 avait converti les colonnes du module Facturation
 * (invoices, invoice_lines, invoice_payments, invoice_services,
 * invoice_schedules, communes, commune_tax_rate_history) de DECIMAL(15,2)
 * vers BIGINT UNSIGNED.
 *
 * Mais 3 sources de montants liées au même domaine restaient DECIMAL :
 *   - campaigns.total_amount        (montant HT campagne, alimente Invoice)
 *   - reservations.total_amount     (montant HT résa, alimente Campaign + Invoice)
 *   - commune_tax_payments :
 *       . odp_theorique, tm_theorique (montant dû calculé)
 *       . odp_paye, tm_paye           (montant versé à la commune)
 *
 * Conséquence : décimales potentielles (1 234 567.50 F) sans aucun sens
 * en FCFA, arrondis incohérents au transfert vers la facturation.
 *
 * Cette migration ferme la couverture en convertissant les 6 colonnes
 * manquantes en BIGINT UNSIGNED avec pré-arrondi (UPDATE ROUND).
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') return; // sqlite test : tables recréées à neuf

        // ── campaigns.total_amount ──
        DB::statement("UPDATE campaigns SET total_amount = ROUND(COALESCE(total_amount, 0))");
        DB::statement("
            ALTER TABLE campaigns
                MODIFY COLUMN total_amount BIGINT UNSIGNED NOT NULL DEFAULT 0
        ");

        // ── reservations.total_amount ──
        DB::statement("UPDATE reservations SET total_amount = ROUND(COALESCE(total_amount, 0))");
        DB::statement("
            ALTER TABLE reservations
                MODIFY COLUMN total_amount BIGINT UNSIGNED NOT NULL DEFAULT 0
        ");

        // ── reservation_panels.unit_price (pivot, source des PU négociés) ──
        // Bonus : ce pivot alimente InvoiceLine.pu_ht_mensuel via le builder.
        // Si on garde decimal ici, on injecte des décimales dans une colonne
        // BIGINT (Phase 1) → erreur silencieuse ou troncature.
        if (Schema::hasTable('reservation_panels')) {
            DB::statement("UPDATE reservation_panels SET unit_price = ROUND(COALESCE(unit_price, 0))");
            DB::statement("
                ALTER TABLE reservation_panels
                    MODIFY COLUMN unit_price BIGINT UNSIGNED NOT NULL DEFAULT 0
            ");
            // total_price (pivot)
            if (Schema::hasColumn('reservation_panels', 'total_price')) {
                DB::statement("UPDATE reservation_panels SET total_price = ROUND(COALESCE(total_price, 0))");
                DB::statement("
                    ALTER TABLE reservation_panels
                        MODIFY COLUMN total_price BIGINT UNSIGNED NOT NULL DEFAULT 0
                ");
            }
        }

        // ── commune_tax_payments : montants théoriques + payés ──
        if (Schema::hasTable('commune_tax_payments')) {
            DB::statement("
                UPDATE commune_tax_payments SET
                    odp_theorique = ROUND(COALESCE(odp_theorique, 0)),
                    tm_theorique  = ROUND(COALESCE(tm_theorique, 0)),
                    odp_paye      = ROUND(COALESCE(odp_paye, 0)),
                    tm_paye       = ROUND(COALESCE(tm_paye, 0))
            ");
            DB::statement("
                ALTER TABLE commune_tax_payments
                    MODIFY COLUMN odp_theorique BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_theorique  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN odp_paye      BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_paye       BIGINT UNSIGNED NOT NULL DEFAULT 0
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE campaigns MODIFY COLUMN total_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE reservations MODIFY COLUMN total_amount DECIMAL(15,2) NOT NULL DEFAULT 0");

        if (Schema::hasTable('reservation_panels')) {
            DB::statement("ALTER TABLE reservation_panels MODIFY COLUMN unit_price DECIMAL(15,2) NOT NULL DEFAULT 0");
            if (Schema::hasColumn('reservation_panels', 'total_price')) {
                DB::statement("ALTER TABLE reservation_panels MODIFY COLUMN total_price DECIMAL(15,2) NOT NULL DEFAULT 0");
            }
        }

        if (Schema::hasTable('commune_tax_payments')) {
            DB::statement("
                ALTER TABLE commune_tax_payments
                    MODIFY COLUMN odp_theorique DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_theorique  DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN odp_paye      DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_paye       DECIMAL(15,2) NOT NULL DEFAULT 0
            ");
        }
    }
};
