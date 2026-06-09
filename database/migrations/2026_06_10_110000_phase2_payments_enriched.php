<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Paiements enrichis (cahier des charges §4 + §5).
 *
 * Ajoute sur invoice_payments les colonnes manquantes pour matcher la
 * définition complète d'un paiement selon le cahier :
 *
 *   §4 « Un paiement = date, montant, référence, BANQUE, moyen de
 *       paiement, PIÈCE JOINTE, note. »
 *   §5 « Une facture peut comporter un ou plusieurs ACOMPTES (paiement
 *       avec is_acompte = true). »
 *
 * Ajout aussi du moyen 'carte_bancaire' à l'enum mode (manquant en
 * Phase 1 — le cahier liste : espèces, chèque, virement, mobile money,
 * carte bancaire, autre).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_payments', 'bank')) {
                $table->string('bank', 100)->nullable()->after('reference')
                    ->comment('Banque émettrice (pour chèque/virement). Ex: SGCI, BICICI, UBA...');
            }
            if (!Schema::hasColumn('invoice_payments', 'is_acompte')) {
                $table->boolean('is_acompte')->default(false)->after('bank')
                    ->comment('Marqué si ce versement est un acompte (vs solde). Affichage spécifique + indicateur sur la facture.');
            }
            if (!Schema::hasColumn('invoice_payments', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('is_acompte')
                    ->comment('Chemin storage local de la pièce justificative (scan chèque, reçu, etc.). Stockage : storage/app/invoices/payments/{id}/');
            }
            if (!Schema::hasColumn('invoice_payments', 'attachment_original_name')) {
                $table->string('attachment_original_name', 200)->nullable()->after('attachment_path')
                    ->comment('Nom original du fichier au moment de l\'upload (pour téléchargement).');
            }
        });

        // Ajout 'carte_bancaire' à l'enum mode (cahier §4)
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("
                ALTER TABLE invoice_payments MODIFY COLUMN mode ENUM(
                    'especes',
                    'cheque',
                    'virement',
                    'mobile_money',
                    'carte_bancaire',
                    'compensation',
                    'autre'
                ) NOT NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_payments', 'attachment_original_name')) {
                $table->dropColumn('attachment_original_name');
            }
            if (Schema::hasColumn('invoice_payments', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
            if (Schema::hasColumn('invoice_payments', 'is_acompte')) {
                $table->dropColumn('is_acompte');
            }
            if (Schema::hasColumn('invoice_payments', 'bank')) {
                $table->dropColumn('bank');
            }
        });

        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("
                ALTER TABLE invoice_payments MODIFY COLUMN mode ENUM(
                    'especes','cheque','virement','mobile_money','compensation','autre'
                ) NOT NULL
            ");
        }
    }
};
