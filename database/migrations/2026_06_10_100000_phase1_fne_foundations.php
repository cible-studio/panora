<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Fondations FNE (cahier des charges Recouvrement).
 *
 * Trois changements structurels en une seule migration atomique :
 *
 *   A) RÈGLE D'OR #4 — Tous les MONTANTS FCFA passent de DECIMAL(15,2)
 *      à BIGINT UNSIGNED. Le franc CFA n'a pas de centimes. Les
 *      arrondis hérités sont préservés par UPDATE ... ROUND(...) AVANT
 *      l'ALTER pour éviter une troncature silencieuse MySQL.
 *
 *      Champs conservés en DECIMAL :
 *        - dimension_m2 (surface fractionnaire 12.5 m²)
 *        - duree_mois   (durée fractionnaire 0.5 mois)
 *        - remise_pct   (pourcentage 0-100)
 *        - tva / tva_rate (taux)
 *
 *   B) Ajoute invoices.commercial_user_id (FK users) pour matérialiser
 *      le "commercial responsable" demandé § 1 du cahier (récupéré sans
 *      ressaisie depuis la campagne). Distinct de created_by (qui reste
 *      l'utilisateur qui a CLIQUÉ sur "Générer/Créer la facture", ce
 *      qui peut être un admin différent du commercial titulaire).
 *
 *   C) Élargit l'enum invoices.status de 4 → 9 statuts (§3 du cahier) :
 *        brouillon, generee, validee, envoyee, partiellement_payee,
 *        payee, en_retard, litige, annulee.
 *      Les anciennes valeurs sont préservées (subset).
 *
 *   D) Crée invoice_status_history pour la traçabilité (§13). Chaque
 *      transition génère une ligne : from_status, to_status, reason,
 *      user_id, created_at.
 */
