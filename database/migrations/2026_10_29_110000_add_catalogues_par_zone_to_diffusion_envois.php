<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diffusion des disponibilités (2026-10-29) : deux catalogues par envoi,
 * un pour Abidjan et un pour l'intérieur du pays, en plus du catalogue
 * complet déjà en place.
 *
 * Règle validée le 2026-10-29 : le mail client ne montre plus qu'un seul
 * lien global mais DEUX liens, un par zone. Le catalogue complet continue
 * d'être produit — il sert au mail interne, au journal, et de filet de
 * sécurité pour tout modèle Brevo encore branché sur params.LIEN_PDF.
 *
 * Migration ADDITIVE : les colonnes existantes (pdf_path, pdf_token,
 * nb_panneaux) ne bougent pas. Les envois déjà partis gardent leur lien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('diffusion_envois')) {
            return;
        }

        Schema::table('diffusion_envois', function (Blueprint $table) {
            if (!Schema::hasColumn('diffusion_envois', 'nb_panneaux_abidjan')) {
                $table->unsignedInteger('nb_panneaux_abidjan')->default(0)->after('nb_panneaux');
            }
            if (!Schema::hasColumn('diffusion_envois', 'nb_panneaux_interieur')) {
                $table->unsignedInteger('nb_panneaux_interieur')->default(0)->after('nb_panneaux_abidjan');
            }
            if (!Schema::hasColumn('diffusion_envois', 'pdf_path_abidjan')) {
                $table->string('pdf_path_abidjan')->nullable()->after('pdf_token');
            }
            if (!Schema::hasColumn('diffusion_envois', 'pdf_token_abidjan')) {
                $table->string('pdf_token_abidjan', 64)->nullable()->after('pdf_path_abidjan');
            }
            if (!Schema::hasColumn('diffusion_envois', 'pdf_path_interieur')) {
                $table->string('pdf_path_interieur')->nullable()->after('pdf_token_abidjan');
            }
            if (!Schema::hasColumn('diffusion_envois', 'pdf_token_interieur')) {
                $table->string('pdf_token_interieur', 64)->nullable()->after('pdf_path_interieur');
            }
        });

        // Index posés à part, et SIMPLES plutôt qu'uniques : SQLite refuse
        // d'ajouter une colonne UNIQUE par ALTER TABLE, ce qui rendrait la
        // migration injouable en test local (cf. docs/TECHNICAL_DEBT.md,
        // « migrations non SQLite-portables »). L'unicité est déjà assurée
        // par le tirage : 48 caractères aléatoires par jeton. L'index sert
        // la recherche du lien public, qui interroge les trois colonnes.
        Schema::table('diffusion_envois', function (Blueprint $table) {
            foreach (['pdf_token_abidjan', 'pdf_token_interieur'] as $colonne) {
                $table->index($colonne, "diffusion_envois_{$colonne}_index");
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('diffusion_envois')) {
            return;
        }

        Schema::table('diffusion_envois', function (Blueprint $table) {
            foreach (['pdf_token_abidjan', 'pdf_token_interieur'] as $colonne) {
                if (Schema::hasColumn('diffusion_envois', $colonne)) {
                    $table->dropIndex("diffusion_envois_{$colonne}_index");
                }
            }
        });

        Schema::table('diffusion_envois', function (Blueprint $table) {
            foreach ([
                'pdf_token_interieur', 'pdf_path_interieur',
                'pdf_token_abidjan', 'pdf_path_abidjan',
                'nb_panneaux_interieur', 'nb_panneaux_abidjan',
            ] as $colonne) {
                if (Schema::hasColumn('diffusion_envois', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
