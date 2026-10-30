<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identité du client figée sur la facture (décision de la direction,
 * 2026-10-30) : nom, NCC, RCCM, régime d'imposition, adresse postale, au
 * moment où la facture sort du brouillon. Renommer ou regrouper une fiche
 * ne change plus le PDF d'une facture déjà émise. Règle :
 * App\Support\IdentiteClientFacture.
 *
 * Les factures déjà émises sont figées avec les données ACTUELLES de leur
 * fiche : l'identité au jour de leur émission n'a jamais été gardée.
 * Migration ADDITIVE (colonnes nullables) ; down() retire les colonnes.
 */
return new class extends Migration
{
    private const COLONNES = ['client_nom', 'client_ncc', 'client_rccm', 'client_regime_imposition', 'client_adresse', 'client_fige_le'];

    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'client_nom')) {
                $table->string('client_nom', 150)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'client_ncc')) {
                $table->string('client_ncc', 50)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'client_rccm')) {
                $table->string('client_rccm', 50)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'client_regime_imposition')) {
                $table->string('client_regime_imposition', 50)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'client_adresse')) {
                $table->text('client_adresse')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'client_fige_le')) {
                $table->timestamp('client_fige_le')->nullable();
            }
        });

        // Factures déjà émises (tout sauf le brouillon) : figées avec la fiche actuelle.
        $colonnesClient = array_values(array_intersect(['name', 'ncc', 'rccm', 'regime_imposition', 'address'], Schema::getColumnListing('clients')));
        DB::table('invoices')->where('status', '!=', 'brouillon')->whereNull('client_fige_le')
            ->orderBy('id')->select(['id', 'client_id'])
            ->chunkById(500, function ($factures) use ($colonnesClient) {
                $clients = DB::table('clients')->whereIn('id', $factures->pluck('client_id')->filter()->unique())
                    ->get(array_merge(['id'], $colonnesClient))->keyBy('id');
                foreach ($factures as $f) {
                    $c = $clients->get($f->client_id);
                    DB::table('invoices')->where('id', $f->id)->update([
                        'client_nom'               => $c->name ?? null,
                        'client_ncc'               => $c->ncc ?? null,
                        'client_rccm'              => $c->rccm ?? null,
                        'client_regime_imposition' => $c->regime_imposition ?? null,
                        'client_adresse'           => $c->address ?? null,
                        'client_fige_le'           => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }
        $presentes = array_values(array_filter(self::COLONNES, fn ($c) => Schema::hasColumn('invoices', $c)));
        if ($presentes) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn($presentes));
        }
    }
};