return new class extends Migration {
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────
        // A) MONTANTS EN ENTIERS FCFA
        // ──────────────────────────────────────────────────────────
        // Pré-arrondi sur les données existantes pour éviter la troncature
        // au moment du ALTER COLUMN. Si la connexion n'est pas MySQL,
        // les statements raw seront ignorés (les bases test sqlite::memory:
        // recréeront les tables à neuf).
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            // INVOICES
            DB::statement("
                UPDATE invoices SET
                    amount               = ROUND(COALESCE(amount, 0)),
                    net_ht               = ROUND(COALESCE(net_ht, 0)),
                    tva_amount           = ROUND(COALESCE(tva_amount, 0)),
                    tsp_amount           = ROUND(COALESCE(tsp_amount, 0)),
                    tm_total             = ROUND(COALESCE(tm_total, 0)),
                    odp_total            = ROUND(COALESCE(odp_total, 0)),
                    services_impression  = ROUND(COALESCE(services_impression, 0)),
                    services_pose_depose = ROUND(COALESCE(services_pose_depose, 0)),
                    amount_ttc           = ROUND(COALESCE(amount_ttc, 0)),
                    total_a_payer        = ROUND(COALESCE(total_a_payer, 0))
            ");
            DB::statement("
                ALTER TABLE invoices
                    MODIFY COLUMN amount               BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN net_ht               BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tva_amount           BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tsp_amount           BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_total             BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN odp_total            BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN services_impression  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN services_pose_depose BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN amount_ttc           BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN total_a_payer        BIGINT UNSIGNED NOT NULL DEFAULT 0
            ");

            // INVOICE_LINES
            DB::statement("
                UPDATE invoice_lines SET
                    pu_ht_mensuel     = ROUND(COALESCE(pu_ht_mensuel, 0)),
                    montant_ht_ligne  = ROUND(COALESCE(montant_ht_ligne, 0)),
                    odp_rate_applique = ROUND(COALESCE(odp_rate_applique, 0)),
                    tm_rate_applique  = ROUND(COALESCE(tm_rate_applique, 0)),
                    odp_ligne         = ROUND(COALESCE(odp_ligne, 0)),
                    tm_ligne          = ROUND(COALESCE(tm_ligne, 0))
            ");
            DB::statement("
                ALTER TABLE invoice_lines
                    MODIFY COLUMN pu_ht_mensuel     BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN montant_ht_ligne  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN odp_rate_applique BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_rate_applique  BIGINT UNSIGNED NOT NULL DEFAULT 1000,
                    MODIFY COLUMN odp_ligne         BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_ligne          BIGINT UNSIGNED NOT NULL DEFAULT 0
            ");

            // INVOICE_PAYMENTS
            DB::statement("UPDATE invoice_payments SET montant = ROUND(COALESCE(montant, 0))");
            DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN montant BIGINT UNSIGNED NOT NULL");

            // INVOICE_SERVICES
            if (Schema::hasTable('invoice_services')) {
                DB::statement("UPDATE invoice_services SET prix_ht = ROUND(COALESCE(prix_ht, 0))");
                DB::statement("ALTER TABLE invoice_services MODIFY COLUMN prix_ht BIGINT UNSIGNED NOT NULL DEFAULT 0");
            }

            // INVOICE_SCHEDULES
            if (Schema::hasTable('invoice_schedules')) {
                DB::statement("UPDATE invoice_schedules SET amount = ROUND(COALESCE(amount, 0))");
                DB::statement("ALTER TABLE invoice_schedules MODIFY COLUMN amount BIGINT UNSIGNED NOT NULL");
            }

            // COMMUNES
            DB::statement("
                UPDATE communes SET
                    odp_rate = ROUND(COALESCE(odp_rate, 0)),
                    tm_rate  = ROUND(COALESCE(tm_rate,  0))
            ");
            DB::statement("
                ALTER TABLE communes
                    MODIFY COLUMN odp_rate BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_rate  BIGINT UNSIGNED NOT NULL DEFAULT 0
            ");

            // COMMUNE_TAX_RATE_HISTORY
            if (Schema::hasTable('commune_tax_rate_history')) {
                DB::statement("
                    UPDATE commune_tax_rate_history SET
                        odp_rate = ROUND(COALESCE(odp_rate, 0)),
                        tm_rate  = ROUND(COALESCE(tm_rate,  0))
                ");
                DB::statement("
                    ALTER TABLE commune_tax_rate_history
                        MODIFY COLUMN odp_rate BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        MODIFY COLUMN tm_rate  BIGINT UNSIGNED NOT NULL DEFAULT 0
                ");
            }
        }

        // ──────────────────────────────────────────────────────────
        // B) commercial_user_id sur invoices
        // ──────────────────────────────────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'commercial_user_id')) {
                $table->foreignId('commercial_user_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Commercial responsable du compte client. Distinct de created_by (qui peut être un admin).');
                $table->index('commercial_user_id', 'idx_invoices_commercial');
            }
        });

        // Backfill : pour chaque facture existante avec une campagne, on
        // pose le commercial_user_id depuis la campagne (priorité
        // campaign.commercial_user_id, sinon campaign.user_id en fallback).
        if ($driver === 'mysql') {
            DB::statement("
                UPDATE invoices i
                INNER JOIN campaigns c ON c.id = i.campaign_id
                SET i.commercial_user_id = COALESCE(c.commercial_user_id, c.user_id)
                WHERE i.commercial_user_id IS NULL
                  AND COALESCE(c.commercial_user_id, c.user_id) IS NOT NULL
            ");
            // Sinon (factures sans campagne) → on tombe sur created_by
            DB::statement("
                UPDATE invoices SET commercial_user_id = created_by
                WHERE commercial_user_id IS NULL
                  AND created_by IS NOT NULL
            ");
        }

        // ──────────────────────────────────────────────────────────
        // C) Statuts élargis (4 → 9)
        // ──────────────────────────────────────────────────────────
        // Stratégie portable : on convertit l'enum en string(50) puis on
        // pose un CHECK explicite. Évite de jouer avec ALTER ENUM qui
        // bloque sur MySQL si des données existent.
        if ($driver === 'mysql') {
            // L'enum existant : brouillon/envoyee/payee/annulee. On
            // l'élargit en place (MySQL supporte l'ajout d'éléments à un
            // enum sans toucher aux données existantes).
            DB::statement("
                ALTER TABLE invoices MODIFY COLUMN status ENUM(
                    'brouillon',
                    'generee',
                    'validee',
                    'envoyee',
                    'partiellement_payee',
                    'payee',
                    'en_retard',
                    'litige',
                    'annulee'
                ) NOT NULL DEFAULT 'brouillon'
            ");
        }

        // ──────────────────────────────────────────────────────────
        // D) Table invoice_status_history (traçabilité §13)
        // ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('invoice_status_history')) {
            Schema::create('invoice_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->string('from_status', 50)->nullable()
                    ->comment('Statut avant transition. NULL = première transition / création.');
                $table->string('to_status', 50);
                $table->foreignId('user_id')->nullable()
                    ->constrained('users')->nullOnDelete()
                    ->comment('Auteur de la transition. NULL = système (job auto).');
                $table->string('reason', 500)->nullable()
                    ->comment('Motif (ex: "Versement complet reçu", "Annulation manuelle").');
                $table->boolean('auto')->default(false)
                    ->comment('true = transition déclenchée par observer/job, false = action utilisateur.');
                $table->timestamps();

                $table->index(['invoice_id', 'created_at'], 'idx_isth_invoice_chrono');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_status_history');

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'commercial_user_id')) {
                $table->dropForeign(['commercial_user_id']);
                $table->dropIndex('idx_invoices_commercial');
                $table->dropColumn('commercial_user_id');
            }
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            // Restaure l'enum d'origine
            DB::statement("
                ALTER TABLE invoices MODIFY COLUMN status ENUM(
                    'brouillon','envoyee','payee','annulee'
                ) NOT NULL DEFAULT 'brouillon'
            ");

            // Restaure decimal(15,2) sur les colonnes ENTIER (réversible)
            DB::statement("
                ALTER TABLE invoices
                    MODIFY COLUMN amount               DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN net_ht               DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tva_amount           DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tsp_amount           DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_total             DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN odp_total            DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN services_impression  DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN services_pose_depose DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN amount_ttc           DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN total_a_payer        DECIMAL(15,2) NOT NULL DEFAULT 0
            ");
            DB::statement("
                ALTER TABLE invoice_lines
                    MODIFY COLUMN pu_ht_mensuel     DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN montant_ht_ligne  DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN odp_rate_applique DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_rate_applique  DECIMAL(15,2) NOT NULL DEFAULT 1000,
                    MODIFY COLUMN odp_ligne         DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_ligne          DECIMAL(15,2) NOT NULL DEFAULT 0
            ");
            DB::statement("ALTER TABLE invoice_payments MODIFY COLUMN montant DECIMAL(15,2) NOT NULL");
            if (Schema::hasTable('invoice_services')) {
                DB::statement("ALTER TABLE invoice_services MODIFY COLUMN prix_ht DECIMAL(15,2) NOT NULL DEFAULT 0");
            }
            if (Schema::hasTable('invoice_schedules')) {
                DB::statement("ALTER TABLE invoice_schedules MODIFY COLUMN amount DECIMAL(15,2) NOT NULL");
            }
            DB::statement("
                ALTER TABLE communes
                    MODIFY COLUMN odp_rate DECIMAL(15,2) NOT NULL DEFAULT 0,
                    MODIFY COLUMN tm_rate  DECIMAL(15,2) NOT NULL DEFAULT 0
            ");
            if (Schema::hasTable('commune_tax_rate_history')) {
                DB::statement("
                    ALTER TABLE commune_tax_rate_history
                        MODIFY COLUMN odp_rate DECIMAL(15,2) NOT NULL DEFAULT 0,
                        MODIFY COLUMN tm_rate  DECIMAL(15,2) NOT NULL DEFAULT 0
                ");
            }
        }
    }
};
