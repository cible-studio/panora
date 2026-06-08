<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes nécessaires pour la facture normalisée électronique (FNE)
 * et le suivi métier (remise, TSP, TM/ODP agrégés, services, lock,
 * référence avoir).
 *
 * Toutes idempotentes (hasColumn check) — safe à rejouer.
 *
 * Pourquoi stocker les agrégats au lieu de toujours recalculer ?
 *   Pour qu'une facture émise en 2025 reste IDENTIQUE même si on
 *   change le tarif ODP de Cocody en 2026, ou si on ajoute des lignes
 *   par erreur après émission. C'est un document fiscal — figé.
 *
 * locked_at / locked_by_id : verrouillage automatique au markSent.
 * Évite les modifs sauvages d'une facture déjà envoyée au client.
 *
 * credit_note_for_id : lien vers la facture source si cette ligne est
 * une note de crédit (avoir). Permet le rapprochement comptable.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'remise_pct')) {
                $table->decimal('remise_pct', 5, 2)->default(0)->after('campaign_id')
                    ->comment('Remise globale en %, 0-100');
            }
            if (!Schema::hasColumn('invoices', 'net_ht')) {
                $table->decimal('net_ht', 15, 2)->default(0)->after('amount')
                    ->comment('Net HT après remise = amount × (1 - remise_pct/100)');
            }
            if (!Schema::hasColumn('invoices', 'tva_amount')) {
                $table->decimal('tva_amount', 15, 2)->default(0)->after('tva')
                    ->comment('Montant TVA en FCFA = net_ht × tva/100');
            }
            if (!Schema::hasColumn('invoices', 'tsp_amount')) {
                $table->decimal('tsp_amount', 15, 2)->default(0)->after('tva_amount')
                    ->comment('Taxe Soutien à la Production = net_ht × 3%');
            }
            if (!Schema::hasColumn('invoices', 'tm_total')) {
                $table->decimal('tm_total', 15, 2)->default(0)->after('tsp_amount')
                    ->comment('Somme TM des lignes');
            }
            if (!Schema::hasColumn('invoices', 'odp_total')) {
                $table->decimal('odp_total', 15, 2)->default(0)->after('tm_total')
                    ->comment('Somme ODP des lignes');
            }
            if (!Schema::hasColumn('invoices', 'services_impression')) {
                $table->decimal('services_impression', 15, 2)->default(0)->after('odp_total')
                    ->comment('Frais impression HT — TVA 18% appliquée');
            }
            if (!Schema::hasColumn('invoices', 'services_pose_depose')) {
                $table->decimal('services_pose_depose', 15, 2)->default(0)->after('services_impression')
                    ->comment('Frais pose-dépose HT — TVA 18% appliquée');
            }
            if (!Schema::hasColumn('invoices', 'total_a_payer')) {
                $table->decimal('total_a_payer', 15, 2)->default(0)->after('amount_ttc')
                    ->comment('Total final FNE — TTC + autres taxes + services TTC');
            }
            if (!Schema::hasColumn('invoices', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('total_a_payer')
                    ->comment('Verrouillé au markSent — empêche les modifs');
            }
            if (!Schema::hasColumn('invoices', 'locked_by_id')) {
                $table->foreignId('locked_by_id')->nullable()->after('locked_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'credit_note_for_id')) {
                $table->foreignId('credit_note_for_id')->nullable()->after('locked_by_id')
                    ->constrained('invoices')->nullOnDelete()
                    ->comment('Si non null, cette facture est un AVOIR sur la facture pointée');
            }
            if (!Schema::hasColumn('invoices', 'campaign_year')) {
                $table->year('campaign_year')->nullable()->after('credit_note_for_id')
                    ->comment('Année appliquée pour résoudre les tarifs ODP/TM historisés');
            }
            if (!Schema::hasColumn('invoices', 'notes_client')) {
                $table->text('notes_client')->nullable()->after('campaign_year')
                    ->comment('Texte libre affiché en bas de facture (conditions, IBAN, etc.)');
            }
        });

        // Backfill pour les factures historiques :
        //   net_ht = amount, tva_amount = amount_ttc - amount,
        //   total_a_payer = amount_ttc. Les autres taxes restent à 0
        //   (on ne sait pas les recalculer rétroactivement).
        if (Schema::hasColumn('invoices', 'net_ht')) {
            \Illuminate\Support\Facades\DB::statement('
                UPDATE invoices
                SET net_ht         = COALESCE(amount, 0),
                    tva_amount     = GREATEST(COALESCE(amount_ttc, 0) - COALESCE(amount, 0), 0),
                    total_a_payer  = COALESCE(amount_ttc, 0)
                WHERE net_ht = 0 AND amount > 0
            ');
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'notes_client', 'campaign_year', 'credit_note_for_id',
                'locked_by_id', 'locked_at', 'total_a_payer',
                'services_pose_depose', 'services_impression',
                'odp_total', 'tm_total', 'tsp_amount',
                'tva_amount', 'net_ht', 'remise_pct',
            ] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    try { $table->dropConstrainedForeignId($col); } catch (\Throwable) {}
                    try { $table->dropColumn($col); } catch (\Throwable) {}
                }
            }
        });
    }
};
