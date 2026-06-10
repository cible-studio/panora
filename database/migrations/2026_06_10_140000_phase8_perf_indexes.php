<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 finalisation — Index performance critique sur invoices,
 * invoice_payments, invoice_lines.
 *
 * Constats audit prod (résultat de SHOW INDEX) — index MANQUANTS :
 *
 *   invoices :
 *     - status (utilisé par 10+ queries : KPI dashboard, filtres, RBAC)
 *     - issued_at (whereYear/whereMonth dans dashboard finance)
 *
 *   invoice_payments :
 *     - paid_at (whereYear/whereMonth dans KPI encaissements)
 *
 *   invoice_lines :
 *     - (commune_id, montant_ht_ligne) composite pour le Top 10
 *       communes contributrices qui groupBy commune + sum(montant_ht_ligne)
 *
 * Bénéfices attendus :
 *   - /dashboard sans index ≈ 800 ms sur 5000 factures → avec ≈ 80 ms
 *   - filtres /admin/invoices par statut : 10× plus rapide
 *   - Top 10 communes : passe de full scan à index range scan
 *
 * Pas d'impact sur la BDD : ces index sont CREATED IF NOT EXISTS via
 * Schema::hasIndex (Laravel le gère) pour permettre re-run safe.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── invoices.status ──
        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->indexExists('invoices', 'idx_invoices_status')) {
                $table->index('status', 'idx_invoices_status');
            }
            if (!$this->indexExists('invoices', 'idx_invoices_issued_at')) {
                $table->index('issued_at', 'idx_invoices_issued_at');
            }
            // Composite très utilisé : "factures non annulées sur année"
            if (!$this->indexExists('invoices', 'idx_invoices_status_year')) {
                $table->index(['status', 'issued_at'], 'idx_invoices_status_year');
            }
        });

        // ── invoice_payments.paid_at ──
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (!$this->indexExists('invoice_payments', 'idx_payments_paid_at')) {
                $table->index('paid_at', 'idx_payments_paid_at');
            }
        });

        // ── invoice_lines : (commune_id, montant_ht_ligne) ──
        Schema::table('invoice_lines', function (Blueprint $table) {
            if (!$this->indexExists('invoice_lines', 'idx_lines_commune_ht')) {
                $table->index(['commune_id', 'montant_ht_ligne'], 'idx_lines_commune_ht');
            }
        });

        // ── invoice_schedules.due_date (utilisé par Job alertes + prévision 30j) ──
        if (Schema::hasTable('invoice_schedules')) {
            Schema::table('invoice_schedules', function (Blueprint $table) {
                if (!$this->indexExists('invoice_schedules', 'idx_schedules_due_unpaid')) {
                    $table->index(['due_date', 'paid_at'], 'idx_schedules_due_unpaid');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if ($this->indexExists('invoices', 'idx_invoices_status_year')) {
                $table->dropIndex('idx_invoices_status_year');
            }
            if ($this->indexExists('invoices', 'idx_invoices_issued_at')) {
                $table->dropIndex('idx_invoices_issued_at');
            }
            if ($this->indexExists('invoices', 'idx_invoices_status')) {
                $table->dropIndex('idx_invoices_status');
            }
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            if ($this->indexExists('invoice_payments', 'idx_payments_paid_at')) {
                $table->dropIndex('idx_payments_paid_at');
            }
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            if ($this->indexExists('invoice_lines', 'idx_lines_commune_ht')) {
                $table->dropIndex('idx_lines_commune_ht');
            }
        });

        if (Schema::hasTable('invoice_schedules')) {
            Schema::table('invoice_schedules', function (Blueprint $table) {
                if ($this->indexExists('invoice_schedules', 'idx_schedules_due_unpaid')) {
                    $table->dropIndex('idx_schedules_due_unpaid');
                }
            });
        }
    }

    /**
     * Vérifie l'existence d'un index par nom. SHOW INDEX standard MySQL,
     * fallback inerte sur les autres drivers (sqlite, pgsql, etc.) →
     * retourne false pour que les CREATE soient tentés.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            if (DB::connection()->getDriverName() !== 'mysql') return false;
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
